@extends('layouts.admin')
@section('title', 'تنظیمات سیستم')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">تنظیمات سیستم</h1>
</div>

@if(session('success'))
<div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
    {{ session('success') }}
</div>
@endif

{{-- تنظیمات سوال رایگان روزانه --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
    <h3 class="font-semibold text-slate-800 mb-1">سوال رایگان روزانه</h3>
    <p class="text-xs text-slate-400 mb-4">تعداد سوالی که کاربران بدون اشتراک می‌توانند در روز بپرسند. صفر = غیرفعال.</p>
    <form method="POST" action="{{ route('admin.system.settings.save') }}" class="flex items-center gap-3">
        @csrf
        <input type="number"
               name="daily_free_questions"
               value="{{ $dailyFreeQuestions }}"
               min="0" max="100"
               class="w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-400">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg transition">
            ذخیره
        </button>
        <span class="text-xs text-slate-400">سوال در روز</span>
    </form>
    @error('daily_free_questions')
        <p class="mt-2 text-red-500 text-xs">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <h3 class="font-semibold text-slate-800 mb-4">اطلاعات سرور</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">PHP Version</dt>
                <dd class="font-mono font-medium text-slate-800">{{ PHP_VERSION }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Laravel Version</dt>
                <dd class="font-mono font-medium text-slate-800">{{ app()->version() }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Database</dt>
                <dd class="font-mono font-medium text-slate-800">{{ config('database.connections.mysql.database') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Environment</dt>
                <dd class="font-mono font-medium text-slate-800">{{ config('app.env') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Debug Mode</dt>
                <dd class="font-mono font-medium {{ config('app.debug') ? 'text-amber-600' : 'text-emerald-600' }}">
                    {{ config('app.debug') ? 'فعال (توصیه می‌شود خاموش شود)' : 'غیرفعال ✓' }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <h3 class="font-semibold text-slate-800 mb-4">دسترسی‌های سیستم</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Storage</dt>
                <dd class="{{ is_writable(storage_path()) ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ is_writable(storage_path()) ? 'قابل نوشتن ✓' : 'مشکل دسترسی ✗' }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Cache</dt>
                <dd class="{{ is_writable(base_path('bootstrap/cache')) ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ is_writable(base_path('bootstrap/cache')) ? 'قابل نوشتن ✓' : 'مشکل دسترسی ✗' }}
                </dd>
            </div>
        </dl>
    </div>
</div>
@endsection
