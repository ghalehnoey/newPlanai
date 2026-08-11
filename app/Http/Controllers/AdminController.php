<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\SystemPromptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');
        $role   = $request->input('role');

        $query = User::with('activeSubscription.plan')->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->whereHas('subscriptions', fn($q) => $q->where('expires_at', '>', now()));
        } elseif ($status === 'expired') {
            $query->whereHas('subscriptions', fn($q) => $q->where('expires_at', '<=', now()));
        } elseif ($status === 'none') {
            $query->doesntHave('subscriptions');
        }

        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->paginate(20)->appends($request->only(['search', 'status', 'role']));

        $stats = [
            'totalUsers'           => User::count(),
            'activeSubscriptions'  => Subscription::where('expires_at', '>', now())->count(),
            'expiredSubscriptions' => Subscription::where('expires_at', '<=', now())->count(),
            'noSubscriptionUsers'  => User::doesntHave('subscriptions')->count(),
            'totalConversations'   => Conversation::count(),
            'totalMessages'        => Message::count(),
            'newUsersToday'        => User::whereDate('created_at', today())->count(),
            'newUsersThisMonth'    => User::whereMonth('created_at', now()->month)->count(),
        ];

        $plans = Plan::orderBy('price')->get();

        $topUsers = DB::table('users as u')
            ->leftJoin('messages as m', function ($join) {
                $join->on('m.user_id', '=', 'u.id')->where('m.role', '=', 'user');
            })
            ->leftJoin('subscriptions as s', function ($join) {
                $join->on('s.user_id', '=', 'u.id')
                     ->whereRaw('s.id = (SELECT id FROM subscriptions WHERE user_id = u.id ORDER BY id DESC LIMIT 1)');
            })
            ->leftJoin('plans as p', 'p.id', '=', 's.plan_id')
            ->select(
                'u.id',
                'u.name',
                'u.phone',
                DB::raw('COUNT(m.id) as total_messages'),
                DB::raw('COALESCE(s.questions_used, 0) as questions_used'),
                'p.name as plan_name',
                's.expires_at'
            )
            ->groupBy('u.id', 'u.name', 'u.phone', 's.questions_used', 'p.name', 's.expires_at')
            ->orderByDesc('total_messages')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('users', 'stats', 'plans', 'topUsers', 'search', 'status', 'role'));
    }

    public function changePassword(Request $request, User $user)
    {
        $request->validate(['password' => 'required|string|min:6']);
        $user->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function updatePhone(Request $request, User $user)
    {
        $request->validate(['phone' => 'required|string|unique:users,phone,' . $user->id]);
        $user->update(['phone' => $request->phone]);
        return back()->with('success', 'شماره تماس بروزرسانی شد.');
    }

    public function updateRole(Request $request, User $user)
    {
        if (auth()->user()->role !== 'manager') {
            abort(403, 'فقط مدیر کل می‌تواند نقش تغییر دهد');
        }
        $request->validate(['role' => 'required|in:user,manager,modir2']);
        $user->update(['role' => $request->role, 'is_admin' => $request->role === 'manager']);
        return back()->with('success', 'نقش کاربر تغییر کرد.');
    }

    public function updateSubscription(Request $request, User $user)
    {
        $request->validate([
            'plan_id'         => 'required|exists:plans,id',
            'expires_at'      => 'required|date',
            'questions_used'  => 'required|integer|min:0',
        ]);

        $subscription = $user->activeSubscription ?? $user->subscriptions()->latest()->first();

        if ($subscription) {
            $subscription->update([
                'plan_id'        => $request->plan_id,
                'expires_at'     => Carbon::parse($request->expires_at),
                'questions_used' => $request->questions_used,
                'paid'           => 1,
            ]);
        } else {
            Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $request->plan_id,
                'expires_at'     => Carbon::parse($request->expires_at),
                'questions_used' => $request->questions_used,
                'paid'           => 1,
            ]);
        }

        return back()->with('success', 'اشتراک کاربر بروزرسانی شد.');
    }

    public function bulkExtendSubscriptions(Request $request)
    {
        $request->validate(['days' => 'required|integer']);
        $days = (int) $request->days;
        $subs = Subscription::where('expires_at', '>', now())->get();
        foreach ($subs as $sub) {
            $sub->update(['expires_at' => Carbon::parse($sub->expires_at)->addDays($days)]);
        }
        $action = $days > 0 ? 'افزایش' : 'کاهش';
        return back()->with('success', "زمان اشتراک {$subs->count()} کاربر {$action} یافت.");
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|unique:users,phone',
            'email'    => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'in:user,manager,modir2',
        ]);

        User::create([
            'name'           => $request->name,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'role'           => $request->role ?? 'user',
            'is_admin'       => $request->role === 'manager',
            'phone_verified' => true,
        ]);

        return back()->with('success', 'کاربر جدید ایجاد شد.');
    }

    public function deleteUser(User $user)
    {
        if (auth()->user()->role !== 'manager') {
            abort(403);
        }
        if ($user->id === auth()->id()) {
            return back()->with('error', 'نمی‌توانید حساب خودتان را حذف کنید.');
        }
        $user->delete();
        return back()->with('success', 'کاربر حذف شد.');
    }

    public function exportUsers(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');
        $role   = $request->input('role');

        $query = User::with(['activeSubscription.plan'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->whereHas('subscriptions', fn($q) => $q->where('expires_at', '>', now()));
        } elseif ($status === 'expired') {
            $query->whereHas('subscriptions', fn($q) => $q->where('expires_at', '<=', now()));
        } elseif ($status === 'none') {
            $query->doesntHave('subscriptions');
        }

        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->get();

        $filename = 'users_' . now()->format('Ymd_Hi') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'شناسه', 'نام', 'موبایل', 'ایمیل', 'نقش',
                'پلن فعال', 'سؤالات مصرفی', 'سقف سؤالات', 'انقضای اشتراک',
                'تاریخ عضویت',
            ]);
            foreach ($users as $user) {
                $sub = $user->activeSubscription;
                fputcsv($handle, [
                    $user->id,
                    $user->name ?? '',
                    $user->phone ?? '',
                    $user->email ?? '',
                    $user->role ?? 'user',
                    $sub && $sub->plan ? $sub->plan->name : '',
                    $sub ? $sub->questions_used : '',
                    $sub && $sub->plan ? $sub->plan->questions_limit : '',
                    $sub && $sub->expires_at ? $sub->expires_at->format('Y/m/d') : '',
                    $user->created_at ? $user->created_at->format('Y/m/d') : '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function userPurchases(Request $request, User $user)
    {
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->orderBy('created_at')
            ->get();

        $totalMessages = \App\Models\Message::where('user_id', $user->id)
            ->where('role', 'user')
            ->count();

        $activeSubscription = $user->activeSubscription;

        $searchQuery = $request->input('q');
        $searchResults = null;
        if ($searchQuery) {
            $searchResults = User::with('activeSubscription.plan')
                ->where(function ($q) use ($searchQuery) {
                    $q->where('phone', 'like', "%{$searchQuery}%")
                      ->orWhere('name', 'like', "%{$searchQuery}%");
                })
                ->limit(10)
                ->get();
        }

        return view('admin.users.purchases', compact(
            'user', 'subscriptions', 'totalMessages',
            'activeSubscription', 'searchQuery', 'searchResults'
        ));
    }

    /* ═══════════════ AI MODELS (manager only) ═══════════════ */

    public function aiModelsIndex()
    {
        $models = AiModel::orderBy('sort_order')->get();
        return view('admin.ai_models.index', compact('models'));
    }

    public function aiModelsCreate()
    {
        return view('admin.ai_models.create');
    }

    public function aiModelsStore(Request $request)
    {
        $validated = $request->validate([
            'key'             => 'required|string|unique:ai_models,key',
            'name'            => 'required|string|max:255',
            'title'           => 'nullable|string|max:255',
            'provider'        => 'required|string|in:gapgpt,irangpu,deepseek,openai,custom',
            'endpoint'        => 'required|url',
            'api_key'         => 'nullable|string',
            'model_mapping'   => 'required|string|max:255',
            'supports_files'  => 'boolean',
            'is_active'       => 'boolean',
            'use_proxy'       => 'boolean',
            'proxy_url'       => 'nullable|string|max:500',
            'timeout_seconds' => 'nullable|integer|min:10|max:300',
            'sort_order'      => 'integer',
        ]);

        $validated['supports_files']  = $request->has('supports_files');
        $validated['is_active']       = $request->has('is_active');
        $validated['use_proxy']       = $request->has('use_proxy');
        $validated['proxy_url']       = $request->input('proxy_url') ?: null;
        $validated['timeout_seconds'] = (int) $request->input('timeout_seconds', 120);

        AiModel::create($validated);
        return redirect()->route('admin.ai-models.index')->with('success', 'مدل جدید اضافه شد.');
    }

    public function aiModelsEdit(AiModel $aiModel)
    {
        return view('admin.ai_models.edit', compact('aiModel'));
    }

    public function aiModelsUpdate(Request $request, AiModel $aiModel)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'title'           => 'nullable|string|max:255',
            'endpoint'        => 'required|url',
            'api_key'         => 'nullable|string',
            'model_mapping'   => 'required|string|max:255',
            'supports_files'  => 'boolean',
            'is_active'       => 'boolean',
            'use_proxy'       => 'boolean',
            'proxy_url'       => 'nullable|string|max:500',
            'timeout_seconds' => 'nullable|integer|min:10|max:300',
            'sort_order'      => 'integer',
        ]);

        $validated['supports_files']  = $request->has('supports_files');
        $validated['is_active']       = $request->has('is_active');
        $validated['use_proxy']       = $request->has('use_proxy');
        $validated['proxy_url']       = $request->input('proxy_url') ?: null;
        $validated['timeout_seconds'] = (int) $request->input('timeout_seconds', 120);

        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $aiModel->update($validated);
        return redirect()->route('admin.ai-models.index')->with('success', 'مدل بروزرسانی شد.');
    }

    public function aiModelsDestroy(AiModel $aiModel)
    {
        $aiModel->delete();
        return redirect()->route('admin.ai-models.index')->with('success', 'مدل حذف شد.');
    }

    /* ═══════════════ CHATBOT SETTINGS (manager only) ═══════════════ */

    public function chatbotSettings(SystemPromptService $svc)
    {
        return view('admin.chatbot_settings', [
            'currentPrompt'    => $svc->getSystemPrompt(),
            'defaultPrompt'    => $svc->getDefaultPrompt(),
            'topicRestriction' => $svc->getTopicRestriction(),
        ]);
    }

    public function chatbotSettingsSave(Request $request, SystemPromptService $svc)
    {
        $request->validate([
            'system_prompt'     => 'required|string|min:10',
            'topic_restriction' => 'nullable|string|max:5000',
        ]);

        $svc->save($request->input('system_prompt'));
        $svc->saveTopicRestriction($request->input('topic_restriction', ''));

        return redirect()->route('admin.chatbot-settings')->with('success', 'تنظیمات چت‌بات ذخیره شد.');
    }

    public function chatbotSettingsReset(SystemPromptService $svc)
    {
        $svc->save($svc->getDefaultPrompt());
        return redirect()->route('admin.chatbot-settings')->with('success', 'پرامپت به حالت پیش‌فرض بازگشت.');
    }

    /* ═══════════════ STATS & SYSTEM ═══════════════ */

    public function stats()
    {
        $data = [
            'usersByMonth'   => User::selectRaw('MONTH(created_at) as m, COUNT(*) as cnt')
                ->whereYear('created_at', now()->year)->groupBy('m')->orderBy('m')->get(),
            'messagesByDay'  => Message::selectRaw('DATE(created_at) as d, COUNT(*) as cnt')
                ->where('created_at', '>=', now()->subDays(30))->groupBy('d')->orderBy('d')->get(),
            'topPlan'        => Plan::withCount('subscriptions')->orderByDesc('subscriptions_count')->first(),
        ];
        return view('admin.stats', compact('data'));
    }


    public function repeatPurchasersExport()
    {
        $rows = DB::table('subscriptions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.paid', 1)
            ->select(
                'u.id as user_id',
                'u.name',
                'u.phone',
                'u.email',
                's.id as sub_id',
                'p.name as plan_name',
                'p.price',
                's.created_at as purchased_at',
                's.expires_at',
                's.zibal_track_id'
            )
            ->orderBy('u.id')
            ->orderBy('s.created_at')
            ->get();

        $counts = $rows->groupBy('user_id')->filter(fn($g) => $g->count() > 1)->keys();
        $filtered = $rows->filter(fn($r) => $counts->contains($r->user_id));

        $filename = 'repeat_purchasers_' . now()->format('Ymd_Hi') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($filtered) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['شناسه کاربر', 'نام', 'موبایل', 'ایمیل', 'پلن', 'مبلغ (تومان)', 'تاریخ خرید', 'انقضا', 'شناسه زیبال']);
            foreach ($filtered as $row) {
                fputcsv($handle, [
                    $row->user_id,
                    $row->name,
                    $row->phone,
                    $row->email ?? '',
                    $row->plan_name,
                    number_format($row->price),
                    $row->purchased_at,
                    $row->expires_at,
                    $row->zibal_track_id ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function systemSettings()
    {
        $dailyFreeQuestions = (int) DB::table('settings')
            ->where('key', 'daily_free_questions')
            ->value('value') ?? 1;

        return view('admin.system', compact('dailyFreeQuestions'));
    }

    public function systemSettingsSave(Request $request)
    {
        $request->validate([
            'daily_free_questions' => 'required|integer|min:0|max:100',
        ]);

        DB::table('settings')->updateOrInsert(
            ['key' => 'daily_free_questions'],
            ['value' => $request->daily_free_questions, 'updated_at' => now()]
        );

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }

    public function aiTest()
    {
        $models = \App\Models\AiModel::orderBy('sort_order')->get();
        return view('admin.ai_test', compact('models'));
    }

    public function aiTestRun(\Illuminate\Http\Request $request)
    {
        $modelKey  = $request->input('model_key');
        $useProxy  = (bool) $request->input('use_proxy', false);
        $proxyUrl  = trim($request->input('proxy_url', 'socks5h://127.0.0.1:1080'));
        $question  = trim($request->input('question', 'سلام، یک جمله کوتاه به فارسی بگو.'));

        $model = \App\Models\AiModel::where('key', $modelKey)->first();
        if (!$model) {
            return response()->json(['ok' => false, 'error' => 'مدل یافت نشد']);
        }

        $apiKey = $model->api_key ?: match (strtolower((string) $model->provider)) {
            'openai'   => config('services.openai.key'),
            'irangpu'  => config('services.irangpu.key'),
            'gapgpt'   => config('services.gapgpt.key'),
            'deepseek' => config('services.deepseek.key'),
            default    => env('OPENAI_API_KEY'),
        };
        $endpoint = $model->endpoint;

        $isNewOpenAi = str_contains($model->endpoint, 'openai.com') 
            && preg_match('/gpt-[45]|o[13]-|o1$/', $model->model_mapping);
        $body = json_encode(
            $isNewOpenAi
                ? ['model' => $model->model_mapping, 'messages' => [['role' => 'user', 'content' => $question]], 'max_completion_tokens' => 150]
                : ['model' => $model->model_mapping, 'messages' => [['role' => 'user', 'content' => $question]], 'max_tokens' => 500],
            JSON_UNESCAPED_UNICODE
        );

        $ch = curl_init($endpoint);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($useProxy && $proxyUrl !== '') {
            $opts[CURLOPT_PROXY]     = $proxyUrl;
            $opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
        }
        curl_setopt_array($ch, $opts);

        $t0       = microtime(true);
        $response = curl_exec($ch);
        $elapsed  = round((microtime(true) - $t0) * 1000);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ip       = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($errno) {
            $diagnosis = 'اتصال برقرار نشد';
            if (str_contains($error, 'refused'))  $diagnosis = 'Proxy در دسترس نیست (Connection refused)';
            if (str_contains($error, 'timed out')) $diagnosis = 'Timeout — سرور پاسخ نداد';
            if (str_contains($error, 'Could not resolve')) $diagnosis = 'DNS resolve ناموفق';
            return response()->json([
                'ok' => false, 'http' => 0, 'elapsed_ms' => $elapsed,
                'error' => $error, 'diagnosis' => $diagnosis, 'server_ip' => '',
            ]);
        }

        $data    = json_decode($response, true);
        $answer  = $data['choices'][0]['message']['content'] ?? null;
        $usage   = $data['usage'] ?? null;

        // credit check برای OpenAI
        $credit = null;
        if (str_contains($endpoint, 'openai.com') && $apiKey) {
            $chc = curl_init('https://api.openai.com/v1/dashboard/billing/credit_grants');
            $creditOpts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => 10,
            ];
            if ($useProxy && $proxyUrl !== '') {
                $creditOpts[CURLOPT_PROXY]     = $proxyUrl;
                $creditOpts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
            }
            curl_setopt_array($chc, $creditOpts);
            $cr = json_decode(curl_exec($chc), true);
            curl_close($chc);
            if (isset($cr['total_available'])) {
                $credit = '$' . number_format($cr['total_available'], 2);
            } elseif (isset($cr['error'])) {
                $credit = 'API: ' . ($cr['error']['message'] ?? 'خطا');
            }
        }

        $apiError = null;
        if (is_string($response) && $response !== '') {
            $parsed = json_decode($response, true);
            $apiError = $parsed['error']['message'] ?? $parsed['error']['code'] ?? null;
        }

        if ($httpCode === 401) {
            return response()->json([
                'ok' => false, 'http' => $httpCode, 'elapsed_ms' => $elapsed,
                'error' => 'API Key نامعتبر است (401 Unauthorized)', 'server_ip' => $ip,
                'diagnosis' => 'کلید API اشتباه یا منقضی شده',
            ]);
        }
        if ($httpCode === 429) {
            return response()->json([
                'ok' => false, 'http' => $httpCode, 'elapsed_ms' => $elapsed,
                'error' => 'Rate limit یا اعتبار تمام شده (429)', 'server_ip' => $ip,
                'diagnosis' => 'اعتبار API تمام شده یا درخواست زیاد است',
            ]);
        }

        $diagnosis = $httpCode === 200 ? 'اتصال و API هر دو سالم هستند ✓' : 'خطای HTTP ' . $httpCode;

        return response()->json([
            'ok'         => $httpCode === 200 && $answer !== null,
            'http'       => $httpCode,
            'elapsed_ms' => $elapsed,
            'answer'     => $answer,
            'usage'      => $usage,
            'credit'     => $credit,
            'server_ip'  => $ip,
            'diagnosis'  => $diagnosis,
            'api_error'  => $apiError,
        ]);
    }

}
