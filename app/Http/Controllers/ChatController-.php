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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
       ارسال پیام + فایل
     ============================ */
    public function sendMessage(Request $request) 
{
    $request->validate([
        'conversation_id' => 'nullable|exists:conversations,id',
        'question'        => 'nullable|string',
        'model'           => 'required|in:gpt,claude,grok,deepseek',
        'files.*'         => 'nullable|mimes:jpg,jpeg,png,webp,gif|max:8192',
    ]);

    if (!$request->question && !$request->hasFile('files')) {
        return response()->json(['error' => 'لطفاً متن یا تصویر ارسال کنید.'], 422);
    }

    $user = auth()->user();
    $subscription = $user->activeSubscription();

    /* ---------------------------------------------------
     | ۱) کنترل اشتراک / زمان / تعداد سؤال
     --------------------------------------------------- */
    if (!$subscription || !$subscription->isActive()) {
        return response()->json([
            'error' => 'اشتراک شما فعال نیست یا منقضی شده است.',
            'upgrade_url' => route('plans')
        ], 403);
    }

    if ($subscription->remainingQuestions() <= 0) {
        return response()->json([
            'error' => 'تعداد سؤال‌های شما تمام شده است.',
            'upgrade_url' => route('plans')
        ], 403);
    }

    /* ---------------------------------------------------
     | ۲) مکالمه
     --------------------------------------------------- */
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

    /* ---------------------------------------------------
     | ۳) آپلود فایل‌ها (در صورت وجود)
     --------------------------------------------------- */
    $uploadedFiles = [];

    if ($request->hasFile('files')) {

        if ($request->model !== 'gpt') {
            return response()->json([
                'error' => 'مدل انتخابی شما از تحلیل تصویر پشتیبانی نمی‌کند.'
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

    /* ---------------------------------------------------
     | ۴) متن کامل پیام کاربر
     --------------------------------------------------- */
    $fullQuestion = $request->question;

    if (!empty($uploadedFiles)) {
        $fullQuestion .= "\n\n📷 تصویر ارسال شد — لطفاً با توجه به تصویر تحلیل کن.";
    }

    /* ---------------------------------------------------
     | ۵) ذخیره پیام کاربر
     --------------------------------------------------- */
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

    /* ---------------------------------------------------
     | ۶) تماس با API مدل هوش مصنوعی
     --------------------------------------------------- */
    $systemPrompt = $this->systemPromptService->getSystemPrompt();

    $answer = $this->callAiApi(
        $request->question ?? "",
        $request->model,
        $uploadedFiles,
        $systemPrompt
    );

    /* ---------------------------------------------------
     | ۷) ذخیره پاسخ دستیار
     --------------------------------------------------- */
    Message::create([
        'conversation_id' => $conversation->id,
        'user_id'         => $user->id,
        'role'            => 'assistant',
        'content'         => $answer,
        'model'           => $request->model,
    ]);

    /* ---------------------------------------------------
     | ۸) اصلاح عنوان مکالمه
     --------------------------------------------------- */
    if ($conversation->title === 'مکالمه جدید') {
        $conversation->update([
            'title' => mb_strimwidth($request->question, 0, 60, '...', 'UTF-8')
        ]);
    }

    $conversation->touch();

    /* ---------------------------------------------------
     | ۹) کم‌کردن سهمیه سؤال
     --------------------------------------------------- */
    $subscription->incrementQuestionsUsed();

    /* ---------------------------------------------------
     | ۱۰) پاسخ نهایی
     --------------------------------------------------- */
    return response()->json([
        'success'         => true,
        'answer'          => $answer,
        'conversation_id' => $conversation->id,
        'remaining'       => $subscription->remainingQuestions(),
    ]);
}


    /* ============================
       تماس با GPT + تصویر
     ============================ */
    private function callAiApi(string $message, string $model, array $images = [], string $systemPrompt = "")
    {
        /* اگر مدل GPT نبود → مسیر سرویس اصلی */
        if ($model !== 'gpt') {
            return $this->aiApiService->call($message, $model, $systemPrompt);
        }

        $apiKey = config("services.gpt.key");

        /* ساختار بدنه پیام */
        $userContent = [
            [
                "type" => "text",
                "text" => $message
            ]
        ];

        /* اضافه کردن تصاویر */
        foreach ($images as $img) {

            $filePath = storage_path("app/public/" . $img->path);

            if (!file_exists($filePath)) {
                continue;
            }

            $binary = file_get_contents($filePath);
            $base64 = base64_encode($binary);
            $mime   = $img->mime_type;

            $userContent[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => "data:{$mime};base64,{$base64}"
                ]
            ];
        }

        $messages = [
            [
                "role"    => "system",
                "content" => $systemPrompt ?: "شما یک وکیل متخصص هستید."
            ],
            [
                "role"    => "user",
                "content" => $userContent
            ]
        ];

        $body = [
            "model" => "gpt-5.1",
            "messages" => $messages,
            "temperature" => 0.1,
            "max_completion_tokens" => 6000,
        ];

        try {
            $response = Http::withHeaders([
                "Authorization" => "Bearer {$apiKey}",
                "Content-Type"  => "application/json",
            ])
            ->timeout(180)
            ->post("https://api.openai.com/v1/chat/completions", $body);

            if (!$response->successful()) {
                return "❌ خطای GPT: " . $response->body();
            }

            return $response->json()['choices'][0]['message']['content']
                ?? "پاسخی دریافت نشد.";

        } catch (\Exception $e) {
            return "❌ خطا در اتصال به GPT: " . $e->getMessage();
        }
    }

    /* ============================
       مدیریت مکالمات
     ============================ */

    public function getConversation($id)
    {
        $conversation = auth()->user()
            ->conversations()
            ->with(['messages.files'])
            ->findOrFail($id);

        $conversation->messages = $conversation->messages
            ->sortBy('created_at')
            ->values();

        return response()->json($conversation);
    }

    public function deleteConversation($id)
    {
        $conversation = auth()->user()
            ->conversations()
            ->with('messages.files')
            ->findOrFail($id);

        $conversation->delete();
        return response()->json(['success' => true]);
    }

    public function renameConversation(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:100']);

        $conversation = auth()->user()->conversations()->findOrFail($id);
        $conversation->update(['title' => $request->title]);

        return response()->json(['success' => true]);
    }

    public function viewFile(UploadedFile $file)
    {
        $user = auth()->user();

        if ($file->user_id !== $user->id) {
            abort(403);
        }

        $path = storage_path('app/public/' . $file->path);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }
}
