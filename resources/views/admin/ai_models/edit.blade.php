@extends('layouts.admin')
@section('title', 'ویرایش مدل AI')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-800">ویرایش مدل: <span class="text-indigo-600">{{ $aiModel->name }}</span></h1>
    <a href="{{ route('admin.ai-models.index') }}" class="px-4 py-2 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-50">بازگشت</a>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 max-w-2xl">
    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.ai-models.update', $aiModel) }}" class="space-y-4">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">کلید (key)</label>
                <input type="text" value="{{ $aiModel->key }}" disabled class="input-field bg-slate-50 text-slate-400 font-mono cursor-not-allowed">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">ترتیب نمایش</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $aiModel->sort_order) }}" class="input-field">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">نام نمایشی *</label>
                <input type="text" name="name" value="{{ old('name', $aiModel->name) }}" required class="input-field">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">عنوان کوتاه</label>
                <input type="text" name="title" value="{{ old('title', $aiModel->title) }}" class="input-field">
            </div>
        </div>
        <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1">Endpoint *</label>
            <input type="url" name="endpoint" value="{{ old('endpoint', $aiModel->endpoint) }}" required class="input-field font-mono" dir="ltr">
        </div>
        <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1">API Key (اگر خالی بماند تغییر نمی‌کند)</label>
            <input type="text" name="api_key" class="input-field font-mono" dir="ltr" placeholder="برای تغییر کلید وارد کنید، وگرنه خالی بگذارید">
        </div>
        <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1">Model Mapping *</label>
            <input type="text" name="model_mapping" value="{{ old('model_mapping', $aiModel->model_mapping) }}" required class="input-field font-mono" dir="ltr">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Proxy URL</label>
                <input type="text" name="proxy_url" value="{{ old('proxy_url', $aiModel->proxy_url) }}" class="input-field font-mono" dir="ltr">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Timeout (ثانیه)</label>
                <input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $aiModel->timeout_seconds ?? 120) }}" class="input-field">
            </div>
        </div>
        <div class="flex items-center gap-6 pt-2">
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" name="supports_files" value="1" {{ old('supports_files', $aiModel->supports_files) ? 'checked' : '' }} class="w-4 h-4 accent-indigo-600">
                پشتیبانی از فایل
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" name="use_proxy" value="1" {{ old('use_proxy', $aiModel->use_proxy) ? 'checked' : '' }} class="w-4 h-4 accent-indigo-600">
                استفاده از پراکسی
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $aiModel->is_active) ? 'checked' : '' }} class="w-4 h-4 accent-indigo-600">
                مدل فعال باشد
            </label>
        </div>
        <div class="flex gap-3 pt-2 border-t border-slate-100">
            <button type="submit" class="btn-primary">ذخیره تغییرات</button>
            <a href="{{ route('admin.ai-models.index') }}" class="px-4 py-2 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-50">انصراف</a>
        </div>
    </form>
</div>
@endsection
