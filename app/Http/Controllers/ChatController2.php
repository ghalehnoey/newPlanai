<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UploadedFile;
use App\Services\AiApiService;
use App\Services\ConversationService;
use App\Services\FileService;
use App\Services\SystemPromptService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

class ChatController extends BaseController
{
    public function __construct(
        private SystemPromptService $systemPromptService,
        private AiApiService $aiApiService,
        private FileService $fileService,
        private ConversationService $conversationService
    ) {
        $this->middleware('auth');
    }

    /* ============================
       صفحه اصلی
    ============================ */
    public function index()
    {
        $models = [
            ['value' => 'gpt',      'name' => 'GPT-5.4 (بهترین کیفیت)'],
            ['value' => 'claude',   'name' => 'Claude Sonnet'],
            ['value' => 'grok',     'name' => 'Grok-3'],
            ['value' => 'deepseek', 'name' => 'DeepSeek'],
        ];

        $conversations = auth()->user()
            ->conversations()
            ->with('latestMessage')
            ->orderByDesc('updated_at')
            ->get();

        return view('index', compact('models', 'conversations'));
    }

    /* ============================
       ارسال پیام (STREAM)
    ============================ */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'question'        => 'nullable|string',
            'model'           => 'required|in:gpt,claude,grok,deepseek',
            'files.*'         => 'nullable|mimes:jpg,jpeg,png,webp,gif|max:8192',
        ]);

        $messageText = (string) ($request->input('question') ?? '');
        if ($messageText === '' && !$request->hasFile('files')) {
            return response()->json(['error' => 'لطفاً متن یا تصویر ارسال کنید.'], 422);
        }

        $user = auth()->user();
        $subscription = $user->activeSubscription();

        if (!$subscription || !$subscription->isActive() || $subscription->remainingQuestions() <= 0) {
            return response()->json([
                'error' => '<a href="https://static.hakimisadr.com/plans" style="color: #e63946; font-weight: bold; text-decoration: underline;">برای ادامه چت کلیک کنید</a><br>'
                    . '<span style="color: #000000;">سؤالات مجاز تمام شده</span><br>'
                    . '<span style="color: #000000;">وب سایت دکتر حکیمی صدر</span>',
            ], 403);
        }

        $conversation = $request->filled('conversation_id')
            ? Conversation::where('id', $request->conversation_id)->where('user_id', $user->id)->firstOrFail()
            : Conversation::create(['user_id' => $user->id, 'title' => 'مکالمه جدید']);

        $uploadedFiles = [];
        if ($request->hasFile('files')) {
            if ($request->model !== 'gpt') {
                return response()->json(['error' => 'برای آپلود و تحلیل عکس، مدل رو به GPT تغییر بدید.'], 400);
            }

            foreach ($request->file('files') as $file) {
                $uploadedFiles[] = $this->fileService->handleImageUpload($file, $user->id, $conversation->id);
            }
        }

        $fullQuestion = $messageText;
        if (!empty($uploadedFiles)) {
            $fullQuestion .= "\n\n📷 تصویر ارسال شد — لطفاً با توجه به تصویر تحلیل کن.";
        }

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'user',
            'content'         => $fullQuestion,
            'model'           => $request->model,
        ]);

        if (!empty($uploadedFiles)) {
            $userMessage->files()->attach(collect($uploadedFiles)->pluck('id'));
        }

        $previousChatMessages = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $userMessage->id)
            ->orderByDesc('id')
            ->take(4)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->toArray();

        $systemPrompt = (string) $this->systemPromptService->getSystemPrompt();
        $assistantAnswer = '';

        /* ============================
           STREAM RESPONSE
        ============================ */
        return response()->stream(function () use (
            $conversation,
            $messageText,
            $uploadedFiles,
            $systemPrompt,
            $previousChatMessages,
            &$assistantAnswer,
            $subscription,
            $request
        ) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');

            $this->streamAiApi(
                $messageText,
                $request->model,
                $uploadedFiles,
                $systemPrompt,
                $previousChatMessages,
                function (string $chunk) use (&$assistantAnswer) {
                    $assistantAnswer .= $chunk;
                    echo $chunk;
                    flush();
                }
            );

            Message::create([
                'conversation_id' => $conversation->id,
                'user_id'         => auth()->id(),
                'role'            => 'assistant',
                'content'         => $assistantAnswer,
                'model'           => $request->model,
            ]);

            if ($conversation->title === 'مکالمه جدید' && trim($messageText) !== '') {
                $conversation->update(['title' => mb_strimwidth($messageText, 0, 60, '...', 'UTF-8')]);
            }

            $conversation->touch();
            $subscription->incrementQuestionsUsed();
        }, 200, [
            'Content-Type'      => 'text/plain; charset=utf-8',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Conversation-Id' => (string) $conversation->id,
        ]);
    }

    /* ============================
       STREAM AI API (SAFE + PROXY)
    ============================ */
    private function streamAiApi(
        string $message,
        string $model,
        array $images,
        string $systemPrompt,
        array $previousChatMessages,
        callable $onChunk
    ) {
        $apiKey = config('services.gpt.key');

        $messages = [
            ["role" => "system", "content" => $systemPrompt],
        ];

        foreach ($previousChatMessages as $m) {
            $messages[] = [
                "role" => $m['role'],
                "content" => [["type" => "text", "text" => $m['content']]]
            ];
        }

        $userContent = [["type" => "text", "text" => $message]];

        foreach ($images as $img) {
            $path = storage_path('app/public/' . $img->path);
            if (!file_exists($path)) continue;

            $userContent[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => "data:{$img->mime_type};base64," . base64_encode(file_get_contents($path))
                ]
            ];
        }

        $messages[] = ["role" => "user", "content" => $userContent];

        $payload = [
            "model" => $model,
            "messages" => $messages,
            "stream" => true,
            "temperature" => 0.1,
        ];

        $client = new Client([
            'timeout' => 0,
            'connect_timeout' => 160,
            'read_timeout' => 300,
            'proxy' => config('services.gpt.proxy'),
            'curl' => [
                CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 30,
            ],
        ]);

        try {
            $response = $client->post("https://api.openai.com/v1/chat/completions", [
                'headers' => [
                    "Authorization" => "Bearer {$apiKey}",
                    "Content-Type"  => "application/json",
                ],
                'json' => $payload,
                'stream' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('GPT connection error', ['error' => $e->getMessage()]);
            $onChunk("❌ خطا در اتصال به GPT: " . $e->getMessage());
            return;
        }

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || $line === 'data: [DONE]') continue;
                if (substr($line, 0, 6) === 'data: ') {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);

                    if (is_array($data) && isset($data['choices'][0]['delta']['content'])) {
                        $onChunk((string) $data['choices'][0]['delta']['content']);
                    }
                }
            }
        }
    }

    /* ============================
       دریافت مکالمه
    ============================ */
    public function getConversation($id)
    {
        try {
            $conversation = auth()->user()->conversations()
                ->with(['messages.files'])
                ->findOrFail($id);

            $messages = $conversation->messages->sortBy('created_at')->values();
            $mappedMessages = $messages->map(fn($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'files' => $message->files->map(fn($file) => [
                    'id' => $file->id,
                    'original_name' => $file->original_name ?? basename($file->path),
                ])->toArray(),
            ])->toArray();

            return response()->json(['messages' => $mappedMessages]);
        } catch (\Exception $e) {
            Log::error('Error in getConversation', [
                'conversation_id' => $id,
                'user_id' => auth()->id() ?? 'guest',
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'خطا در لود مکالمه: ' . $e->getMessage()], 500);
        }
    }

    /* ============================
       حذف مکالمه
    ============================ */
    public function deleteConversation($id)
    {
        $conversation = auth()->user()->conversations()->with('messages.files')->findOrFail($id);
        $conversation->delete();
        return response()->json(['success' => true]);
    }

    /* ============================
       تغییر عنوان
    ============================ */
    public function renameConversation(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:100']);
        $conversation = auth()->user()->conversations()->findOrFail($id);
        $conversation->update(['title' => $request->title]);
        return response()->json(['success' => true]);
    }

    /* ============================
       مشاهده فایل
    ============================ */
    public function viewFile(UploadedFile $file)
    {
        $user = auth()->user();
        if ($file->user_id !== $user->id) abort(403);

        $path = storage_path('app/public/' . $file->path);
        if (!file_exists($path)) abort(404);

        return response()->file($path);
    }
}
