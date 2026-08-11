<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)
                     ->orderBy('price', 'asc')
                     ->get();

        // اشتراک فعال ← روش صحیح (Query Builder)
        $subscription = Subscription::with('plan')
            ->where('user_id', Auth::id())
            ->active()                   // از Scope استفاده می‌کند
            ->orderByDesc('id')
            ->first();

        return view('plans', compact('plans', 'subscription'));
    }

    public function purchase(Request $request, Plan $plan)
    {
        $user = Auth::user();

        // بررسی وجود اشتراک فعال
        $hasActive = Subscription::where('user_id', $user->id)
            ->active()
            ->exists();

        if ($hasActive) {
            return redirect()->route('plans')
                ->with('error', 'شما در حال حاضر یک پلن فعال دارید!');
        }

        if (!$plan->is_active) {
            return redirect()->route('plans')
                ->with('error', 'این پلن در حال حاضر فعال نیست.');
        }

        // ایجاد اشتراک جدید
        Subscription::create([
            'user_id'        => $user->id,
            'plan_id'        => $plan->id,
            'questions_used' => 0,
            'expires_at'     => now()->addDays(30),
            'paid'           => true,
            'gateway'        => 'free_test_mode',
            'status'         => 'active',
            'activated_at'   => now(),
        ]);

        $questions = $plan->questions_limit >= 99999
            ? 'نامحدود ♾️'
            : $plan->questions_limit . ' سؤال';

        return redirect()->route('home')
            ->with('success', "پلن «{$plan->name}» با موفقیت فعال شد. اکنون {$questions} در اختیار دارید.");
    }
}
