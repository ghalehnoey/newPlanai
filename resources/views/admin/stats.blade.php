@extends('layouts.admin')
@section('title', 'آمار و گزارش')

@section('content')
<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">آمار و گزارش سیستم</h1>
        <p class="text-slate-500 text-sm mt-1">نمای کلی از وضعیت سیستم</p>
    </div>
    <a href="{{ route('admin.reports.repeat-purchasers') }}" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium px-4 py-2 rounded-xl transition shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        دریافت گزارش خریدهای مکرر (Excel)
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Users by month --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <h3 class="font-semibold text-slate-800 mb-4">کاربران جدید — {{ now()->year }}</h3>
        <div class="space-y-2">
            @foreach($data['usersByMonth'] as $row)
            @php
                $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            @endphp
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 w-16 text-left">{{ $months[$row->m - 1] ?? $row->m }}</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3">
                    <div class="bg-indigo-500 h-3 rounded-full" style="width: {{ min(100, $row->cnt / max(1, $data['usersByMonth']->max('cnt')) * 100) }}%"></div>
                </div>
                <span class="text-sm font-medium text-slate-700 w-8 text-left">{{ $row->cnt }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Messages by day --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <h3 class="font-semibold text-slate-800 mb-4">پیام‌های ۳۰ روز اخیر</h3>
        <div class="space-y-1.5 max-h-64 overflow-y-auto">
            @foreach($data['messagesByDay'] as $row)
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 w-24 font-mono">{{ $row->d }}</span>
                <div class="flex-1 bg-slate-100 rounded-full h-2.5">
                    <div class="bg-violet-500 h-2.5 rounded-full" style="width: {{ min(100, $row->cnt / max(1, $data['messagesByDay']->max('cnt')) * 100) }}%"></div>
                </div>
                <span class="text-sm font-medium text-slate-700 w-8 text-left">{{ $row->cnt }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Top plan --}}
    @if($data['topPlan'])
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <h3 class="font-semibold text-slate-800 mb-3">محبوب‌ترین پلن</h3>
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center text-2xl">🏆</div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ $data['topPlan']->name }}</div>
                <div class="text-slate-500 text-sm">{{ number_format($data['topPlan']->subscriptions_count) }} اشتراک</div>
                <div class="text-slate-400 text-xs">{{ number_format($data['topPlan']->price) }} تومان — {{ $data['topPlan']->questions_limit }} سؤال</div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
