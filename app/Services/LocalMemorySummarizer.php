<?php

namespace App\Services;

use Illuminate\Support\Str;

class LocalMemorySummarizer
{
    private const MAX_ITEMS = 48;
    private const MAX_ITEMS_PER_MESSAGE = 3;
    private const MAX_ITEM_CHARACTERS = 360;

    /**
     * Build an extractive, deterministic summary locally. No external AI call is made.
     *
     * @param array<int, array<string, mixed>> $existingItems
     * @param iterable<int, object|array<string, mixed>> $messages
     * @return array{items:array<int, array<string, mixed>>,summary:string,estimated_tokens:int}
     */
    public function summarize(array $existingItems, iterable $messages, int $maxCharacters): array
    {
        $maxCharacters = max(600, min(12000, $maxCharacters));
        $items = [];

        foreach ($existingItems as $item) {
            $normalized = $this->normalizeExistingItem($item);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        foreach ($messages as $message) {
            foreach ($this->extractMessageItems($message) as $item) {
                $items[] = $item;
            }
        }

        $items = $this->deduplicate($items);
        $selected = $this->selectWithinBudget($items, $maxCharacters);
        usort($selected, static fn (array $a, array $b): int => ($a['message_id'] <=> $b['message_id']));

        $summary = implode("\n", array_map(
            static fn (array $item): string => '- ' . ($item['role'] === 'user' ? 'کاربر' : 'نتیجه قبلی') . ': ' . $item['text'],
            $selected,
        ));

        return [
            'items' => $selected,
            'summary' => $summary,
            'estimated_tokens' => $this->estimateTokens($summary),
        ];
    }

    /** @param array<string, mixed> $item */
    private function normalizeExistingItem(array $item): ?array
    {
        $role = (string) ($item['role'] ?? '');
        $text = $this->normalizeText((string) ($item['text'] ?? ''));
        if (!in_array($role, ['user', 'assistant'], true) || $text === '') {
            return null;
        }

        return [
            'message_id' => max(0, (int) ($item['message_id'] ?? 0)),
            'role' => $role,
            'text' => $this->truncate($text, self::MAX_ITEM_CHARACTERS),
            'score' => max(1, (int) ($item['score'] ?? 1)),
            'hash' => (string) ($item['hash'] ?? $this->hash($text)),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function extractMessageItems(object|array $message): array
    {
        $role = (string) $this->value($message, 'role');
        $messageId = max(0, (int) $this->value($message, 'id'));
        $content = $this->normalizeText((string) $this->value($message, 'content'));

        if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
            return [];
        }

        $content = $this->truncate($content, 6000);
        $sentences = preg_split('/(?:\r\n|\r|\n)+|(?<=[.!؟?؛;])\s+/u', $content) ?: [];
        $candidates = [];

        foreach ($sentences as $position => $sentence) {
            $sentence = $this->normalizeText($sentence);
            if (mb_strlen($sentence) < 8 || $this->isGeneric($sentence)) {
                continue;
            }

            $candidates[] = [
                'message_id' => $messageId,
                'role' => $role,
                'text' => $this->truncate($sentence, self::MAX_ITEM_CHARACTERS),
                'score' => $this->score($sentence, $role, (int) $position),
                'hash' => $this->hash($sentence),
            ];
        }

        if ($candidates === [] && mb_strlen($content) >= 8 && !$this->isGeneric($content)) {
            $candidates[] = [
                'message_id' => $messageId,
                'role' => $role,
                'text' => $this->truncate($content, self::MAX_ITEM_CHARACTERS),
                'score' => $this->score($content, $role, 0),
                'hash' => $this->hash($content),
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, self::MAX_ITEMS_PER_MESSAGE);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function deduplicate(array $items): array
    {
        usort($items, static fn (array $a, array $b): int => ($b['message_id'] <=> $a['message_id']));
        $seen = [];
        $out = [];

        foreach ($items as $item) {
            $hash = (string) $item['hash'];
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $out[] = $item;
        }

        return array_slice($out, 0, self::MAX_ITEMS * 3);
    }

    /**
     * Keep a recent core, then spend the rest of the budget on high-value facts.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function selectWithinBudget(array $items, int $maxCharacters): array
    {
        if ($items === []) {
            return [];
        }

        $newest = $items;
        usort($newest, static fn (array $a, array $b): int => $b['message_id'] <=> $a['message_id']);
        $priority = $items;
        usort($priority, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score']) ?: ($b['message_id'] <=> $a['message_id']);
        });

        $selected = [];
        $selectedHashes = [];
        $used = 0;
        $recentBudget = (int) floor($maxCharacters * 0.38);

        foreach (array_slice($newest, 0, 10) as $item) {
            $length = $this->renderedLength($item);
            if ($used + $length > $recentBudget && $selected !== []) {
                break;
            }
            $selected[] = $item;
            $selectedHashes[$item['hash']] = true;
            $used += $length;
        }

        foreach ($priority as $item) {
            if (isset($selectedHashes[$item['hash']]) || count($selected) >= self::MAX_ITEMS) {
                continue;
            }
            $length = $this->renderedLength($item);
            if ($used + $length > $maxCharacters) {
                continue;
            }
            $selected[] = $item;
            $selectedHashes[$item['hash']] = true;
            $used += $length;
        }

        return $selected;
    }

    private function score(string $sentence, string $role, int $position): int
    {
        $score = $role === 'user' ? 6 : 3;
        $score += $position === 0 ? 1 : 0;

        if (preg_match('/(?:\d|https?:\/\/|www\.|@|\b[A-Z]{2,}\b)/u', $sentence)) {
            $score += 5;
        }
        if (preg_match('/(نام|شماره|تاریخ|مبلغ|آدرس|ایمیل|تلفن|کد|پرونده|قرارداد|ماده|تبصره|مدل|تنظیم|فایل)/u', $sentence)) {
            $score += 4;
        }
        if (preg_match('/(می.?خواهم|ترجیح|نباید|باید|همیشه|هرگز|هدف|منظور|لازم|مهم)/u', $sentence)) {
            $score += 4;
        }
        if (preg_match('/(نتیجه|بنابراین|جمع.?بندی|تصمیم|انجام شد|تأیید|پیشنهاد|راهکار|مرحله بعد|حل شد)/u', $sentence)) {
            $score += 4;
        }
        if (mb_strlen($sentence) > 40 && mb_strlen($sentence) < 280) {
            $score += 2;
        }

        return $score;
    }

    private function isGeneric(string $text): bool
    {
        $flat = $this->hash($text);
        if (mb_strlen($flat) < 5) {
            return true;
        }

        foreach ([
            'سلام', 'خوبی', 'ممنون', 'متشکرم', 'خواهش میکنم', 'در خدمتم',
            'چطور میتوانم کمکتان کنم', 'امیدوارم حال شما خوب باشد',
            'تولید شده توسط هوش مصنوعی', 'در حال پاسخگویی',
        ] as $generic) {
            if ($flat === $this->hash($generic)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\0", '[پایان حافظه]', '[حافظه فشرده]'], ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function truncate(string $text, int $maxCharacters): string
    {
        if (mb_strlen($text) <= $maxCharacters) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $maxCharacters - 1))) . '…';
    }

    private function hash(string $text): string
    {
        $text = Str::lower($this->normalizeText($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
        return hash('sha256', $text);
    }

    /** @param array<string, mixed> $item */
    private function renderedLength(array $item): int
    {
        return mb_strlen((string) $item['text']) + 18;
    }

    private function estimateTokens(string $text): int
    {
        return $text === '' ? 0 : max(1, (int) ceil(strlen($text) / 3.2));
    }

    private function value(object|array $source, string $key): mixed
    {
        return is_array($source) ? ($source[$key] ?? null) : ($source->{$key} ?? null);
    }
}
