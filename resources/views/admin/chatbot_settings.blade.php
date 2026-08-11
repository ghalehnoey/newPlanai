@extends('layouts.admin')
@section('title', 'تنظیمات چت‌بات')

@section('content')
<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">تنظیمات چت‌بات</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">ویرایش پرامپت سیستمی و محدوده پاسخگویی هوش مصنوعی</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- Main Form --}}
    <div class="xl:col-span-2 space-y-5">
        <form method="POST" action="{{ route('admin.chatbot-settings.save') }}" id="promptForm">
            @csrf

            {{-- System Prompt --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-sm font-bold text-slate-700 dark:text-slate-200">پرامپت سیستمی (System Prompt)</label>
                    <span class="text-xs text-slate-400" id="charCount">0 کاراکتر</span>
                </div>
                <textarea name="system_prompt" id="systemPrompt" rows="22"
                    class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-y bg-slate-50 dark:bg-slate-900 dark:text-slate-100 leading-relaxed"
                    dir="rtl" placeholder="پرامپت سیستمی چت‌بات را اینجا وارد کنید...">{{ old('system_prompt', $currentPrompt) }}</textarea>
                @error('system_prompt')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-400 mt-2">این متن به عنوان دستورالعمل اولیه به مدل هوش مصنوعی ارسال می‌شود.</p>
            </div>

            {{-- Topic Restriction --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <label class="text-sm font-bold text-slate-700 dark:text-slate-200 block mb-1">محدوده موضوعی (اختیاری)</label>
                <p class="text-xs text-slate-400 mb-3">موضوعاتی که ربات باید به آن‌ها پاسخ دهد یا ندهد را توضیح دهید.</p>
                <textarea name="topic_restriction" rows="5"
                    class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-y bg-slate-50 dark:bg-slate-900 dark:text-slate-100"
                    dir="rtl" placeholder="مثال: فقط به سوالات حقوقی پاسخ بدهد. سوالات پزشکی، برنامه‌نویسی و غیرمرتبط را رد کند.">{{ old('topic_restriction', $topicRestriction) }}</textarea>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                    ذخیره تنظیمات
                </button>
                <button type="button" onclick="document.getElementById('resetForm').submit()"
                    class="bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    بازگشت به پیش‌فرض
                </button>
                <button type="button" onclick="copyPrompt()"
                    class="bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    کپی پرامپت
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.chatbot-settings.reset') }}" id="resetForm">
            @csrf
        </form>
    </div>

    {{-- Sidebar Info --}}
    <div class="space-y-5">
        {{-- Tips --}}
        <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl border border-indigo-100 dark:border-indigo-800 p-5">
            <h3 class="text-sm font-bold text-indigo-800 dark:text-indigo-300 mb-3">راهنمای نگارش پرامپت</h3>
            <ul class="text-xs text-indigo-700 dark:text-indigo-400 space-y-2">
                <li class="flex gap-2"><span class="text-indigo-400">◆</span> نقش و تخصص چت‌بات را مشخص کنید</li>
                <li class="flex gap-2"><span class="text-indigo-400">◆</span> موضوعاتی که باید پاسخ دهد را ذکر کنید</li>
                <li class="flex gap-2"><span class="text-indigo-400">◆</span> محدودیت‌ها را واضح بنویسید</li>
                <li class="flex gap-2"><span class="text-indigo-400">◆</span> سبک پاسخ (رسمی/غیررسمی) را تعیین کنید</li>
                <li class="flex gap-2"><span class="text-indigo-400">◆</span> از Markdown برای ساختاردهی استفاده کنید</li>
            </ul>
        </div>

        {{-- Current Status --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">وضعیت فعلی</h3>
            <div class="space-y-2 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex justify-between">
                    <span>طول پرامپت:</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300" id="promptLen">{{ mb_strlen($currentPrompt) }} کاراکتر</span>
                </div>
                <div class="flex justify-between">
                    <span>منبع پرامپت:</span>
                    <span class="font-semibold {{ strlen(trim($currentPrompt)) > 0 ? 'text-green-600' : 'text-amber-600' }}">
                        {{ strlen(trim($currentPrompt)) > 0 ? 'دیتابیس' : 'پیش‌فرض' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>محدوده موضوعی:</span>
                    <span class="font-semibold {{ $topicRestriction ? 'text-green-600' : 'text-slate-400' }}">
                        {{ $topicRestriction ? 'تنظیم شده' : 'تنظیم نشده' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Default Prompt Preview --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">پرامپت پیش‌فرض</h3>
                <button onclick="loadDefault()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">بارگذاری</button>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 max-h-32 overflow-y-auto leading-relaxed border border-slate-100 dark:border-slate-700 rounded-lg p-2 bg-slate-50 dark:bg-slate-900 font-mono">
                {{ mb_substr($defaultPrompt, 0, 300) }}...
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const defaultPrompt = {{ Js::from($defaultPrompt) }};
const textarea = document.getElementById('systemPrompt');
const charCount = document.getElementById('charCount');
const promptLen = document.getElementById('promptLen');

function updateCount() {
    const len = textarea.value.length;
    charCount.textContent = len.toLocaleString('fa') + ' کاراکتر';
    if (promptLen) promptLen.textContent = len.toLocaleString('fa') + ' کاراکتر';
}

textarea.addEventListener('input', updateCount);
updateCount();

function loadDefault() {
    if (confirm('پرامپت فعلی با پیش‌فرض جایگزین می‌شود. ادامه می‌دهید؟')) {
        textarea.value = defaultPrompt;
        updateCount();
    }
}

function copyPrompt() {
    navigator.clipboard.writeText(textarea.value).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = '✓ کپی شد';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}
</script>
@endpush
@endsection
