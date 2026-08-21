@extends('layouts.admin')
@section('title', 'افزودن مدل AI')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-800">افزودن مدل هوش مصنوعی</h1>
    <a href="{{ route('admin.ai-models.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">بازگشت</a>
</div>

<div class="max-w-3xl rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
    @if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.ai-models.store') }}" class="space-y-5">
        @csrf
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">کلید داخلی *</label><input name="key" value="{{ old('key') }}" required class="input-field font-mono" dir="ltr" placeholder="xai"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">ترتیب نمایش</label><input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">نام نمایشی *</label><input name="name" value="{{ old('name') }}" required class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">عنوان کوتاه</label><input name="title" value="{{ old('title') }}" class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">ارائه‌دهنده *</label><select name="provider" class="input-field">@foreach(['gapgpt','irangpu','deepseek','openai','custom'] as $provider)<option value="{{ $provider }}" {{ old('provider') === $provider ? 'selected' : '' }}>{{ strtoupper($provider) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">نام مدل API *</label><input name="model_mapping" value="{{ old('model_mapping') }}" required class="input-field font-mono" dir="ltr" placeholder="grok-4"></div>
        </div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-600">Endpoint کامل *</label><input type="url" name="endpoint" value="{{ old('endpoint') }}" required class="input-field font-mono" dir="ltr" placeholder="https://api.x.ai/v1/chat/completions"></div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-600">API Key</label><input type="password" name="api_key" value="{{ old('api_key') }}" class="input-field font-mono" dir="ltr" autocomplete="new-password"></div>

        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
            <h2 class="mb-3 font-semibold text-slate-800">مسیر اتصال</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">حالت مسیر *</label>
                    <select name="proxy_mode" class="input-field">
                        <option value="direct" {{ old('proxy_mode', 'direct') === 'direct' ? 'selected' : '' }}>فقط اتصال مستقیم</option>
                        <option value="auto" {{ old('proxy_mode') === 'auto' ? 'selected' : '' }}>خودکار: مستقیم، سپس پراکسی</option>
                        <option value="proxy" {{ old('proxy_mode') === 'proxy' ? 'selected' : '' }}>فقط پراکسی</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">پراکسی ترجیحی</label>
                    <select name="proxy_server_id" class="input-field">
                        <option value="">اولین پراکسی فعال بر اساس اولویت</option>
                        @foreach($proxyServers as $proxy)<option value="{{ $proxy->id }}" {{ (string) old('proxy_server_id') === (string) $proxy->id ? 'selected' : '' }}>{{ $proxy->name }} — {{ $proxy->displayAddress() }}{{ $proxy->is_active ? '' : ' (غیرفعال)' }}</option>@endforeach
                    </select>
                </div>
            </div>
            @if($proxyServers->isEmpty())<p class="mt-3 text-xs text-amber-700">ابتدا از صفحهٔ <a class="font-bold underline" href="{{ route('admin.ai-proxies.index') }}">مدیریت پراکسی‌ها</a> یک مسیر بسازید.</p>@endif
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">مهلت پاسخ (ثانیه)</label><input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', 120) }}" min="10" max="300" class="input-field"></div>
        </div>
        <div class="flex flex-wrap items-center gap-6 pt-2">
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="supports_files" value="1" {{ old('supports_files') ? 'checked' : '' }} class="h-4 w-4 accent-indigo-600">پشتیبانی از فایل</label>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="h-4 w-4 accent-indigo-600">مدل فعال باشد</label>
        </div>
        <div class="flex gap-3 border-t border-slate-100 pt-4"><button class="btn-primary">ذخیره مدل</button><a href="{{ route('admin.ai-models.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600">انصراف</a></div>
    </form>
</div>
@endsection
