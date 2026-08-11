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
        private AiApiService        $aiApiService,
        private FileService         $fileService,
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
            ['value' => 'gpt',      'name' => 'GPT-5.1 (بهترین کیفیت)'],
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
        /* ---------- Validation ---------- */
        $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'question'        => 'nullable|string',
            'model'           => 'required|in:gpt,claude,grok,deepseek',
            'files.*'         => 'nullable|mimes:jpg,jpeg,png,webp,gif|max:8192',
        ]);

        $messageText = (string) ($request->input('question') ?? '');

        if ($messageText === '' && !$request->hasFile('files')) {
            return response()->json([
                'error' => 'لطفاً متن یا تصویر ارسال کنید.'
            ], 422);
        }

        $user = auth()->user();
        $subscription = $user->activeSubscription();

        /* ---------- Subscription ---------- */
        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'error' => '<a href="https://static.hakimisadr.com/plans" style="color: #e63946; font-weight: bold; text-decoration: underline;">برای ادامه چت کلیک کنید</a><br>'
                    . '<span style="color: #000000;">سؤالات مجاز تمام شده</span><br>'
                    . '<span style="color: #000000;">وب سایت دکتر حکیمی صدر</span>',
            ], 403);
        }

        if ($subscription->remainingQuestions() <= 0) {
            return response()->json([
                'error' => '<a href="https://static.hakimisadr.com/plans" style="color: #e63946; font-weight: bold; text-decoration: underline;">برای ادامه چت کلیک کنید</a><br>'
                    . '<span style="color: #000000;">سؤالات مجاز تمام شده</span><br>'
                    . '<span style="color: #000000;">وب سایت دکتر حکیمی صدر</span>',
            ], 403);
        }

        /* ---------- Conversation ---------- */
        if ($request->filled('conversation_id')) {
            $conversation = Conversation::where('id', $request->conversation_id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'title'   => 'مکالمه جدید',
            ]);
        }

        /* ---------- Files ---------- */
        $uploadedFiles = [];

        if ($request->hasFile('files')) {

            if ($request->model !== 'gpt') {
                return response()->json([
                    'error' => 'برای آپلود و تحلیل عکس، مدل رو به GPT  تغییر بدید.'
                ], 400);
            }

            foreach ($request->file('files') as $file) {
                $uploadedFiles[] = $this->fileService->handleImageUpload(
                    $file,
                    $user->id,
                    $conversation->id
                );
            }
        }

        /* ---------- Final user message ---------- */
        $fullQuestion = $messageText;

        if (!empty($uploadedFiles)) {
            $fullQuestion .= "\n\n📷 تصویر ارسال شد — لطفاً با توجه به تصویر تحلیل کن.";
        }

        /* ---------- Save user message ---------- */
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'user',
            'content'         => $fullQuestion,
            'model'           => $request->model,
        ]);

        if (!empty($uploadedFiles)) {
            $userMessage->files()->attach(
                collect($uploadedFiles)->pluck('id')
            );
        }

        /* ✅ آخرین 2 چت قبلی (۲ رفت و برگشت اخیر = ۴ پیام user/assistant) قبل از پیام جدید */
        $previousChatMessages = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $userMessage->id)     // ✅ خود پیام جدید داخلش نیاد
            ->orderByDesc('id')
            ->take(4)                                 // ✅ 2 چت آخر = معمولاً 4 پیام
            ->get()
            ->reverse()                               // قدیمی‌تر اول
            ->values()
            ->map(function ($m) {
                return [
                    'role'    => $m->role,            // 'user' | 'assistant'
                    'content' => (string) $m->content,
                ];
            })
            ->toArray();

        $systemPrompt    = (string) $this->systemPromptService->getSystemPrompt();
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
                $conversation->update([
                    'title' => mb_strimwidth($messageText, 0, 60, '...', 'UTF-8')
                ]);
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
       STREAM AI API (SAFE)
     ============================ */
    private function streamAiApi(
        string $message,
        string $model,
        array $images,
        string $systemPrompt,
        array $previousChatMessages,
        callable $onChunk
    ) {
        /* ---------- Non-GPT ---------- */
        if ($model !== 'gpt') {
            // ✅ حالا برای مدل‌های دیگر هم ۲ چت آخر را می‌فرستیم (به صورت transcript استاندارد)
            $text = (string) $this->aiApiService->callWithHistory(
                $message,
                $model,
                $systemPrompt,
                $previousChatMessages
            );

            foreach (mb_str_split($text, 1) as $char) {
                $onChunk($char);
                usleep(12000);
            }
            return;
        }

        /* ---------- GPT (SSE) ---------- */
        $apiKey = config('services.gpt.key');

        // ✅ ساخت messages با history (۲ چت آخر) + سؤال جدید
        $messages = [
            ["role" => "system", "content" => $systemPrompt],
        ];

        foreach ($previousChatMessages as $m) {
            if (!isset($m['role'], $m['content'])) continue;
            if (!in_array($m['role'], ['user', 'assistant'], true)) continue;

            $messages[] = [
                "role" => $m['role'],
                "content" => [
                    ["type" => "text", "text" => (string) $m['content']]
                ]
            ];
        }

        $userContent = [
            ["type" => "text", "text" => $message]
        ];

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
            "model"       => "gpt-5.1",
            "messages"    => $messages,
            "stream"      => true,
            "temperature" => 0.1,
        ];

        $client = new Client(['timeout' => 0]);

        try {
            $response = $client->post(
                "https://api.openai.com/v1/chat/completions",
                [
                    'headers' => [
                        "Authorization" => "Bearer {$apiKey}",
                        "Content-Type"  => "application/json",
                    ],
                    'json'   => $payload,
                    'stream' => true,
                ]
            );
        } catch (\Throwable $e) {
            $onChunk("❌ خطا در اتصال به GPT");
            return;
        }

        $body   = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = trim(substr($buffer, 0, $pos));
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
        Log::info('getConversation called', [
            'conversation_id' => $id,
            'user_id' => auth()->id() ?? 'guest',
            'ip' => request()->ip(),
        ]);

        try {
            $conversation = auth()->user()->conversations()
                ->with(['messages.files'])
                ->findOrFail($id);

            Log::info('Conversation found', [
                'conversation_id' => $conversation->id,
                'messages_count' => $conversation->messages->count(),
            ]);

            $messages = $conversation->messages->sortBy('created_at')->values();

            $mappedMessages = $messages->map(function ($message) {
                return [
                    'id'      => $message->id,
                    'role'    => $message->role,
                    'content' => $message->content,
                    'files'   => $message->files->map(function ($file) {
                        return [
                            'id'            => $file->id,
                            'original_name' => $file->original_name ?? basename($file->path),
                        ];
                    })->toArray(),
                ];
            })->toArray();

            Log::info('Messages prepared for JSON', ['count' => count($mappedMessages)]);

            return response()->json([
                'messages' => $mappedMessages
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getConversation', [
                'conversation_id' => $id,
                'user_id' => auth()->id() ?? 'guest',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'خطا در لود مکالمه: ' . $e->getMessage()], 500);
        }
    }

    /* ============================
       حذف مکالمه
     ============================ */
    public function deleteConversation($id)
    {
        $conversation = auth()->user()
            ->conversations()
            ->with('messages.files')
            ->findOrFail($id);

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