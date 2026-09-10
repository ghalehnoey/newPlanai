<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMemory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationMemoryService
{
    public const DEFAULTS = [
        'ai_memory_enabled' => 1,
        'ai_memory_recent_turns' => 3,
        'ai_memory_previous_conversations' => 3,
        'ai_memory_summary_max_characters' => 1600,
        'ai_memory_cross_chat_max_characters' => 900,
        'ai_memory_context_token_budget' => 3000,
        'ai_memory_recent_message_max_characters' => 3000,
        'ai_memory_source_message_limit' => 160,
        'ai_memory_reuse_files_limit' => 1,
        'ai_memory_file_reuse_turns' => 2,
    ];

    public const SETTING_KEYS = [
        'ai_memory_enabled',
        'ai_memory_recent_turns',
        'ai_memory_previous_conversations',
        'ai_memory_summary_max_characters',
        'ai_memory_cross_chat_max_characters',
        'ai_memory_context_token_budget',
        'ai_memory_recent_message_max_characters',
        'ai_memory_source_message_limit',
        'ai_memory_reuse_files_limit',
        'ai_memory_file_reuse_turns',
    ];

    private const SETTINGS_CACHE_KEY = 'ai_memory_settings:v3';
    private const MEMORY_VERSION = 1;

    public function __construct(private LocalMemorySummarizer $summarizer)
    {
    }

    /** @return array<string, int> */
    public function settings(): array
    {
        return Cache::remember(self::SETTINGS_CACHE_KEY, 60, static function (): array {
            $stored = DB::table('settings')
                ->whereIn('key', self::SETTING_KEYS)
                ->pluck('value', 'key');
            $settings = self::DEFAULTS;

            foreach (self::SETTING_KEYS as $key) {
                if ($stored->has($key)) {
                    $settings[$key] = (int) $stored->get($key);
                }
            }

            return $settings;
        });
    }

    public function forgetSettingsCache(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    /**
     * @return array{
     *   messages:array<int,array{role:string,content:string}>,
     *   was_limited:bool,
     *   memory_used:bool,
     *   recent_turns_used:int,
     *   estimated_context_tokens:int,
     *   file_reuse_limit:int,
     *   file_reuse_after_message_id:?int
     * }
     */
    public function buildContext(
        Conversation $conversation,
        int $currentMessageId,
        string $currentQuestion = ''
    ): array {
        $settings = $this->settings();
        $recentTurnLimit = $this->clamp($settings['ai_memory_recent_turns'], 0, 20);
        $fileTurnLimit = $this->clamp($settings['ai_memory_file_reuse_turns'], 0, 20);
        $allTurns = $this->loadRecentCompleteTurns(
            $conversation,
            $currentMessageId,
            max($recentTurnLimit, $fileTurnLimit),
        );
        $recentTurns = $recentTurnLimit > 0
            ? array_slice($allTurns, -$recentTurnLimit)
            : [];
        $fileTurns = $fileTurnLimit > 0
            ? array_slice($allTurns, -$fileTurnLimit)
            : [];

        $oldestRecentId = $recentTurns !== []
            ? (int) $recentTurns[0][0]['id']
            : $currentMessageId;
        $summaryBoundary = max(0, $oldestRecentId - 1);
        $hasOlderMessages = $summaryBoundary > 0 && $conversation->messages()
            ->where('id', '<=', $summaryBoundary)
            ->exists();

        $currentSummary = '';
        $previousSummaries = [];
        $memoryEnabled = (bool) $settings['ai_memory_enabled'];

        if ($memoryEnabled && $hasOlderMessages) {
            $currentSummary = $this->refreshMemory($conversation, $summaryBoundary, $settings);
        }

        $previousCount = $this->clamp($settings['ai_memory_previous_conversations'], 0, 10);
        if ($memoryEnabled && $previousCount > 0) {
            $previousSummaries = $this->previousConversationSummaries(
                $conversation,
                $currentQuestion,
                $previousCount,
                $settings,
            );
        }

        $messageMaxCharacters = $this->clamp(
            $settings['ai_memory_recent_message_max_characters'],
            500,
            12000,
        );
        $trimmedRawMessage = false;
        foreach ($recentTurns as &$turn) {
            foreach ($turn as &$message) {
                $original = (string) $message['content'];
                $message['content'] = $this->trimKeepingConclusion($original, $messageMaxCharacters);
                $trimmedRawMessage = $trimmedRawMessage || $message['content'] !== trim($original);
            }
            unset($message);
        }
        unset($turn);

        $context = $this->fitContextBudget(
            $recentTurns,
            $currentSummary,
            $previousSummaries,
            $this->clamp($settings['ai_memory_context_token_budget'], 800, 30000),
            $this->clamp($settings['ai_memory_cross_chat_max_characters'], 0, 10000),
        );

        $fileReuseAfterMessageId = null;
        if ($fileTurns !== []) {
            $fileReuseAfterMessageId = (int) $fileTurns[0][0]['id'];
        }

        $fileReuseLimit = $this->clamp($settings['ai_memory_reuse_files_limit'], 0, 3);
        if (!$this->refersToPriorFile($currentQuestion)) {
            $fileReuseLimit = 0;
        }

        return [
            'messages' => $context['messages'],
            'was_limited' => $hasOlderMessages || $context['trimmed'] || $trimmedRawMessage,
            'memory_used' => $context['memory_used'],
            'recent_turns_used' => $context['recent_turns_used'],
            'estimated_context_tokens' => $context['estimated_tokens'],
            'file_reuse_limit' => $fileReuseLimit,
            'file_reuse_after_message_id' => $fileReuseAfterMessageId,
        ];
    }

    /**
     * Load only completed user/assistant pairs. Failed orphan user messages are not replayed.
     *
     * @return array<int,array{0:array<string,mixed>,1:array<string,mixed>}>
     */
    private function loadRecentCompleteTurns(
        Conversation $conversation,
        int $beforeMessageId,
        int $turnLimit
    ): array {
        if ($turnLimit <= 0) {
            return [];
        }

        $candidateLimit = min(500, max(80, $turnLimit * 10 + 20));
        $messages = $conversation->messages()
            ->where('id', '<', $beforeMessageId)
            ->whereIn('role', ['user', 'assistant'])
            ->reorder('id', 'desc')
            ->limit($candidateLimit)
            ->get(['id', 'role', 'content'])
            ->reverse()
            ->values();

        return array_slice($this->pairCompleteTurns($messages), -$turnLimit);
    }

    /**
     * @param iterable<int,object|array<string,mixed>> $messages
     * @return array<int,array{0:array<string,mixed>,1:array<string,mixed>}>
     */
    private function pairCompleteTurns(iterable $messages): array
    {
        $turns = [];
        $pendingUser = null;

        foreach ($messages as $message) {
            $row = [
                'id' => (int) $this->value($message, 'id'),
                'role' => (string) $this->value($message, 'role'),
                'content' => trim((string) $this->value($message, 'content')),
            ];
            if ($row['content'] === '') {
                continue;
            }
            if ($row['role'] === 'user') {
                $pendingUser = $row;
                continue;
            }
            if ($row['role'] === 'assistant' && $pendingUser !== null) {
                $turns[] = [$pendingUser, $row];
                $pendingUser = null;
            }
        }

        return $turns;
    }

    /** @param array<string,int> $settings */
    private function refreshMemory(Conversation $conversation, int $boundary, array $settings): string
    {
        if ($boundary <= 0) {
            return '';
        }

        try {
            return Cache::lock('conversation-memory:' . $conversation->id, 15)
                ->block(3, function () use ($conversation, $boundary, $settings): string {
                    $memory = ConversationMemory::firstOrNew([
                        'conversation_id' => $conversation->id,
                    ]);
                    $existingItems = collect((array) $memory->memory_items)
                        ->filter(static fn (array $item): bool => (int) ($item['message_id'] ?? 0) <= $boundary)
                        ->values()
                        ->all();
                    $through = min($boundary, max(0, (int) $memory->summarized_through_message_id));
                    $sourceLimit = $this->clamp($settings['ai_memory_source_message_limit'], 20, 500);

                    if (!$memory->exists || (int) $memory->version !== self::MEMORY_VERSION) {
                        $rows = $conversation->messages()
                            ->where('id', '<=', $boundary)
                            ->whereIn('role', ['user', 'assistant'])
                            ->reorder('id', 'desc')
                            ->limit($sourceLimit)
                            ->get(['id', 'role', 'content'])
                            ->reverse()
                            ->values();
                        $existingItems = [];
                        $through = $boundary;
                    } elseif ($through < $boundary) {
                        // Prefer the newest aged-out turns if an admin sharply reduces the raw window.
                        $rows = $conversation->messages()
                            ->where('id', '>', $through)
                            ->where('id', '<=', $boundary)
                            ->whereIn('role', ['user', 'assistant'])
                            ->reorder('id', 'desc')
                            ->limit($sourceLimit)
                            ->get(['id', 'role', 'content'])
                            ->reverse()
                            ->values();
                        $through = $boundary;
                    } else {
                        $rows = collect();
                    }

                    $completeMessages = collect($this->pairCompleteTurns($rows))->flatten(1)->values();
                    $result = $this->summarizer->summarize(
                        $existingItems,
                        $completeMessages,
                        $this->clamp($settings['ai_memory_summary_max_characters'], 600, 12000),
                    );

                    $memory->fill([
                        'user_id' => $conversation->user_id,
                        'summary_text' => $result['summary'],
                        'memory_items' => $result['items'],
                        'summarized_through_message_id' => $through,
                        'source_message_count' => (int) $memory->source_message_count + $completeMessages->count(),
                        'estimated_tokens' => $result['estimated_tokens'],
                        'version' => self::MEMORY_VERSION,
                    ])->save();

                    return $result['summary'];
                });
        } catch (Throwable $error) {
            Log::warning('Local conversation memory refresh failed', [
                'conversation_id' => $conversation->id,
                'error' => $error->getMessage(),
            ]);

            $memory = ConversationMemory::where('conversation_id', $conversation->id)->first();
            return (string) ($memory?->summary_text ?? '');
        }
    }

    /**
     * @param array<string,int> $settings
     * @return array<int,array{title:string,summary:string,score:int}>
     */
    private function previousConversationSummaries(
        Conversation $current,
        string $question,
        int $limit,
        array $settings
    ): array {
        $candidateLimit = min(30, max(10, $limit * 4));
        $candidates = Conversation::query()
            ->where('user_id', $current->user_id)
            ->where('id', '!=', $current->id)
            ->whereHas('messages', static fn ($query) => $query->where('role', 'assistant'))
            ->orderByDesc('updated_at')
            ->limit($candidateLimit)
            ->get(['id', 'user_id', 'title', 'updated_at']);
        $existing = ConversationMemory::whereIn('conversation_id', $candidates->pluck('id'))
            ->get()
            ->keyBy('conversation_id');
        $questionWords = $this->keywords($question);

        $ranked = $candidates->values()->map(function (Conversation $conversation, int $index) use ($existing, $questionWords, $candidateLimit): array {
            $memory = $existing->get($conversation->id);
            $haystack = (string) $conversation->title . ' ' . (string) ($memory?->summary_text ?? '');
            $overlap = count(array_intersect($questionWords, $this->keywords($haystack)));

            return [
                'conversation' => $conversation,
                'overlap' => $overlap,
                'score' => $overlap * 20 + ($candidateLimit - $index),
            ];
        })->sortByDesc('score')->values();

        $relevant = $ranked
            ->filter(static fn (array $candidate): bool => (int) $candidate['overlap'] > 0)
            ->take($limit);

        // A short message such as «ادامه بده» has no useful keywords. In that case only
        // the latest previous conversation is used instead of paying for several guesses.
        if ($relevant->isEmpty() && $this->refersToEarlierConversation($question)) {
            $relevant = $ranked->sortByDesc(
                static fn (array $candidate): int => $candidate['conversation']->updated_at?->getTimestamp() ?? 0
            )->take(1);
        }

        $out = [];
        foreach ($relevant as $candidate) {
            /** @var Conversation $conversation */
            $conversation = $candidate['conversation'];
            $boundary = $this->lastCompletedAssistantId($conversation);
            if ($boundary <= 0) {
                continue;
            }
            $summary = $this->refreshMemory($conversation, $boundary, $settings);
            if ($summary === '') {
                continue;
            }
            $out[] = [
                'title' => trim((string) $conversation->title) ?: 'گفت‌وگوی قبلی',
                'summary' => $summary,
                'score' => (int) $candidate['score'],
            ];
        }

        return $out;
    }

    private function lastCompletedAssistantId(Conversation $conversation): int
    {
        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->reorder('id', 'desc')
            ->limit(80)
            ->get(['id', 'role', 'content'])
            ->reverse()
            ->values();
        $turns = $this->pairCompleteTurns($messages);

        if ($turns === []) {
            return 0;
        }

        $lastTurn = end($turns);
        return (int) $lastTurn[1]['id'];
    }

    /**
     * @param array<int,array{0:array<string,mixed>,1:array<string,mixed>}> $turns
     * @param array<int,array{title:string,summary:string,score:int}> $previous
     * @return array{messages:array<int,array{role:string,content:string}>,trimmed:bool,memory_used:bool,recent_turns_used:int,estimated_tokens:int}
     */
    private function fitContextBudget(
        array $turns,
        string $currentSummary,
        array $previous,
        int $tokenBudget,
        int $crossChatMaxCharacters
    ): array {
        $trimmed = false;
        $recentTokenTarget = max(400, (int) floor($tokenBudget * 0.72));

        while (count($turns) > 1 && $this->estimateTurnTokens($turns) > $recentTokenTarget) {
            array_shift($turns);
            $trimmed = true;
        }

        if ($turns !== [] && $this->estimateTurnTokens($turns) > $recentTokenTarget) {
            $perMessageBudget = max(180, (int) floor(($recentTokenTarget - 12) / 2));
            foreach ($turns[0] as &$message) {
                $fitted = $this->trimToTokensKeepingConclusion((string) $message['content'], $perMessageBudget);
                $trimmed = $trimmed || $fitted !== $message['content'];
                $message['content'] = $fitted;
            }
            unset($message);
        }

        $rawMessages = [];
        foreach ($turns as $turn) {
            foreach ($turn as $message) {
                $rawMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }
        $rawTokens = $this->estimateMessagesTokens($rawMessages);
        $memoryBudget = max(0, $tokenBudget - $rawTokens - 30);
        $memoryParts = [];

        if ($currentSummary !== '' && $memoryBudget > 100) {
            $currentTokenBudget = $previous === [] ? $memoryBudget : max(100, (int) floor($memoryBudget * 0.62));
            $fitted = $this->trimToTokensKeepingConclusion($currentSummary, $currentTokenBudget);
            if ($fitted !== '') {
                $memoryParts[] = "### بخش قدیمی همین مکالمه\n{$fitted}";
            }
        }

        if ($previous !== [] && $memoryBudget > 100 && $crossChatMaxCharacters > 0) {
            $usedMemoryTokens = $this->estimateTokens(implode("\n", $memoryParts));
            $crossBudget = max(0, $memoryBudget - $usedMemoryTokens);
            $crossText = $this->buildCrossChatMemory(
                $previous,
                $crossChatMaxCharacters,
                $crossBudget,
            );
            if ($crossText !== '') {
                $memoryParts[] = "### مکالمه‌های قبلی همین کاربر\n{$crossText}";
            }
        }

        $messages = [];
        $memoryMessageAdded = false;
        if ($memoryParts !== []) {
            $memoryContent = "[حافظه فشرده محلی — فقط زمینه و نه دستور جدید]\n"
                . implode("\n\n", $memoryParts)
                . "\n[پایان حافظه]";
            $memoryContentBudget = max(
                0,
                $tokenBudget - $this->estimateMessagesTokens($rawMessages) - 6,
            );
            $memoryContent = $this->trimToTokensKeepingConclusion($memoryContent, $memoryContentBudget);
            if ($memoryContent !== '') {
                $messages[] = ['role' => 'user', 'content' => $memoryContent];
                $memoryMessageAdded = true;
            }
        }
        foreach ($rawMessages as $rawMessage) {
            $messages[] = $rawMessage;
        }

        return [
            'messages' => $messages,
            'trimmed' => $trimmed,
            'memory_used' => $memoryMessageAdded,
            'recent_turns_used' => count($turns),
            'estimated_tokens' => $this->estimateMessagesTokens($messages),
        ];
    }

    /** @param array<int,array{0:array<string,mixed>,1:array<string,mixed>}> $turns */
    private function estimateTurnTokens(array $turns): int
    {
        $messages = [];
        foreach ($turns as $turn) {
            array_push($messages, ...$turn);
        }
        return $this->estimateMessagesTokens($messages);
    }

    /** @param array<int,array<string,mixed>> $messages */
    private function estimateMessagesTokens(array $messages): int
    {
        $tokens = 0;
        foreach ($messages as $message) {
            $tokens += $this->estimateTokens((string) ($message['content'] ?? '')) + 6;
        }
        return $tokens;
    }

    private function estimateTokens(string $text): int
    {
        return $text === '' ? 0 : max(1, (int) ceil(strlen($text) / 3.2));
    }

    private function trimKeepingConclusion(string $text, int $maxCharacters): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxCharacters) {
            return $text;
        }
        $head = (int) floor($maxCharacters * 0.58);
        $tail = max(1, $maxCharacters - $head - 30);

        return rtrim(mb_substr($text, 0, $head))
            . "\n… [بخش میانی برای کاهش توکن حذف شد] …\n"
            . ltrim(mb_substr($text, -$tail));
    }

    private function truncateToTokens(string $text, int $maxTokens): string
    {
        if ($maxTokens <= 0 || $text === '') {
            return '';
        }
        if ($this->estimateTokens($text) <= $maxTokens) {
            return $text;
        }

        $low = 0;
        $high = mb_strlen($text);
        $fitted = '';
        while ($low <= $high) {
            $mid = (int) floor(($low + $high) / 2);
            $candidate = $mid > 0 ? rtrim(mb_substr($text, 0, $mid)) . '…' : '';
            if ($candidate !== '' && $this->estimateTokens($candidate) <= $maxTokens) {
                $fitted = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $fitted;
    }

    private function trimToTokensKeepingConclusion(string $text, int $maxTokens): string
    {
        if ($maxTokens <= 0 || $text === '') {
            return '';
        }
        if ($this->estimateTokens($text) <= $maxTokens) {
            return $text;
        }

        $characterBudget = max(1, (int) floor(mb_strlen($text) * $maxTokens / $this->estimateTokens($text)));
        $head = max(1, (int) floor($characterBudget * 0.58));
        $tail = max(1, $characterBudget - $head - 24);

        $fitted = rtrim(mb_substr($text, 0, $head))
            . "\n… [میانه حذف شد] …\n"
            . ltrim(mb_substr($text, -$tail));

        return $this->estimateTokens($fitted) <= $maxTokens
            ? $fitted
            : $this->truncateToTokens($fitted, $maxTokens);
    }

    private function truncateCharacters(string $text, int $maxCharacters): string
    {
        return mb_strlen($text) <= $maxCharacters
            ? $text
            : rtrim(mb_substr($text, 0, max(1, $maxCharacters - 1))) . '…';
    }

    /** @param array<int,array{title:string,summary:string,score:int}> $previous */
    private function buildCrossChatMemory(array $previous, int $maxCharacters, int $maxTokens): string
    {
        if ($previous === [] || $maxCharacters < 40 || $maxTokens < 20) {
            return '';
        }

        $count = count($previous);
        $charactersPerConversation = max(1, (int) floor(($maxCharacters - (($count - 1) * 2)) / $count));
        $tokensPerConversation = max(1, (int) floor(($maxTokens - (($count - 1) * 2)) / $count));
        $blocks = [];

        foreach ($previous as $item) {
            $titleLimit = max(20, min(70, (int) floor($charactersPerConversation * 0.30)));
            $title = $this->singleLine($item['title'], $titleLimit);
            $prefix = "گفت‌وگو «{$title}»:\n";
            $summary = $this->truncateCharacters(
                $item['summary'],
                max(1, $charactersPerConversation - mb_strlen($prefix)),
            );
            $block = $this->truncateToTokens($prefix . $summary, $tokensPerConversation);
            if ($block !== '') {
                $blocks[] = $block;
            }
        }

        $combined = $this->truncateCharacters(implode("\n\n", $blocks), $maxCharacters);
        return $this->truncateToTokens($combined, $maxTokens);
    }

    /** @return array<int,string> */
    private function keywords(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $stop = array_flip([
            'این', 'آن', 'برای', 'با', 'از', 'به', 'که', 'را', 'در', 'و', 'یا', 'یک',
            'است', 'هست', 'بود', 'شد', 'شود', 'من', 'شما', 'اگر', 'اما', 'روی', 'هم',
            'the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'has',
        ]);

        return array_values(array_unique(array_filter($words, static function (string $word) use ($stop): bool {
            return mb_strlen($word) >= 3 && !isset($stop[$word]);
        })));
    }

    private function singleLine(string $text, int $maxCharacters): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        return $this->truncateCharacters($text, $maxCharacters);
    }

    private function refersToEarlierConversation(string $question): bool
    {
        return (bool) preg_match(
            '/(?:ادامه(?: بده| بحث)?|قبلاً|قبلا|دفعه[‌ ]?قبل|بحث[‌ ]?قبل|چت[‌ ]?قبل|گفتگو(?:ی)?[‌ ]?قبل|مکالمه(?:‌ی|ی)?[‌ ]?قبل)/u',
            $question,
        );
    }

    private function refersToPriorFile(string $question): bool
    {
        return (bool) preg_match(
            '/(?:فایل|pdf|پی[‌ ]?دی[‌ ]?اف|پیوست|سند|مدرک|تصویر|عکس|صفحه|برگه|متن[‌ ]?بالا|ادامه[‌ ]?تحلیل)/iu',
            $question,
        );
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function value(object|array $source, string $key): mixed
    {
        return is_array($source) ? ($source[$key] ?? null) : ($source->{$key} ?? null);
    }
}
