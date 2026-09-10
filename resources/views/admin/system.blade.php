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

<div class="bg-white rounded-2xl border border-indigo-100 shadow-sm p-5 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
            <h3 class="font-semibold text-slate-800 mb-1">حافظه هوشمند و کنترل توکن چت</h3>
            <p class="text-xs text-slate-500 leading-6 max-w-3xl">
                چند رفت‌وبرگشت آخر عیناً ارسال می‌شود و بخش قدیمی مکالمه روی همین سرور، بدون مصرف API، به حافظه فشرده تبدیل می‌شود.
                خلاصه مکالمه‌های قبلی فقط از همان کاربر برداشته می‌شود و اطلاعات کاربران با هم مخلوط نمی‌شود.
            </p>
        </div>
        <div class="text-left">
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">خلاصه‌سازی محلی؛ هزینه API صفر</span>
            <p class="mt-2 text-xs text-slate-400">{{ number_format($memoryStats->conversations ?? 0) }} حافظه آماده ـ حدود {{ number_format($memoryStats->estimated_tokens ?? 0) }} توکن فشرده</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.system.settings.save') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="ai_memory_enabled" value="0">
        <label class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4 cursor-pointer">
            <input type="checkbox" name="ai_memory_enabled" value="1" {{ old('ai_memory_enabled', $memorySettings['ai_memory_enabled']) ? 'checked' : '' }} class="h-5 w-5 accent-indigo-600">
            <span><strong class="block text-sm text-slate-800">فعال‌بودن حافظه فشرده</strong><span class="text-xs text-slate-500">با خاموش‌کردن، فقط رفت‌وبرگشت‌های اخیر ارسال می‌شوند.</span></span>
        </label>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">رفت‌وبرگشت کامل اخیر</span>
                <input type="number" name="ai_memory_recent_turns" value="{{ old('ai_memory_recent_turns', $memorySettings['ai_memory_recent_turns']) }}" min="0" max="20" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <span class="mt-1 block text-xs text-slate-400">هر رفت‌وبرگشت = یک سؤال + پاسخ؛ پیشنهاد: ۳</span>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">تعداد مکالمه قبلی</span>
                <input type="number" name="ai_memory_previous_conversations" value="{{ old('ai_memory_previous_conversations', $memorySettings['ai_memory_previous_conversations']) }}" min="0" max="10" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <span class="mt-1 block text-xs text-slate-400">حداکثر تعداد مرتبط؛ چت نامرتبط ارسال نمی‌شود. پیشنهاد: ۳</span>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">بودجه کل زمینه</span>
                <input type="number" name="ai_memory_context_token_budget" value="{{ old('ai_memory_context_token_budget', $memorySettings['ai_memory_context_token_budget']) }}" min="800" max="30000" step="100" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <span class="mt-1 block text-xs text-slate-400">توکن تقریبی تاریخچه و حافظه؛ پیشنهاد کم‌مصرف: ۳۰۰۰</span>
            </label>
        </div>

        <details class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-700">تنظیمات پیشرفته کاهش توکن</summary>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">حداکثر اندازه حافظه همین چت</span><input type="number" name="ai_memory_summary_max_characters" value="{{ old('ai_memory_summary_max_characters', $memorySettings['ai_memory_summary_max_characters']) }}" min="600" max="12000" step="100" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><span class="mt-1 block text-xs text-slate-400">کاراکتر؛ پیشنهاد: ۱۶۰۰</span></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">سقف خلاصه چت‌های قبلی</span><input type="number" name="ai_memory_cross_chat_max_characters" value="{{ old('ai_memory_cross_chat_max_characters', $memorySettings['ai_memory_cross_chat_max_characters']) }}" min="0" max="10000" step="100" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><span class="mt-1 block text-xs text-slate-400">مجموع کاراکتر؛ صفر = غیرفعال</span></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">سقف هر پیام اخیر</span><input type="number" name="ai_memory_recent_message_max_characters" value="{{ old('ai_memory_recent_message_max_characters', $memorySettings['ai_memory_recent_message_max_characters']) }}" min="500" max="12000" step="100" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><span class="mt-1 block text-xs text-slate-400">ابتدا و نتیجه پیام حفظ می‌شود؛ پیشنهاد: ۳۰۰۰</span></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">پیام‌های قابل بررسی در شروع</span><input type="number" name="ai_memory_source_message_limit" value="{{ old('ai_memory_source_message_limit', $memorySettings['ai_memory_source_message_limit']) }}" min="20" max="500" step="10" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><span class="mt-1 block text-xs text-slate-400">فقط پردازش محلی؛ پیشنهاد: ۱۶۰</span></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">تعداد فایل قدیمی قابل استفاده</span><input type="number" name="ai_memory_reuse_files_limit" value="{{ old('ai_memory_reuse_files_limit', $memorySettings['ai_memory_reuse_files_limit']) }}" min="0" max="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><span class="mt-1 block text-xs text-slate-400">پیشنهاد: ۱؛ صفر = فایل قدیمی ارسال نشود</span></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">عمر نگهداری فایل در چت</span><input type="number" name="ai_memory_file_reuse_turns" value="{{ old('ai_memory_file_reuse_turns', $memorySettings['ai_memory_file_reuse_turns']) }}" min="0" max="20" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><span class="mt-1 block text-xs text-slate-400">رفت‌وبرگشت؛ فقط هنگام اشاره کاربر به فایل. پیشنهاد: ۲</span></label>
            </div>
        </details>

        @if($errors->hasAny(['ai_memory_enabled','ai_memory_recent_turns','ai_memory_previous_conversations','ai_memory_summary_max_characters','ai_memory_cross_chat_max_characters','ai_memory_context_token_budget','ai_memory_recent_message_max_characters','ai_memory_source_message_limit','ai_memory_reuse_files_limit','ai_memory_file_reuse_turns']))
            <div class="rounded-lg bg-red-50 p-3 text-xs text-red-700">یکی از مقادیر حافظه خارج از محدوده مجاز است؛ موارد مشخص‌شده را اصلاح کنید.</div>
        @endif
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition">ذخیره تنظیمات حافظه</button>
    </form>

    <form method="POST" action="{{ route('admin.system.memory.reset') }}" class="mt-5 border-t border-slate-100 pt-4" onsubmit="return confirm('فقط خلاصه‌های ساخته‌شده پاک و در استفاده بعدی بازسازی می‌شوند؛ ادامه می‌دهید؟')">
        @csrf
        <button type="submit" class="text-xs font-semibold text-amber-700 hover:text-amber-800">پاک‌سازی و بازسازی تدریجی حافظه‌ها</button>
        <span class="mr-2 text-xs text-slate-400">پیام‌های اصلی کاربران حذف نمی‌شوند.</span>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
    <h3 class="font-semibold text-slate-800 mb-1">نرخ تبدیل هزینه هوش مصنوعی</h3>
    <p class="text-xs text-slate-400 mb-4">قیمت هر دلار به تومان برای تبدیل هزینه API و محاسبه سود تقریبی ماه شمسی. صفر = فقط نمایش هزینه دلاری.</p>
    <form method="POST" action="{{ route('admin.system.settings.save') }}" class="flex flex-wrap items-center gap-3">
        @csrf
        <input type="number" name="usd_to_toman_rate" value="{{ old('usd_to_toman_rate', $usdToTomanRate) }}" min="0" max="100000000" step="1"
               class="w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-400" dir="ltr">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg transition">ذخیره</button>
        <span class="text-xs text-slate-400">تومان برای هر دلار</span>
    </form>
    @error('usd_to_toman_rate')<p class="mt-2 text-red-500 text-xs">{{ $message }}</p>@enderror
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
