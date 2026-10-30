<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\AiModel;
use App\Models\AiProxyServer;
use App\Models\AiUsageLog;
use App\Models\AiFailureLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\SystemPromptService;
use App\Services\AiApiService;
use App\Services\AiProxyRouter;
use App\Services\RemoteProxyProvisioner;
use App\Services\ConversationMemoryService;
use App\Services\SmartModelRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

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

        $stats = Cache::remember('admin:dashboard:stats', 60, static function (): array {
            $today = now()->startOfDay();
            $tomorrow = $today->copy()->addDay();
            $monthStart = $today->copy()->startOfMonth();
            $nextMonth = $monthStart->copy()->addMonth();

            return [
                'totalUsers'           => User::count(),
                'activeSubscriptions'  => Subscription::where('expires_at', '>', now())->count(),
                'expiredSubscriptions' => Subscription::where('expires_at', '<=', now())->count(),
                'noSubscriptionUsers'  => User::doesntHave('subscriptions')->count(),
                'totalConversations'   => Conversation::count(),
                'totalMessages'        => Message::count(),
                'newUsersToday'        => User::where('created_at', '>=', $today)->where('created_at', '<', $tomorrow)->count(),
                'newUsersThisMonth'    => User::where('created_at', '>=', $monthStart)->where('created_at', '<', $nextMonth)->count(),
            ];
        });

        $plans = Cache::remember('admin:dashboard:plans', 300, static fn () => Plan::orderBy('price')->get());
        $aiFailureSummary = Cache::remember('admin:ai-failure-summary', 60, static function (): array {
            $since = now()->subDay();
            return [
                'count' => AiFailureLog::where('created_at', '>=', $since)->count(),
                'categories' => AiFailureLog::where('created_at', '>=', $since)
                    ->selectRaw('category, COUNT(*) as total')
                    ->groupBy('category')->orderByDesc('total')->limit(4)->get(),
            ];
        });
        $financialMonth = null;
        $aiDailyUsage = null;
        if (auth()->user()->role === 'manager') {
            $aiDailyUsage = Cache::remember('admin:ai-daily-usage', 60, static function (): array {
                $today = now('Asia/Tehran')->startOfDay();
                $tomorrow = $today->copy()->addDay();
                $yesterday = $today->copy()->subDay();
                $usage = AiUsageLog::where('created_at', '>=', $today->copy()->utc())
                    ->where('created_at', '<', $tomorrow->copy()->utc())
                    ->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(estimated_cost_usd), 0) as cost_usd')
                    ->first();
                $yesterdayCost = (float) AiUsageLog::where('created_at', '>=', $yesterday->copy()->utc())
                    ->where('created_at', '<', $today->copy()->utc())
                    ->sum('estimated_cost_usd');
                $storedBudget = DB::table('settings')->where('key', 'ai_daily_budget_usd')->value('value');
                $modelBudget = (float) AiModel::where('is_active', true)
                    ->whereNotNull('daily_cost_limit_usd')
                    ->sum('daily_cost_limit_usd');
                $budget = max(0, (float) ($storedBudget ?? $modelBudget));
                $cost = (float) ($usage->cost_usd ?? 0);
                $percentage = $budget > 0 ? ($cost / $budget) * 100 : 0;

                return [
                    'cost_usd' => $cost,
                    'budget_usd' => $budget,
                    'percentage' => $percentage,
                    'bar_percentage' => min(100, $percentage),
                    'requests' => (int) ($usage->requests ?? 0),
                    'tokens' => (int) ($usage->tokens ?? 0),
                    'yesterday_cost_usd' => $yesterdayCost,
                    'date' => Jalalian::fromCarbon($today)->format('Y/m/d'),
                ];
            });
            $financialMonth = Cache::remember('admin:financial-current-jalali', 300, static function (): array {
                $jalaliNow = Jalalian::fromCarbon(now('Asia/Tehran'));
                $jalaliStart = new Jalalian($jalaliNow->getYear(), $jalaliNow->getMonth(), 1);
                $jalaliNext = $jalaliStart->addMonths(1);
                $startUtc = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $jalaliStart->toCarbon()->format('Y-m-d') . ' 00:00:00',
                    'Asia/Tehran'
                )->utc();
                $endUtc = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $jalaliNext->toCarbon()->format('Y-m-d') . ' 00:00:00',
                    'Asia/Tehran'
                )->utc();

                $sales = DB::table('subscriptions as s')
                    ->leftJoin('plans as p', 'p.id', '=', 's.plan_id')
                    ->where('s.paid', true)
                    ->whereNotNull('s.zibal_track_id')
                    ->where('s.created_at', '>=', $startUtc)
                    ->where('s.created_at', '<', $endUtc)
                    ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(COALESCE(s.paid_amount_toman, p.price)), 0) as total_toman')
                    ->first();
                $ai = AiUsageLog::where('created_at', '>=', $startUtc)
                    ->where('created_at', '<', $endUtc)
                    ->selectRaw('COALESCE(SUM(estimated_cost_usd), 0) as cost_usd, COALESCE(SUM(total_tokens), 0) as tokens')
                    ->first();
                $usdRate = max(0, (float) (DB::table('settings')->where('key', 'usd_to_toman_rate')->value('value') ?? 0));
                $aiCostUsd = (float) ($ai->cost_usd ?? 0);
                $aiCostToman = $usdRate > 0 ? $aiCostUsd * $usdRate : null;
                $salesToman = (int) ($sales->total_toman ?? 0);
                $trackingStartedAt = AiUsageLog::min('created_at');
                $trackingStartedJalali = $trackingStartedAt
                    ? Jalalian::fromCarbon(Carbon::parse($trackingStartedAt)->setTimezone('Asia/Tehran'))->format('Y/m/d H:i')
                    : null;

                return [
                    'label' => $jalaliStart->format('F Y'),
                    'sales_count' => (int) ($sales->total_count ?? 0),
                    'sales_toman' => $salesToman,
                    'ai_cost_usd' => $aiCostUsd,
                    'ai_cost_toman' => $aiCostToman,
                    'net_toman' => $aiCostToman !== null ? $salesToman - $aiCostToman : null,
                    'tokens' => (int) ($ai->tokens ?? 0),
                    'usd_rate' => $usdRate,
                    'ai_tracking_from' => $trackingStartedJalali,
                    'from' => $jalaliStart->format('Y/m/d'),
                    'to' => $jalaliNext->format('Y/m/d'),
                ];
            });
        }

        return view('admin.dashboard', compact('users', 'stats', 'plans', 'search', 'status', 'role', 'aiFailureSummary', 'financialMonth', 'aiDailyUsage'));
    }

    public function topUsers()
    {
        $topUsers = Cache::remember('admin:top-users', 60, function () {
            $messageCounts = DB::table('messages')
                ->select('user_id', DB::raw('COUNT(*) as total_messages'))
                ->where('role', 'user')
                ->groupBy('user_id');
            $latestSubscriptionIds = DB::table('subscriptions')
                ->select('user_id', DB::raw('MAX(id) as subscription_id'))
                ->groupBy('user_id');

            return DB::table('users as u')
                ->leftJoinSub($messageCounts, 'mc', 'mc.user_id', '=', 'u.id')
                ->leftJoinSub($latestSubscriptionIds, 'latest_s', 'latest_s.user_id', '=', 'u.id')
                ->leftJoin('subscriptions as s', 's.id', '=', 'latest_s.subscription_id')
                ->leftJoin('plans as p', 'p.id', '=', 's.plan_id')
                ->select(
                    'u.id', 'u.name', 'u.phone',
                    DB::raw('COALESCE(mc.total_messages, 0) as total_messages'),
                    DB::raw('COALESCE(s.questions_used, 0) as questions_used'),
                    'p.name as plan_name', 's.expires_at'
                )
                ->orderByDesc('total_messages')
                ->limit(10)
                ->get();
        });

        return response()->json([
            'ok' => true,
            'html' => view('admin.partials.top_users_rows', compact('topUsers'))->render(),
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        if (auth()->user()?->phone === '09155711123' && $user->role === 'manager') {
            abort(403, 'شما اجازه تغییر رمز حساب‌های مدیر کل را ندارید.');
        }

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
        $models = AiModel::with(['proxyServer', 'fallbackModel'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('sort_order')
            ->get();
        $usageToday = AiUsageLog::where('created_at', '>=', now('Asia/Tehran')->startOfDay()->utc())
            ->selectRaw('used_model, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost')
            ->groupBy('used_model')->get()->keyBy('used_model');
        return view('admin.ai_models.index', compact('models', 'usageToday'));
    }

    public function aiModelsCreate()
    {
        $proxyServers = AiProxyServer::orderBy('priority')->orderBy('name')->get();
        $fallbackModels = AiModel::orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.ai_models.create', compact('proxyServers', 'fallbackModels'));
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
            'proxy_mode'      => 'required|in:direct,auto,proxy',
            'proxy_server_id' => 'nullable|exists:ai_proxy_servers,id',
            'timeout_seconds' => 'nullable|integer|min:10|max:300',
            'max_output_tokens' => 'nullable|integer|min:100|max:100000',
            'sort_order'      => 'integer',
            'input_price_per_million' => 'nullable|numeric|min:0|max:100000',
            'cached_input_price_per_million' => 'nullable|numeric|min:0|max:100000',
            'output_price_per_million' => 'nullable|numeric|min:0|max:100000',
            'daily_cost_limit_usd' => 'nullable|numeric|min:0|max:1000000',
            'daily_token_limit' => 'nullable|integer|min:0|max:1000000000000',
            'fallback_model_id' => 'nullable|exists:ai_models,id',
        ]);

        $validated['supports_files']  = $request->has('supports_files');
        $validated['is_active']       = $request->has('is_active');
        $validated['use_proxy']       = $validated['proxy_mode'] === 'proxy';
        $validated['proxy_url']       = $request->input('proxy_url') ?: null;
        $validated['timeout_seconds'] = (int) $request->input('timeout_seconds', 120);
        $validated['max_output_tokens'] = (int) $request->input('max_output_tokens', 0) ?: null;
        $validated['input_price_per_million'] = (float) $request->input('input_price_per_million', 0);
        $validated['cached_input_price_per_million'] = (float) $request->input('cached_input_price_per_million', 0);
        $validated['output_price_per_million'] = (float) $request->input('output_price_per_million', 0);
        $validated['daily_cost_limit_usd'] = (float) $request->input('daily_cost_limit_usd', 0) ?: null;
        $validated['daily_token_limit'] = (int) $request->input('daily_token_limit', 0) ?: null;
        $validated['fallback_model_id'] = $request->input('fallback_model_id') ?: null;
        if ($validated['daily_cost_limit_usd'] && $validated['input_price_per_million'] <= 0 && $validated['output_price_per_million'] <= 0) {
            return back()->withInput()->withErrors(['daily_cost_limit_usd' => 'برای سقف دلاری ابتدا تعرفه ورودی یا خروجی مدل را وارد کنید.']);
        }

        AiModel::create($validated);
        return redirect()->route('admin.ai-models.index')->with('success', 'مدل جدید اضافه شد.');
    }

    public function aiModelsEdit(AiModel $aiModel)
    {
        $proxyServers = AiProxyServer::orderBy('priority')->orderBy('name')->get();
        $fallbackModels = AiModel::where('id', '!=', $aiModel->id)
            ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.ai_models.edit', compact('aiModel', 'proxyServers', 'fallbackModels'));
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
            'proxy_mode'      => 'required|in:direct,auto,proxy',
            'proxy_server_id' => 'nullable|exists:ai_proxy_servers,id',
            'timeout_seconds' => 'nullable|integer|min:10|max:300',
            'max_output_tokens' => 'nullable|integer|min:100|max:100000',
            'sort_order'      => 'integer',
            'input_price_per_million' => 'nullable|numeric|min:0|max:100000',
            'cached_input_price_per_million' => 'nullable|numeric|min:0|max:100000',
            'output_price_per_million' => 'nullable|numeric|min:0|max:100000',
            'daily_cost_limit_usd' => 'nullable|numeric|min:0|max:1000000',
            'daily_token_limit' => 'nullable|integer|min:0|max:1000000000000',
            'fallback_model_id' => 'nullable|exists:ai_models,id|not_in:' . $aiModel->id,
        ]);

        $validated['supports_files']  = $request->has('supports_files');
        $validated['is_active']       = $request->has('is_active');
        $validated['use_proxy']       = $validated['proxy_mode'] === 'proxy';
        $validated['proxy_url']       = $request->input('proxy_url') ?: null;
        $validated['timeout_seconds'] = (int) $request->input('timeout_seconds', 120);
        $validated['max_output_tokens'] = (int) $request->input('max_output_tokens', 0) ?: null;
        $validated['input_price_per_million'] = (float) $request->input('input_price_per_million', 0);
        $validated['cached_input_price_per_million'] = (float) $request->input('cached_input_price_per_million', 0);
        $validated['output_price_per_million'] = (float) $request->input('output_price_per_million', 0);
        $validated['daily_cost_limit_usd'] = (float) $request->input('daily_cost_limit_usd', 0) ?: null;
        $validated['daily_token_limit'] = (int) $request->input('daily_token_limit', 0) ?: null;
        $validated['fallback_model_id'] = $request->input('fallback_model_id') ?: null;
        if ($validated['daily_cost_limit_usd'] && $validated['input_price_per_million'] <= 0 && $validated['output_price_per_million'] <= 0) {
            return back()->withInput()->withErrors(['daily_cost_limit_usd' => 'برای سقف دلاری ابتدا تعرفه ورودی یا خروجی مدل را وارد کنید.']);
        }

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

    public function aiProxyServersIndex()
    {
        $proxyServers = AiProxyServer::withCount('aiModels')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return view('admin.ai_proxies.index', compact('proxyServers'));
    }

    public function aiProxyServersStore(Request $request)
    {
        $validated = $this->validateProxyServer($request);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['password'] = $request->filled('password') ? $request->input('password') : null;
        $validated['ssh_password'] = $request->filled('ssh_password') ? $request->input('ssh_password') : null;
        AiProxyServer::create($validated);

        return back()->with('success', 'پراکسی جدید ذخیره شد. پیش از اتصال مدل، تست سلامت را اجرا کنید.');
    }

    public function aiProxyServersUpdate(Request $request, AiProxyServer $aiProxyServer)
    {
        $validated = $this->validateProxyServer($request);
        $validated['is_active'] = $request->boolean('is_active');
        $hostChanged = $aiProxyServer->host !== $validated['host']
            || (int) $aiProxyServer->ssh_port !== (int) $validated['ssh_port'];

        if ($request->boolean('clear_password')) {
            $validated['password'] = null;
        } elseif ($request->filled('password')) {
            $validated['password'] = $request->input('password');
        } else {
            unset($validated['password']);
        }

        if ($request->boolean('clear_ssh_password')) {
            $validated['ssh_password'] = null;
        } elseif ($request->filled('ssh_password')) {
            $validated['ssh_password'] = $request->input('ssh_password');
        } else {
            unset($validated['ssh_password']);
        }

        if ($hostChanged) {
            $validated['ssh_host_fingerprint'] = null;
            $validated['provision_status'] = 'not_started';
            $validated['provision_message'] = 'آدرس سرور تغییر کرده است؛ provisioning را دوباره اجرا کنید.';
        }

        $aiProxyServer->update($validated);

        return back()->with('success', 'تنظیمات پراکسی به‌روزرسانی شد.');
    }

    public function aiProxyServersDestroy(AiProxyServer $aiProxyServer)
    {
        $aiProxyServer->delete();

        return back()->with('success', 'پراکسی حذف شد و اتصال مدل‌های مرتبط آزاد شد.');
    }

    public function aiProxyServersTest(AiProxyServer $aiProxyServer, AiProxyRouter $proxyRouter)
    {
        return response()->json($proxyRouter->testProxy($aiProxyServer));
    }

    public function aiProxyServersProvision(AiProxyServer $aiProxyServer, RemoteProxyProvisioner $provisioner)
    {
        try {
            return response()->json($provisioner->provision($aiProxyServer));
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /** @return array<string, mixed> */
    private function validateProxyServer(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'scheme' => 'required|in:socks5h,socks5,http,https',
            'host' => ['required', 'string', 'max:255', 'regex:/^(?!https?:\/\/|socks5h?:\/\/)[A-Za-z0-9._:-]+$/'],
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'priority' => 'required|integer|min:1|max:10000',
            'connect_timeout' => 'required|integer|min:3|max:60',
            'notes' => 'nullable|string|max:2000',
            'clear_password' => 'boolean',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'ssh_username' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/'],
            'ssh_password' => 'nullable|string|max:1000',
            'clear_ssh_password' => 'boolean',
            'allowed_source_ip' => 'required|ip',
        ]);
    }

    public function chatbotSettings(SystemPromptService $svc)
    {
        return view('admin.chatbot_settings', [
            'currentPrompt'    => $svc->getSystemPrompt(),
            'defaultPrompt'    => $svc->getDefaultPrompt(),
            'topicRestriction' => $svc->getTopicRestriction(),
            'defaultMaxOutputTokens' => max(100, min(100000, (int) (DB::table('settings')
                ->where('key', 'ai_default_max_output_tokens')
                ->value('value') ?? config('services.openai.max_completion_tokens', 4000)))),
        ]);
    }

    public function chatbotSettingsSave(Request $request, SystemPromptService $svc)
    {
        $request->validate([
            'system_prompt'     => 'required|string|min:10',
            'topic_restriction' => 'nullable|string|max:5000',
            'ai_default_max_output_tokens' => 'required|integer|min:100|max:100000',
        ]);

        $svc->save($request->input('system_prompt'));
        $svc->saveTopicRestriction((string) ($request->input('topic_restriction') ?? ''));
        DB::table('settings')->updateOrInsert(
            ['key' => 'ai_default_max_output_tokens'],
            ['value' => (string) (int) $request->input('ai_default_max_output_tokens'), 'updated_at' => now()],
        );
        Cache::forget('ai_default_max_output_tokens');

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

    public function systemSettings(
        ConversationMemoryService $conversationMemoryService,
        SmartModelRouter $smartModelRouter,
    )
    {
        $dailyFreeQuestions = (int) DB::table('settings')
            ->where('key', 'daily_free_questions')
            ->value('value') ?? 1;
        $usdToTomanRate = (float) DB::table('settings')
            ->where('key', 'usd_to_toman_rate')
            ->value('value') ?? 0;
        $storedAiDailyBudget = DB::table('settings')
            ->where('key', 'ai_daily_budget_usd')
            ->value('value');
        $aiDailyBudgetUsd = max(0, (float) ($storedAiDailyBudget ?? AiModel::where('is_active', true)
            ->whereNotNull('daily_cost_limit_usd')
            ->sum('daily_cost_limit_usd')));
        $memorySettings = $conversationMemoryService->settings();
        $memoryStats = DB::table('conversation_memories')
            ->selectRaw('COUNT(*) as conversations, COALESCE(SUM(estimated_tokens), 0) as estimated_tokens')
            ->first();

        $smartRoutingSettings = $smartModelRouter->settings();
        $smartRoutingModel = AiModel::where('key', SmartModelRouter::TARGET_MODEL_KEY)->first();
        $smartRoutingStats = AiUsageLog::query()
            ->where('smart_routed', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(estimated_cost_usd), 0) as cost_usd')
            ->first();

        return view('admin.system', compact(
            'dailyFreeQuestions', 'usdToTomanRate', 'aiDailyBudgetUsd', 'memorySettings', 'memoryStats',
            'smartRoutingSettings', 'smartRoutingModel', 'smartRoutingStats',
        ));
    }

    public function systemSettingsSave(
        Request $request,
        ConversationMemoryService $conversationMemoryService,
        SmartModelRouter $smartModelRouter,
    )
    {
        $request->validate([
            'daily_free_questions' => 'nullable|integer|min:0|max:100',
            'ai_history_message_limit' => 'nullable|integer|min:0|max:80',
            'usd_to_toman_rate' => 'nullable|numeric|min:0|max:100000000',
            'ai_daily_budget_usd' => 'nullable|numeric|min:0|max:1000000',
            'ai_memory_enabled' => 'nullable|boolean',
            'ai_memory_recent_turns' => 'nullable|integer|min:0|max:20',
            'ai_memory_previous_conversations' => 'nullable|integer|min:0|max:10',
            'ai_memory_summary_max_characters' => 'nullable|integer|min:600|max:12000',
            'ai_memory_cross_chat_max_characters' => 'nullable|integer|min:0|max:10000',
            'ai_memory_context_token_budget' => 'nullable|integer|min:800|max:30000',
            'ai_memory_recent_message_max_characters' => 'nullable|integer|min:500|max:12000',
            'ai_memory_source_message_limit' => 'nullable|integer|min:20|max:500',
            'ai_memory_reuse_files_limit' => 'nullable|integer|min:0|max:3',
            'ai_memory_file_reuse_turns' => 'nullable|integer|min:0|max:20',
            'ai_smart_routing_enabled' => 'nullable|boolean',
            'ai_smart_routing_max_characters' => 'nullable|integer|min:200|max:4000',
        ]);
        if (!$request->hasAny(array_merge(
            ['daily_free_questions', 'ai_history_message_limit', 'usd_to_toman_rate', 'ai_daily_budget_usd'],
            ConversationMemoryService::SETTING_KEYS,
            SmartModelRouter::SETTING_KEYS,
        ))) {
            return back()->withErrors(['settings' => 'هیچ تنظیمی برای ذخیره ارسال نشده است.']);
        }

        if ($request->filled('daily_free_questions') || $request->input('daily_free_questions') === '0') {
            DB::table('settings')->updateOrInsert(
                ['key' => 'daily_free_questions'],
                ['value' => $request->daily_free_questions, 'updated_at' => now()]
            );
            Cache::forget('daily_free_questions');
        }
        if ($request->filled('ai_history_message_limit') || $request->input('ai_history_message_limit') === '0') {
            DB::table('settings')->updateOrInsert(
                ['key' => 'ai_history_message_limit'],
                ['value' => $request->ai_history_message_limit, 'updated_at' => now()]
            );
            Cache::forget('ai_history_message_limit');
        }
        if ($request->filled('usd_to_toman_rate') || $request->input('usd_to_toman_rate') === '0') {
            DB::table('settings')->updateOrInsert(
                ['key' => 'usd_to_toman_rate'],
                ['value' => $request->usd_to_toman_rate, 'updated_at' => now()]
            );
            Cache::forget('admin:financial-current-jalali');
        }
        if ($request->filled('ai_daily_budget_usd') || $request->input('ai_daily_budget_usd') === '0') {
            DB::table('settings')->updateOrInsert(
                ['key' => 'ai_daily_budget_usd'],
                ['value' => (string) (float) $request->input('ai_daily_budget_usd'), 'updated_at' => now()]
            );
            Cache::forget('admin:ai-daily-usage');
        }

        foreach (ConversationMemoryService::SETTING_KEYS as $key) {
            if (!$request->exists($key)) {
                continue;
            }
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) (int) $request->input($key, 0), 'updated_at' => now()],
            );
        }
        $conversationMemoryService->forgetSettingsCache();

        foreach (SmartModelRouter::SETTING_KEYS as $key) {
            if (!$request->exists($key)) {
                continue;
            }
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) (int) $request->input($key, 0), 'updated_at' => now()],
            );
        }
        $smartModelRouter->forgetSettingsCache();

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }

    public function resetConversationMemories()
    {
        $count = DB::table('conversation_memories')->delete();

        return back()->with('success', number_format($count) . ' حافظه فشرده پاک شد؛ پیام‌های اصلی حذف نشدند و حافظه‌ها هنگام استفاده بعدی بازسازی می‌شوند.');
    }

    public function aiUsage(Request $request)
    {
        $request->validate([
            'from' => 'nullable|string|max:10',
            'to' => 'nullable|string|max:10',
            'model' => 'nullable|string|max:100',
        ]);
        $timezone = 'Asia/Tehran';
        $defaultTo = now($timezone);
        $to = $this->parseReportDate($request->input('to'), $defaultTo, 'to')->endOfDay();
        $from = $this->parseReportDate($request->input('from'), $to->copy()->subDays(13), 'from')->startOfDay();
        if ($from->greaterThan($to)) {
            $from = $to->copy()->subDays(13)->startOfDay();
        } elseif ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365)->startOfDay();
        }

        $base = AiUsageLog::query()
            ->where('created_at', '>=', $from->copy()->utc())
            ->where('created_at', '<=', $to->copy()->utc());
        if ($request->filled('model')) {
            $base->where('used_model', $request->string('model')->toString());
        }

        $summary = (clone $base)->selectRaw(
            'COUNT(*) as requests, COALESCE(SUM(input_tokens),0) as input_tokens, COALESCE(SUM(cached_input_tokens),0) as cached_input_tokens, COALESCE(SUM(output_tokens),0) as output_tokens, COALESCE(SUM(reasoning_tokens),0) as reasoning_tokens, COALESCE(SUM(total_tokens),0) as total_tokens, COALESCE(SUM(estimated_cost_usd),0) as cost_usd'
        )->first();
        $byModel = (clone $base)->selectRaw(
            'used_model, model_mapping, COUNT(*) as requests, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(total_tokens) as total_tokens, SUM(estimated_cost_usd) as cost_usd'
        )->groupBy('used_model', 'model_mapping')->orderByDesc('cost_usd')->get();
        $byDay = (clone $base)->selectRaw(
            'DATE(DATE_ADD(created_at, INTERVAL 210 MINUTE)) as usage_date, COUNT(*) as requests, SUM(total_tokens) as total_tokens, SUM(estimated_cost_usd) as cost_usd'
        )->groupByRaw('DATE(DATE_ADD(created_at, INTERVAL 210 MINUTE))')->orderBy('usage_date')->get();
        $byDayModel = (clone $base)->selectRaw(
            'DATE(DATE_ADD(created_at, INTERVAL 210 MINUTE)) as usage_date, used_model, SUM(estimated_cost_usd) as cost_usd'
        )->groupByRaw('DATE(DATE_ADD(created_at, INTERVAL 210 MINUTE)), used_model')->orderBy('usage_date')->get();
        $logs = (clone $base)->latest()->paginate(50)->withQueryString();
        $models = AiUsageLog::query()->select('used_model')->distinct()->orderBy('used_model')->pluck('used_model');
        $budgetSwitches = DB::table('ai_failure_logs as failures')
            ->leftJoin('ai_usage_logs as usage', 'usage.request_id', '=', 'failures.request_id')
            ->where('failures.category', 'daily_cost_limit')
            ->where('failures.stage', 'budget')
            ->where('failures.created_at', '>=', $from->copy()->utc())
            ->where('failures.created_at', '<=', $to->copy()->utc())
            ->select([
                'failures.id',
                'failures.created_at',
                'failures.model_key as source_model',
                'usage.used_model as target_model',
                'usage.total_tokens',
                'usage.estimated_cost_usd as fallback_cost_usd',
            ])
            ->orderByDesc('failures.created_at')
            ->limit(100)
            ->get();
        $budgetSwitchSummary = (object) [
            'attempts' => $budgetSwitches->count(),
            'successful' => $budgetSwitches->whereNotNull('target_model')->count(),
            'fallback_cost_usd' => $budgetSwitches->sum(fn ($row) => (float) ($row->fallback_cost_usd ?? 0)),
            'last_switched_at' => $budgetSwitches->first()?->created_at,
        ];
        $budgetRules = AiModel::with('fallbackModel')
            ->where('is_active', true)
            ->whereNotNull('daily_cost_limit_usd')
            ->where('daily_cost_limit_usd', '>', 0)
            ->orderBy('sort_order')
            ->get();
        $storedBudget = DB::table('settings')->where('key', 'ai_daily_budget_usd')->value('value');
        $dailyBudgetUsd = max(0, (float) ($storedBudget ?? AiModel::where('is_active', true)
            ->whereNotNull('daily_cost_limit_usd')->sum('daily_cost_limit_usd')));
        $dailyScaleUsd = max($dailyBudgetUsd, (float) ($byDay->max('cost_usd') ?? 0), 0.000001);
        $rangeDays = (int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1;
        $chartModels = $byModel->pluck('used_model')->values();
        $costLookup = $byDayModel->keyBy(fn ($row) => $row->usage_date . '|' . $row->used_model);
        $chartLabels = [];
        $chartJalaliLabels = [];
        $chartSeries = $chartModels->mapWithKeys(fn ($model) => [$model => []])->all();
        for ($cursor = $from->copy()->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $chartLabels[] = $date;
            $chartJalaliLabels[] = Jalalian::fromCarbon($cursor)->format('m/d');
            foreach ($chartModels as $model) {
                $chartSeries[$model][] = (float) ($costLookup->get($date . '|' . $model)->cost_usd ?? 0);
            }
        }
        $chartData = [
            'labels' => $chartLabels,
            'jalaliLabels' => $chartJalaliLabels,
            'series' => $chartModels->map(fn ($model) => [
                'name' => $model,
                'values' => $chartSeries[$model],
            ])->values(),
        ];

        $fromJalali = Jalalian::fromCarbon($from)->format('Y/m/d');
        $toJalali = Jalalian::fromCarbon($to)->format('Y/m/d');

        return view('admin.ai_usage', compact(
            'summary', 'byModel', 'byDay', 'logs', 'models', 'from', 'to', 'fromJalali', 'toJalali',
            'dailyBudgetUsd', 'dailyScaleUsd', 'rangeDays', 'chartData',
            'budgetSwitches', 'budgetSwitchSummary', 'budgetRules',
        ));
    }

    public function calendarConfig()
    {
        return response()->json([
            'endpoint' => route('admin.calendar.month', [], false),
            'today' => Jalalian::fromCarbon(now('Asia/Tehran'))->format('Y/m/d'),
            'minYear' => 1200,
            'maxYear' => 1500,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function calendarMonth(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:1200|max:1500',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $first = new Jalalian($year, $month, 1);
        $firstCarbon = Carbon::createFromFormat('Y-m-d', $first->toCarbon()->format('Y-m-d'), 'Asia/Tehran');
        $nextCarbon = Carbon::createFromFormat('Y-m-d', $first->addMonths(1)->toCarbon()->format('Y-m-d'), 'Asia/Tehran');
        $daysInMonth = (int) $firstCarbon->diffInDays($nextCarbon);
        $offset = ((int) $firstCarbon->dayOfWeek + 1) % 7;
        $days = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $weekday = ($offset + $day - 1) % 7;
            $days[] = [
                'day' => $day,
                'value' => sprintf('%04d/%02d/%02d', $year, $month, $day),
                'weekday' => $weekday,
                'is_friday' => $weekday === 6,
            ];
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'offset' => $offset,
            'days' => $days,
            'today' => Jalalian::fromCarbon(now('Asia/Tehran'))->format('Y/m/d'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function parseReportDate(?string $value, Carbon $default, string $field): Carbon
    {
        if ($value === null || trim($value) === '') {
            return $default->copy()->setTimezone('Asia/Tehran');
        }

        $normalized = strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        try {
            if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $normalized)) {
                $jalali = Jalalian::fromFormat('Y/m/d', $normalized);
                if ($jalali->format('Y/m/d') !== sprintf('%04d/%02d/%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay())) {
                    throw new \InvalidArgumentException('Invalid Jalali date');
                }

                return Carbon::createFromFormat('Y-m-d', $jalali->toCarbon()->format('Y-m-d'), 'Asia/Tehran');
            }

            // سازگاری با لینک‌های قدیمی که تاریخ میلادی داشتند.
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
                $date = Carbon::createFromFormat('!Y-m-d', $normalized, 'Asia/Tehran');
                if ($date->format('Y-m-d') !== $normalized) {
                    throw new \InvalidArgumentException('Invalid Gregorian date');
                }

                return $date;
            }
        } catch (\Throwable) {
            // پیام یکسان و قابل‌فهم برای همه خطاهای تبدیل تاریخ.
        }

        throw ValidationException::withMessages([
            $field => 'تاریخ شمسی معتبر را با قالب ۱۴۰۵/۰۸/۰۱ وارد کنید.',
        ]);
    }

    public function subscriberEconomics(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'regex:/^1[34][0-9]{2}-(0[1-9]|1[0-2])$/'],
            'search' => 'nullable|string|max:100',
        ]);

        $timezone = 'Asia/Tehran';
        $jalaliNow = Jalalian::fromCarbon(now($timezone));
        $selectedMonth = $request->input('month', sprintf('%04d-%02d', $jalaliNow->getYear(), $jalaliNow->getMonth()));
        [$selectedYear, $selectedMonthNumber] = array_map('intval', explode('-', $selectedMonth));
        $jalaliStart = new Jalalian($selectedYear, $selectedMonthNumber, 1);
        $jalaliNext = $jalaliStart->addMonths(1);
        $startLocal = Carbon::createFromFormat('Y-m-d H:i:s', $jalaliStart->toCarbon()->format('Y-m-d') . ' 00:00:00', $timezone);
        $endLocal = Carbon::createFromFormat('Y-m-d H:i:s', $jalaliNext->toCarbon()->format('Y-m-d') . ' 00:00:00', $timezone);
        $startUtc = $startLocal->copy()->utc();
        $endUtc = $endLocal->copy()->utc();

        $eligibleSubscriptions = DB::table('subscriptions')
            ->selectRaw('MAX(id) as subscription_id')
            ->where('paid', true)
            ->where('created_at', '<', $endUtc)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $startUtc)
            ->groupBy('user_id');

        $usageByUser = DB::table('ai_usage_logs')
            ->selectRaw('user_id, COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as total_tokens, COALESCE(SUM(estimated_cost_usd), 0) as cost_usd')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->groupBy('user_id');

        $base = DB::table('subscriptions as s')
            ->joinSub($eligibleSubscriptions, 'eligible', fn ($join) => $join->on('eligible.subscription_id', '=', 's.id'))
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->leftJoinSub($usageByUser, 'usage', fn ($join) => $join->on('usage.user_id', '=', 's.user_id'));

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $base->where(function ($query) use ($search): void {
                $query->where('u.name', 'like', "%{$search}%")
                    ->orWhere('u.phone', 'like', "%{$search}%")
                    ->orWhere('u.email', 'like', "%{$search}%");
            });
        }

        $summaryRow = (clone $base)->selectRaw(
            'COUNT(*) as subscribers, '
            . 'SUM(CASE WHEN COALESCE(usage.requests, 0) > 0 THEN 1 ELSE 0 END) as consuming_subscribers, '
            . 'COALESCE(SUM(usage.requests), 0) as requests, '
            . 'COALESCE(SUM(usage.total_tokens), 0) as total_tokens, '
            . 'COALESCE(SUM(usage.cost_usd), 0) as cost_usd, '
            . 'COALESCE(SUM(COALESCE(s.paid_amount_toman, p.price, 0)), 0) as subscription_value_toman'
        )->first();

        $rows = (clone $base)->selectRaw(
            'u.id as user_id, u.name, u.phone, u.email, p.name as plan_name, '
            . 'COALESCE(s.paid_amount_toman, p.price, 0) as subscription_value_toman, '
            . 's.created_at as subscription_started_at, s.expires_at, '
            . 'COALESCE(usage.requests, 0) as requests, COALESCE(usage.total_tokens, 0) as total_tokens, '
            . 'COALESCE(usage.cost_usd, 0) as cost_usd'
        )->orderByDesc('cost_usd')->orderByDesc('requests')->paginate(50)->withQueryString();

        $byPlan = (clone $base)->selectRaw(
            'p.id as plan_id, p.name as plan_name, COUNT(*) as subscribers, '
            . 'COALESCE(SUM(usage.requests), 0) as requests, COALESCE(SUM(usage.cost_usd), 0) as cost_usd, '
            . 'COALESCE(SUM(COALESCE(s.paid_amount_toman, p.price, 0)), 0) as subscription_value_toman'
        )->groupBy('p.id', 'p.name')->orderByDesc('cost_usd')->get();

        $usdRate = max(0, (float) (DB::table('settings')->where('key', 'usd_to_toman_rate')->value('value') ?? 0));
        $subscribers = (int) ($summaryRow->subscribers ?? 0);
        $requests = (int) ($summaryRow->requests ?? 0);
        $costUsd = (float) ($summaryRow->cost_usd ?? 0);
        $costToman = $usdRate > 0 ? $costUsd * $usdRate : null;
        $subscriptionValue = (int) ($summaryRow->subscription_value_toman ?? 0);
        $periodDays = max(1, (int) round($startLocal->diffInDays($endLocal)));
        $isCurrentMonth = $selectedYear === $jalaliNow->getYear() && $selectedMonthNumber === $jalaliNow->getMonth();
        $elapsedDays = $isCurrentMonth
            ? max(1, min($periodDays, $jalaliNow->getDay()))
            : $periodDays;
        $projectionFactor = $isCurrentMonth ? $periodDays / $elapsedDays : 1;

        $summary = [
            'subscribers' => $subscribers,
            'consuming_subscribers' => (int) ($summaryRow->consuming_subscribers ?? 0),
            'requests' => $requests,
            'total_tokens' => (int) ($summaryRow->total_tokens ?? 0),
            'cost_usd' => $costUsd,
            'cost_toman' => $costToman,
            'subscription_value_toman' => $subscriptionValue,
            'avg_requests_per_subscriber' => $subscribers > 0 ? $requests / $subscribers : 0,
            'avg_cost_usd_per_subscriber' => $subscribers > 0 ? $costUsd / $subscribers : 0,
            'avg_cost_toman_per_subscriber' => $subscribers > 0 && $costToman !== null ? $costToman / $subscribers : null,
            'cost_per_request_usd' => $requests > 0 ? $costUsd / $requests : 0,
            'cost_ratio_percent' => $costToman !== null && $subscriptionValue > 0 ? ($costToman / $subscriptionValue) * 100 : null,
            'projected_requests' => (int) round($requests * $projectionFactor),
            'projected_cost_usd' => $costUsd * $projectionFactor,
            'projected_avg_requests_per_subscriber' => $subscribers > 0 ? ($requests * $projectionFactor) / $subscribers : 0,
            'projected_avg_cost_usd_per_subscriber' => $subscribers > 0 ? ($costUsd * $projectionFactor) / $subscribers : 0,
            'is_current_month' => $isCurrentMonth,
            'elapsed_days' => $elapsedDays,
            'period_days' => $periodDays,
        ];

        $monthOptions = collect();
        for ($index = 0; $index < 13; $index++) {
            $absoluteMonth = ($jalaliNow->getYear() * 12 + $jalaliNow->getMonth() - 1) - $index;
            $year = intdiv($absoluteMonth, 12);
            $month = ($absoluteMonth % 12) + 1;
            $jalaliMonth = new Jalalian($year, $month, 1);
            $monthOptions->push([
                'value' => sprintf('%04d-%02d', $year, $month),
                'label' => $jalaliMonth->format('F Y'),
            ]);
        }

        return view('admin.subscriber_economics', compact(
            'summary', 'rows', 'byPlan', 'usdRate', 'selectedMonth', 'monthOptions', 'jalaliStart', 'jalaliNext'
        ));
    }

    public function aiErrors(Request $request)
    {
        $request->validate([
            'category' => 'nullable|string|max:40',
            'model' => 'nullable|string|max:100',
        ]);
        $query = AiFailureLog::query();
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('model')) {
            $query->where('model_key', $request->string('model')->toString());
        }

        $logs = (clone $query)->latest()->paginate(50)->withQueryString();
        $summary = AiFailureLog::where('created_at', '>=', now()->subDay())
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->orderByDesc('total')->get();
        $categories = AiFailureLog::select('category')->distinct()->orderBy('category')->pluck('category');
        $models = AiFailureLog::whereNotNull('model_key')->select('model_key')->distinct()->orderBy('model_key')->pluck('model_key');

        return view('admin.ai_errors', compact('logs', 'summary', 'categories', 'models'));
    }

    public function aiTest()
    {
        $models = AiModel::with('proxyServer')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('sort_order')
            ->get();
        $proxyServers = AiProxyServer::where('is_active', true)->orderBy('priority')->orderBy('name')->get();
        return view('admin.ai_test', compact('models', 'proxyServers'));
    }

    public function aiTestRun(Request $request, AiApiService $aiApiService)
    {
        $validated = $request->validate([
            'model_key' => 'required|string|exists:ai_models,key',
            'route_type' => 'required|in:direct,proxy',
            'proxy_server_id' => 'nullable|exists:ai_proxy_servers,id',
        ]);
        $model = AiModel::where('key', $validated['model_key'])->firstOrFail();
        $proxy = $validated['route_type'] === 'proxy'
            ? AiProxyServer::where('is_active', true)->find($validated['proxy_server_id'] ?? null)
            : null;

        return response()->json($aiApiService->diagnoseModel($model, $validated['route_type'], $proxy));

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
