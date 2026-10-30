@extends('layouts.admin')
@section('title', 'اقتصاد کاربران اشتراکی')

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">اقتصاد کاربران اشتراکی</h1>
        <p class="mt-1 text-xs text-slate-500">هزینه واقعی مصرف AI کاربران دارای اشتراک در ماه انتخابی ـ فقط مدیر کل</p>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <div>
            <label class="block text-xs text-slate-500">ماه شمسی</label>
            <select name="month" class="input-field">
                @foreach($monthOptions as $option)
                    <option value="{{ $option['value'] }}" {{ $selectedMonth === $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500">کاربر</label>
            <input type="text" name="search" value="{{ request('search') }}" class="input-field" placeholder="نام، موبایل یا ایمیل">
        </div>
        <button class="btn-primary">نمایش گزارش</button>
    </form>
</div>

<div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-800">
    <span>از {{ $jalaliStart->format('Y/m/d') }} تا پیش از {{ $jalaliNext->format('Y/m/d') }}</span>
    <span>مبنای مشترک: آخرین اشتراک پرداخت‌شده‌ای که با این ماه هم‌پوشانی داشته است.</span>
</div>

@if($usdRate <= 0)
<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    نرخ دلار تنظیم نشده است؛ هزینه دلاری دقیق است اما هزینه تومانی و نسبت آن به مبلغ اشتراک نمایش داده نمی‌شود.
    <a href="{{ route('admin.system') }}" class="font-semibold underline">تنظیم نرخ دلار</a>
</div>
@endif

<div class="mb-6 grid grid-cols-2 gap-3 xl:grid-cols-6">
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="text-xs text-slate-500">مشترک ماه</div>
        <div class="mt-2 text-2xl font-black text-slate-800">{{ number_format($summary['subscribers']) }}</div>
        <div class="mt-1 text-xs text-slate-400">{{ number_format($summary['consuming_subscribers']) }} نفر دارای مصرف</div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="text-xs text-slate-500">متوسط سؤال هر مشترک</div>
        <div class="mt-2 text-2xl font-black text-indigo-700">{{ number_format($summary['avg_requests_per_subscriber'], 1) }}</div>
        <div class="mt-1 text-xs text-slate-400">{{ number_format($summary['requests']) }} سؤال در مجموع</div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="text-xs text-slate-500">متوسط هزینه هر مشترک</div>
        <div class="mt-2 text-2xl font-black text-rose-700" dir="ltr">${{ number_format($summary['avg_cost_usd_per_subscriber'], 4) }}</div>
        <div class="mt-1 text-xs text-slate-400">{{ $summary['avg_cost_toman_per_subscriber'] !== null ? number_format($summary['avg_cost_toman_per_subscriber']).' تومان' : 'نرخ دلار تنظیم نشده' }}</div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="text-xs text-slate-500">هزینه هر سؤال</div>
        <div class="mt-2 text-2xl font-black text-amber-700" dir="ltr">${{ number_format($summary['cost_per_request_usd'], 6) }}</div>
        <div class="mt-1 text-xs text-slate-400">براساس پاسخ‌های ثبت‌شده</div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="text-xs text-slate-500">کل هزینه مشترکان</div>
        <div class="mt-2 text-2xl font-black text-rose-700" dir="ltr">${{ number_format($summary['cost_usd'], 4) }}</div>
        <div class="mt-1 text-xs text-slate-400">{{ $summary['cost_toman'] !== null ? number_format($summary['cost_toman']).' تومان' : number_format($summary['total_tokens']).' توکن' }}</div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="text-xs text-slate-500">نسبت هزینه AI به اشتراک</div>
        <div class="mt-2 text-2xl font-black {{ ($summary['cost_ratio_percent'] ?? 0) >= 20 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $summary['cost_ratio_percent'] !== null ? number_format($summary['cost_ratio_percent'], 2).'٪' : '—' }}</div>
        <div class="mt-1 text-xs text-slate-400">از {{ number_format($summary['subscription_value_toman']) }} تومان ارزش اشتراک</div>
    </div>
</div>

@if($summary['is_current_month'])
<div class="mb-6 rounded-2xl border border-violet-100 bg-gradient-to-l from-violet-50 to-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="font-bold text-slate-800">برآورد پایان ماه</h2><p class="mt-1 text-xs text-slate-500">بر پایه {{ number_format($summary['elapsed_days']) }} روز سپری‌شده از {{ number_format($summary['period_days']) }} روز؛ این عدد پیش‌بینی خطی است.</p></div>
        <div class="flex flex-wrap gap-6 text-center"><div><div class="text-xs text-slate-500">سؤال پیش‌بینی‌شده</div><div class="mt-1 text-xl font-bold text-violet-700">{{ number_format($summary['projected_requests']) }}</div></div><div><div class="text-xs text-slate-500">متوسط سؤال هر مشترک</div><div class="mt-1 text-xl font-bold text-violet-700">{{ number_format($summary['projected_avg_requests_per_subscriber'], 1) }}</div></div><div><div class="text-xs text-slate-500">هزینه پیش‌بینی‌شده</div><div class="mt-1 text-xl font-bold text-rose-700" dir="ltr">${{ number_format($summary['projected_cost_usd'], 4) }}</div></div><div><div class="text-xs text-slate-500">متوسط هزینه هر مشترک</div><div class="mt-1 text-xl font-bold text-rose-700" dir="ltr">${{ number_format($summary['projected_avg_cost_usd_per_subscriber'], 4) }}</div></div></div>
    </div>
</div>
@endif

<div class="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
    <div class="border-b p-4"><h2 class="font-semibold text-slate-800">تحلیل به تفکیک پلن</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-3 text-right">پلن</th><th>مشترک</th><th>سؤال</th><th>متوسط سؤال</th><th>هزینه AI</th><th>متوسط هزینه</th><th>نسبت هزینه</th></tr></thead>
            <tbody>
            @forelse($byPlan as $plan)
                @php $planCostToman = $usdRate > 0 ? (float)$plan->cost_usd * $usdRate : null; $planRatio = $planCostToman !== null && $plan->subscription_value_toman > 0 ? ($planCostToman / $plan->subscription_value_toman) * 100 : null; @endphp
                <tr class="border-t">
                    <td class="p-3 font-medium text-slate-800">{{ $plan->plan_name }}</td>
                    <td class="text-center">{{ number_format($plan->subscribers) }}</td>
                    <td class="text-center">{{ number_format($plan->requests) }}</td>
                    <td class="text-center">{{ number_format($plan->subscribers > 0 ? $plan->requests / $plan->subscribers : 0, 1) }}</td>
                    <td class="text-center" dir="ltr">${{ number_format($plan->cost_usd, 4) }}</td>
                    <td class="text-center" dir="ltr">${{ number_format($plan->subscribers > 0 ? $plan->cost_usd / $plan->subscribers : 0, 4) }}</td>
                    <td class="text-center font-semibold {{ ($planRatio ?? 0) >= 20 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $planRatio !== null ? number_format($planRatio, 2).'٪' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-slate-400">اشتراک فعالی در این ماه یافت نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b p-4"><h2 class="font-semibold text-slate-800">جزئیات کاربران اشتراکی</h2><a href="{{ route('admin.ai-usage', ['from' => $jalaliStart->format('Y/m/d'), 'to' => \Morilog\Jalali\Jalalian::fromCarbon($jalaliNext->toCarbon()->subDay())->format('Y/m/d')]) }}" class="text-xs font-semibold text-indigo-700 hover:underline">مشاهده تمام مصرف AI این ماه</a></div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-right">کاربر</th><th>پلن</th><th>ارزش اشتراک</th><th>سؤال</th><th>توکن</th><th>هزینه دلار</th><th>هزینه تومان</th><th>نسبت هزینه</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php $userCostToman = $usdRate > 0 ? (float)$row->cost_usd * $usdRate : null; $userRatio = $userCostToman !== null && $row->subscription_value_toman > 0 ? ($userCostToman / $row->subscription_value_toman) * 100 : null; @endphp
                <tr class="border-t hover:bg-slate-50/60">
                    <td class="p-3"><div class="font-medium text-slate-800">{{ $row->name ?: 'بدون نام' }}</div><div class="mt-0.5 text-slate-400" dir="ltr">{{ $row->phone ?: $row->email }}</div></td>
                    <td class="text-center">{{ $row->plan_name }}</td>
                    <td class="text-center">{{ number_format($row->subscription_value_toman) }}</td>
                    <td class="text-center font-semibold">{{ number_format($row->requests) }}</td>
                    <td class="text-center">{{ number_format($row->total_tokens) }}</td>
                    <td class="text-center font-semibold" dir="ltr">${{ number_format($row->cost_usd, 6) }}</td>
                    <td class="text-center">{{ $userCostToman !== null ? number_format($userCostToman) : '—' }}</td>
                    <td class="text-center"><span class="rounded-full px-2 py-1 font-semibold {{ ($userRatio ?? 0) >= 20 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $userRatio !== null ? number_format($userRatio, 2).'٪' : '—' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-8 text-center text-slate-400">کاربری برای این ماه پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $rows->links() }}</div>
</div>
@endsection
