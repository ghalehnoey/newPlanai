@extends('layouts.admin')
@section('title', 'سابقه اشتراک - ' . $user->name)

@section('content')

{{-- Header --}}
<div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">سابقه اشتراک</h1>
        <p class="text-sm text-slate-500 mt-1">جزئیات دوره‌های اشتراک کاربر</p>
    </div>
    <a href="{{ route('admin.dashboard') }}"
       class="self-start px-4 py-2 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-50">
        ← بازگشت به داشبورد
    </a>
</div>

{{-- Quick User Search --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
    <form method="GET" class="flex gap-2 items-center">
        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="q" value="{{ $searchQuery ?? '' }}"
               placeholder="جستجوی کاربر دیگر (نام یا موبایل)..."
               class="input-field flex-1">
        <button class="btn-primary whitespace-nowrap">جستجو</button>
    </form>
    @if($searchResults && $searchResults->isNotEmpty())
    <div class="mt-3 border border-slate-100 rounded-xl divide-y divide-slate-50 overflow-hidden">
        @foreach($searchResults as $ru)
        <a href="{{ route('admin.users.purchases', $ru) }}"
           class="flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition-colors {{ $ru->id === $user->id ? 'bg-indigo-50' : '' }}">
            <div>
                <span class="font-medium text-slate-800 text-sm">{{ $ru->name ?: '—' }}</span>
                <span class="text-xs text-slate-400 mr-2">{{ $ru->phone }}</span>
            </div>
            @if($ru->activeSubscription)
                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-lg">فعال — {{ $ru->activeSubscription->plan->name ?? '' }}</span>
            @else
                <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-lg">بدون اشتراک</span>
            @endif
        </a>
        @endforeach
    </div>
    @elseif($searchQuery)
    <p class="mt-3 text-sm text-slate-400 text-center py-2">کاربری یافت نشد</p>
    @endif
</div>

{{-- User Summary Card --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
        <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center flex-shrink-0 text-2xl font-bold text-indigo-600">
            {{ mb_substr($user->name ?: '?', 0, 1) }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-bold text-slate-800 text-lg">{{ $user->name ?: 'بدون نام' }}</div>
            <div class="text-sm text-slate-500 mt-0.5 flex flex-wrap gap-3">
                <span>موبایل: {{ $user->phone ?: '—' }}</span>
                @if($user->email)<span>ایمیل: {{ $user->email }}</span>@endif
                <span>عضو از: {{ \Carbon\Carbon::parse($user->created_at)->format('Y/m/d') }}</span>
            </div>
        </div>
        <div class="flex gap-3 flex-wrap">
            <div class="text-center px-4 py-2 bg-slate-50 rounded-xl">
                <div class="text-xl font-bold text-slate-800">{{ number_format($totalMessages) }}</div>
                <div class="text-xs text-slate-400 mt-0.5">کل پیام‌ها</div>
            </div>
            <div class="text-center px-4 py-2 bg-slate-50 rounded-xl">
                <div class="text-xl font-bold text-slate-800">{{ $subscriptions->where('paid', 1)->count() }}</div>
                <div class="text-xs text-slate-400 mt-0.5">خرید پرداخت‌شده</div>
            </div>
        </div>
    </div>

    {{-- Current Status --}}
    <div class="mt-4 pt-4 border-t border-slate-100">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">وضعیت فعلی</div>
        @if($activeSubscription)
        @php
            $daysLeft = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($activeSubscription->expires_at), false);
            $pct = $activeSubscription->plan
                ? min(100, intval($activeSubscription->questions_used / max(1, $activeSubscription->plan->questions_limit) * 100))
                : 0;
        @endphp
        <div class="flex flex-wrap gap-4 items-center">
            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 px-3 py-1.5 rounded-xl text-sm font-semibold">
                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                اشتراک فعال — {{ $activeSubscription->plan->name ?? '' }}
            </span>
            <span class="text-sm text-slate-600">
                انقضا: <strong>{{ \Carbon\Carbon::parse($activeSubscription->expires_at)->format('Y/m/d') }}</strong>
                @if($daysLeft > 0)
                    <span class="text-emerald-600">({{ $daysLeft }} روز مانده)</span>
                @endif
            </span>
            <span class="text-sm text-slate-600">
                مصرف: <strong>{{ number_format($activeSubscription->questions_used) }}</strong>
                از {{ number_format($activeSubscription->plan->questions_limit ?? 0) }}
            </span>
        </div>
        <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden w-full max-w-sm">
            <div class="h-full rounded-full {{ $pct > 80 ? 'bg-red-400' : ($pct > 50 ? 'bg-amber-400' : 'bg-emerald-400') }}"
                 style="width: {{ $pct }}%"></div>
        </div>
        @else
        <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-500 px-3 py-1.5 rounded-xl text-sm font-medium">
            <span class="w-2 h-2 bg-slate-400 rounded-full"></span>
            بدون اشتراک فعال
        </span>
        @endif
    </div>
</div>

{{-- Timeline --}}
@if($subscriptions->isNotEmpty())
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
    <h2 class="font-semibold text-slate-800 mb-5">تاریخچه دوره‌های اشتراک</h2>
    <div class="relative">
        <div class="absolute right-[18px] top-2 bottom-2 w-0.5 bg-slate-100 z-0"></div>
        <div class="space-y-0">
        @foreach($subscriptions as $i => $sub)
        @php
            $isActive   = $sub->paid && $sub->expires_at && \Carbon\Carbon::parse($sub->expires_at)->isFuture();
            $isPending  = !$sub->paid;
            $pct        = ($sub->plan && $sub->plan->questions_limit > 0)
                            ? min(100, intval($sub->questions_used / $sub->plan->questions_limit * 100))
                            : 0;
            $dotColor   = $isActive ? 'bg-green-500 ring-green-200' : ($isPending ? 'bg-amber-400 ring-amber-100' : 'bg-slate-300 ring-slate-100');
            $cardBorder = $isActive ? 'border-green-200 bg-green-50/30' : ($isPending ? 'border-amber-200 bg-amber-50/20' : 'border-slate-100');
            $num        = $subscriptions->count() - $i;
            $dur        = ($sub->expires_at && $sub->created_at)
                            ? \Carbon\Carbon::parse($sub->created_at)->diffInDays(\Carbon\Carbon::parse($sub->expires_at))
                            : null;
        @endphp
        <div class="flex gap-4 pb-5 relative z-10">
            <div class="flex-shrink-0 w-9 h-9 rounded-full {{ $dotColor }} ring-4 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                {{ $num }}
            </div>
            <div class="flex-1 border {{ $cardBorder }} rounded-xl p-4 -mt-1">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <div>
                        <span class="font-semibold text-slate-800">{{ $sub->plan->name ?? 'نامشخص' }}</span>
                        @if($sub->plan && $sub->plan->price > 0)
                            <span class="text-xs text-slate-400 mr-1">— {{ number_format($sub->plan->price) }} تومان</span>
                        @endif
                    </div>
                    @if($isActive)
                        <span class="text-xs bg-green-100 text-green-700 px-2.5 py-0.5 rounded-lg font-medium">فعال</span>
                    @elseif($isPending)
                        <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-lg">در انتظار پرداخت</span>
                    @else
                        <span class="text-xs bg-slate-100 text-slate-500 px-2.5 py-0.5 rounded-lg">منقضی</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 mb-3">
                    <span class="font-medium">خرید:</span>
                    <span class="bg-white border border-slate-200 px-2 py-0.5 rounded-lg font-mono">
                        {{ \Carbon\Carbon::parse($sub->created_at)->format('Y/m/d') }}
                    </span>
                    <span class="text-slate-300">→</span>
                    <span class="font-medium">انقضا:</span>
                    <span class="border px-2 py-0.5 rounded-lg font-mono {{ $isActive ? 'bg-green-100 border-green-200 text-green-800' : 'bg-white border-slate-200' }}">
                        {{ $sub->expires_at ? \Carbon\Carbon::parse($sub->expires_at)->format('Y/m/d') : '—' }}
                    </span>
                    @if($dur !== null)
                    <span class="text-slate-400">({{ $dur }} روزه)</span>
                    @endif
                </div>
                @if($sub->paid && $sub->plan && $sub->plan->questions_limit > 0)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 whitespace-nowrap">مصرف:</span>
                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden max-w-xs">
                        <div class="h-full rounded-full {{ $pct > 80 ? 'bg-red-400' : ($pct > 50 ? 'bg-amber-400' : 'bg-indigo-400') }}"
                             style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs text-slate-600 whitespace-nowrap font-medium">
                        {{ number_format($sub->questions_used) }} / {{ number_format($sub->plan->questions_limit) }}
                    </span>
                </div>
                @endif
                @if($sub->zibal_track_id)
                <div class="mt-2 text-xs text-slate-400">کد پیگیری: <span class="font-mono">{{ $sub->zibal_track_id }}</span></div>
                @endif
            </div>
        </div>

        {{-- Gap between subscriptions --}}
        @if(!$loop->last)
        @php
            $nextSub = $subscriptions[$i + 1];
            $gapDays = \Carbon\Carbon::parse($sub->expires_at ?: $sub->created_at)
                ->diffInDays(\Carbon\Carbon::parse($nextSub->created_at), false);
        @endphp
        @if($gapDays > 1)
        <div class="flex gap-4 pb-4 relative z-10">
            <div class="w-9"></div>
            <div class="flex-1">
                <span class="text-xs text-amber-600 bg-amber-50 border border-amber-100 px-3 py-1 rounded-lg">
                    فاصله {{ $gapDays }} روزه بین دو اشتراک
                </span>
            </div>
        </div>
        @endif
        @endif
        @endforeach
        </div>
    </div>
</div>
@else
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center text-slate-400 mb-5">
    هیچ اشتراکی ثبت نشده است.
</div>
@endif

{{-- Detail Table --}}
@if($subscriptions->isNotEmpty())
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800 text-sm">جدول جزئیات</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-right font-medium">#</th>
                    <th class="px-4 py-3 text-right font-medium">پلن</th>
                    <th class="px-4 py-3 text-right font-medium">تاریخ خرید</th>
                    <th class="px-4 py-3 text-right font-medium">انقضا</th>
                    <th class="px-4 py-3 text-right font-medium">سؤالات</th>
                    <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                    <th class="px-4 py-3 text-right font-medium">کد پیگیری</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($subscriptions->sortByDesc('created_at') as $i => $sub)
                @php $isActive = $sub->paid && $sub->expires_at && \Carbon\Carbon::parse($sub->expires_at)->isFuture(); @endphp
                <tr class="hover:bg-slate-50/50 {{ $isActive ? 'bg-green-50/20' : '' }}">
                    <td class="px-4 py-3 text-slate-400 text-xs">{{ $i + 1 }}</td>
                    <td class="px-4 py-3 font-medium text-slate-700">{{ $sub->plan->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500 text-xs font-mono">{{ \Carbon\Carbon::parse($sub->created_at)->format('Y/m/d H:i') }}</td>
                    <td class="px-4 py-3 text-slate-500 text-xs font-mono">{{ $sub->expires_at ? \Carbon\Carbon::parse($sub->expires_at)->format('Y/m/d') : '—' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ number_format($sub->questions_used) }} / {{ number_format($sub->plan->questions_limit ?? 0) }}</td>
                    <td class="px-4 py-3">
                        @if(!$sub->paid)
                            <span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-lg">در انتظار</span>
                        @elseif($isActive)
                            <span class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-lg">فعال</span>
                        @else
                            <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-lg">منقضی</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ $sub->zibal_track_id ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3 border-t border-slate-100 text-xs text-slate-400 flex justify-between">
        <span>مجموع: {{ $subscriptions->count() }} دوره</span>
        <span>پرداخت‌شده: {{ $subscriptions->where('paid', 1)->count() }} مورد</span>
    </div>
</div>
@endif

@endsection
