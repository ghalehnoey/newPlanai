@extends('layouts.admin')
@section('title', 'ویرایش مدل AI')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-800">ویرایش مدل: <span class="text-indigo-600">{{ $aiModel->name }}</span></h1>
    <a href="{{ route('admin.ai-models.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">بازگشت</a>
</div>

<div class="max-w-3xl rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
    @if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.ai-models.update', $aiModel) }}" class="space-y-5">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">کلید داخلی</label><input value="{{ $aiModel->key }}" disabled class="input-field cursor-not-allowed bg-slate-50 font-mono text-slate-400" dir="ltr"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">ترتیب نمایش</label><input type="number" name="sort_order" value="{{ old('sort_order', $aiModel->sort_order) }}" class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">نام نمایشی *</label><input name="name" value="{{ old('name', $aiModel->name) }}" required class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">عنوان کوتاه</label><input name="title" value="{{ old('title', $aiModel->title) }}" class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">نام مدل API *</label><input name="model_mapping" value="{{ old('model_mapping', $aiModel->model_mapping) }}" required class="input-field font-mono" dir="ltr"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">مهلت پاسخ (ثانیه)</label><input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $aiModel->timeout_seconds ?? 120) }}" min="10" max="300" class="input-field"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">حداکثر توکن خروجی</label><input type="number" name="max_output_tokens" value="{{ old('max_output_tokens', $aiModel->max_output_tokens) }}" min="100" max="100000" class="input-field" placeholder="مثلاً 1800"><p class="mt-1 text-xs text-slate-500">خالی = پیش‌فرض سیستم</p></div>
        </div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-600">Endpoint کامل *</label><input type="url" name="endpoint" value="{{ old('endpoint', $aiModel->endpoint) }}" required class="input-field font-mono" dir="ltr"></div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-600">API Key <span class="font-normal text-slate-400">(برای حفظ کلید فعلی خالی بگذارید)</span></label><input type="password" name="api_key" class="input-field font-mono" dir="ltr" autocomplete="new-password"></div>

        @php($currentMode = old('proxy_mode', $aiModel->proxy_mode ?? ($aiModel->use_proxy ? 'proxy' : 'direct')))
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
            <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-slate-800">مسیر اتصال</h2><a href="{{ route('admin.ai-proxies.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">مدیریت پراکسی‌ها</a></div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">حالت مسیر *</label>
                    <select name="proxy_mode" class="input-field">
                        <option value="direct" {{ $currentMode === 'direct' ? 'selected' : '' }}>فقط اتصال مستقیم</option>
                        <option value="auto" {{ $currentMode === 'auto' ? 'selected' : '' }}>خودکار: مستقیم، سپس پراکسی</option>
                        <option value="proxy" {{ $currentMode === 'proxy' ? 'selected' : '' }}>فقط پراکسی</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">در حالت خودکار، خطای شبکه، 403، 408، 451 یا 5xx باعث امتحان پراکسی برای همین مدل می‌شود.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">پراکسی ترجیحی</label>
                    <select name="proxy_server_id" class="input-field">
                        <option value="">اولین پراکسی فعال بر اساس اولویت</option>
                        @foreach($proxyServers as $proxy)<option value="{{ $proxy->id }}" {{ (string) old('proxy_server_id', $aiModel->proxy_server_id) === (string) $proxy->id ? 'selected' : '' }}>{{ $proxy->name }} — {{ $proxy->displayAddress() }}{{ $proxy->is_active ? '' : ' (غیرفعال)' }}</option>@endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
            <h2 class="font-semibold text-slate-800">تعرفه مصرف (دلار به‌ازای یک میلیون توکن)</h2>
            <p class="mb-3 mt-1 text-xs text-slate-500">توکن‌های reasoning در خروجی هستند و دوباره هزینه جداگانه نمی‌گیرند.</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">توکن ورودی</label><input type="number" step="0.000001" min="0" name="input_price_per_million" value="{{ old('input_price_per_million', $aiModel->input_price_per_million ?? 0) }}" class="input-field" dir="ltr"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">ورودی Cache شده</label><input type="number" step="0.000001" min="0" name="cached_input_price_per_million" value="{{ old('cached_input_price_per_million', $aiModel->cached_input_price_per_million ?? 0) }}" class="input-field" dir="ltr"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">توکن خروجی</label><input type="number" step="0.000001" min="0" name="output_price_per_million" value="{{ old('output_price_per_million', $aiModel->output_price_per_million ?? 0) }}" class="input-field" dir="ltr"></div>
            </div>
        </div>

        <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
            <h2 class="font-semibold text-slate-800">سقف مصرف روزانه و fallback</h2>
            <p class="mb-3 mt-1 text-xs text-slate-500">با رسیدن به سقف دلار یا توکن، این مدل تا ابتدای روز بعد رد می‌شود و مدل فعال بعدی طبق ترتیب نمایش امتحان می‌شود. خالی یا صفر = نامحدود.</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">حداکثر هزینه روزانه (دلار)</label><input type="number" step="0.0001" min="0" name="daily_cost_limit_usd" value="{{ old('daily_cost_limit_usd', $aiModel->daily_cost_limit_usd) }}" class="input-field" dir="ltr" placeholder="5.00"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">حداکثر توکن روزانه</label><input type="number" step="1" min="0" name="daily_token_limit" value="{{ old('daily_token_limit', $aiModel->daily_token_limit) }}" class="input-field" dir="ltr" placeholder="1000000"></div>
                <div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold text-slate-600">مدل جایگزین در صورت خطا یا رسیدن به سقف</label><select name="fallback_model_id" class="input-field"><option value="">خودکار بر اساس ترتیب نمایش</option>@foreach($fallbackModels as $fallback)<option value="{{ $fallback->id }}" {{ (string)old('fallback_model_id', $aiModel->fallback_model_id) === (string)$fallback->id ? 'selected' : '' }}>{{ $fallback->name }} ({{ $fallback->key }}){{ $fallback->is_active ? '' : ' ـ غیرفعال' }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">در پایان اعتبار، خطای API/شبکه/پراکسی یا عبور از سقف دلار و توکن، این مدل اول امتحان می‌شود.</p></div>
            </div>
            @error('daily_cost_limit_usd')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap items-center gap-6 pt-2">
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="supports_files" value="1" {{ old('supports_files', $aiModel->supports_files) ? 'checked' : '' }} class="h-4 w-4 accent-indigo-600">پشتیبانی از فایل</label>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $aiModel->is_active) ? 'checked' : '' }} class="h-4 w-4 accent-indigo-600">مدل فعال باشد</label>
        </div>
        <div class="flex gap-3 border-t border-slate-100 pt-4"><button class="btn-primary">ذخیره تغییرات</button><a href="{{ route('admin.ai-models.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600">انصراف</a></div>
    </form>
</div>
@endsection
