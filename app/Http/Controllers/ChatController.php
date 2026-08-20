<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UploadedFile;
use App\Models\AiModel;
use App\Services\AiApiService;            // برای تست می‌توانید این خط را تغییر دهید (پایین توضیح داده‌ام)
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

    public function index()
    {
        $models = AiModel::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['key', 'name', 'title', 'supports_files']);

        $conversations = auth()->user()
            ->conversations()
            ->with('latestMessage')
            ->orderByDesc('updated_at')
            ->get();

        return view('index', compact('models', 'conversations'));
    }

    public function sendMessage(Request $request)
    {
        Log::info('CHAT REQUEST START', [
            'model' => $request->model,
            'question' => $request->question,
            'has_files' => $request->hasFile('files')
        ]);

        $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'question'        => 'nullable|string',
            'model'           => 'required|string',
            'files.*'         => 'nullable|mimes:jpg,jpeg,png,webp,gif,txt,doc,docx,pdf|max:8192',
        ]);

        $modelKey = $request->model;
        $aiModel = AiModel::where('key', $modelKey)->where('is_active', true)->first();
        if (!$aiModel) {
            return response()->json([
                'error' => 'مدل انتخاب شده نامعتبر یا غیرفعال است.'
            ], 422);
        }

        if ($request->hasFile('files') && !$aiModel->supports_files) {
            return response()->json([
                'error' => "مدل {$aiModel->name} از ارسال فایل پشتیبانی نمی‌کند. لطفاً فقط متن ارسال کنید."
            ], 422);
        }

        $messageText = trim((string) $request->input('question'));

        if ($messageText === '' && !$request->hasFile('files')) {
            return response()->json(['error' => 'لطفاً متن یا فایل ارسال کنید.'], 422);
        }

        $user = auth()->user();

        if (!$user->canAskQuestion()) {
            $sub = $user->activeSubscription;
            if (!$sub || !$sub->isActive()) {
                $plansUrl = route('plans');
                $error = '<div style="line-height:1.8">'
                    . '<span style="font-size:1.1em">سوال رایگان روزانه شما تمام شد.</span><br>'
                    . '<span style="color:#555">فردا می‌توانید دوباره سوال رایگان بپرسید،</span><br>'
                    . '<span style="color:#555">یا همین الان پلن تهیه کنید و بدون محدودیت ادامه دهید:</span><br><br>'
                    . '<a href="' . $plansUrl . '" style="display:inline-block;background:#2563eb;color:#fff;padding:8px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">'
                    . 'مشاهده پلن‌ها و خرید اشتراک'
                    . '</a>'
                    . '</div>';
            } else {
                $plansUrl = route('plans');
                $error = '<a href="' . $plansUrl . '" style="color:#e63946;font-weight:bold;text-decoration:underline;">برای ادامه چت کلیک کنید</a><br>'
                    . '<span style="color:#000">سؤالات مجاز اشتراک شما تمام شده</span><br>'
                    . '<span style="color:#000">وب سایت دکتر حکیمی صدر</span>';
            }
            return response()->json(['error' => $error], 403);
        }

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

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'user',
            'content'         => $messageText,
            'model'           => $modelKey,
        ]);

        $uploadedFileModels = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('chat_files', 'public');
                $uploadedFileModels[] = UploadedFile::create([
                    'message_id'    => $userMessage->id,
                    'user_id'       => $user->id,
                    'path'          => $path,
                    'stored_name'   => basename($path),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        $systemPrompt = (string) $this->systemPromptService->getSystemPrompt();

        $previousChatMessages = $conversation->messages()
            ->where('id', '<', $userMessage->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();

        $subscription = $user->activeSubscription;
        $isFreeQuestion = !$subscription && $user->canUseFreeQuestion();

        return response()->stream(function () use (
            $conversation,
            $messageText,
            $uploadedFileModels,
            $systemPrompt,
            $previousChatMessages,
            $subscription,
            $isFreeQuestion,
            $user,
            $modelKey
        ) {
            if (ob_get_level()) ob_end_clean();
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');

            $assistantAnswer = '';
            $isSuccess = true;

            // 📌 مهم: callback اکنون فقط یک پارامتر می‌گیرد
            try {
                $this->aiApiService->stream(
                    $messageText,
                    $modelKey,
                    $systemPrompt,
                    $previousChatMessages,
                    $uploadedFileModels,
                    function (string $chunk) use (&$assistantAnswer) {
                        $assistantAnswer .= $chunk;
                        echo $chunk;
                        flush();
                    }
                );
            } catch (\Throwable $streamEx) {
                \Illuminate\Support\Facades\Log::error('Stream exception', [
                    'error' => $streamEx->getMessage(),
                    'model' => $modelKey,
                ]);
            }

            if (trim($assistantAnswer) === '') {
                $isSuccess = false;
                $errorMsg = "در حال حاضر پاسخ‌دهی با اختلال موقت روبه‌رو شد. لطفاً چند لحظه بعد دوباره تلاش کنید یا یک مدل دیگر را انتخاب کنید.";
                echo $errorMsg;
                $assistantAnswer .= $errorMsg;
                flush();
            }

            if ($isSuccess) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => auth()->id(),
                    'role'            => 'assistant',
                    'content'         => $assistantAnswer,
                    'model'           => $modelKey,
                ]);

                if ($conversation->title === 'مکالمه جدید' && trim($messageText) !== '') {
                    $conversation->update([
                        'title' => mb_strimwidth($messageText, 0, 60, '...', 'UTF-8')
                    ]);
                }

                $conversation->touch();

                if ($subscription) {
                    $subscription->incrementQuestionsUsed();
                } elseif ($isFreeQuestion) {
                    $user->useFreeQuestion();
                }
            } else {
                $systemMsg = "\n\nاعتباری از حساب شما کسر نشد.";
                echo $systemMsg;
                flush();
            }

        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, must-revalidate',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Conversation-Id' => (string) $conversation->id,
            'Access-Control-Expose-Headers' => 'X-Conversation-Id',
        ]);
    }

    public function getConversation(Request $request, $id)
    {
        try {
            $limit = (int) $request->query('limit', 30);
            $limit = max(1, min($limit, 80));

            $beforeId = $request->query('before_id');
            $beforeId = $beforeId !== null ? (int) $beforeId : null;
            $beforeId = $beforeId > 0 ? $beforeId : null;

            Log::info("🔍 getConversation called", [
                'conversation_id' => $id,
                'auth_user_id' => auth()->id(),
                'limit' => $limit,
                'before_id' => $beforeId,
            ]);

            $conversation = Conversation::find($id);

            if (!$conversation) {
                Log::warning("❌ Conversation not found", ['id' => $id]);
                return response()->json([
                    'error' => 'Conversation پیدا نشد',
                    'code' => 404
                ], 404);
            }

            if ($conversation->user_id !== auth()->id()) {
                Log::warning("⛔ Unauthorized access attempt", [
                    'conversation_user_id' => $conversation->user_id,
                    'auth_user_id' => auth()->id()
                ]);

                return response()->json([
                    'error' => 'شما اجازه دسترسی به این مکالمه را ندارید',
                    'code' => 403
                ], 403);
            }

            $messagePage = $conversation->messages()
                ->with('files')
                ->when($beforeId, fn($query) => $query->where('id', '<', $beforeId))
                ->orderByDesc('id')
                ->limit($limit + 1)
                ->get();

            $hasMore = $messagePage->count() > $limit;

            $messages = $messagePage
                ->take($limit)
                ->sortBy('id')
                ->values();

            $mappedMessages = $messages->map(function ($message) {
                return [
                    'id'      => $message->id,
                    'role'    => $message->role,
                    'content' => $message->content,
                    'files'   => $message->files->map(function ($file) {
                        return [
                            'id'            => $file->id,
                            'original_name' => $file->original_name ?? basename($file->path),
                            'mime_type'     => $file->mime_type,
                        ];
                    })->toArray(),
                ];
            });

            return response()->json([
                'messages' => $mappedMessages,
                'conversation_id' => $conversation->id,
                'has_more' => $hasMore,
                'next_before_id' => $hasMore ? $messages->first()?->id : null,
            ]);

        } catch (\Throwable $e) {
            Log::error("💥 getConversation ERROR", [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'خطای سرور در بارگذاری مکالمه',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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

        if ($file->user_id !== $user->id) abort(403);

        $path = storage_path('app/public/' . $file->path);

        if (!file_exists($path)) abort(404);

        return response()->file($path);
    }

    public function testIranGPUConnection()
    {
        try {
            $client = new Client(['timeout' => 30]);

            $response = $client->get('https://services.irangpu.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.irangpu.key'),
                ]
            ]);

            $models = json_decode($response->getBody(), true);

            $testResponse = $client->post('https://services.irangpu.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.irangpu.key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'openai/gpt-4.1-2025-04-14',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Say "OK" if you receive this']
                    ],
                    'max_tokens' => 10,
                ],
            ]);

            $testResult = json_decode($testResponse->getBody(), true);

            return response()->json([
                'success' => true,
                'message' => '✅ اتصال به IranGPU برقرار است',
                'models' => $models,
                'test_response' => $testResult['choices'][0]['message']['content'] ?? 'No response'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }
}
