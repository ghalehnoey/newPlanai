@extends('layouts.admin')
@section('title', 'مدیریت مدل‌های AI')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">مدل‌های هوش مصنوعی</h1>
        <p class="mt-1 text-sm text-slate-500">مدیریت مدل، مسیر مستقیم و fallback پراکسی</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.ai-proxies.index') }}" class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">مدیریت پراکسی‌ها</a>
        <a href="{{ route('admin.ai-models.create') }}" class="btn-primary">مدل جدید</a>
    </div>
</div>

<div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
    <table class="w-full min-w-[900px] text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500">
            <tr>
                <th class="px-4 py-3 text-right font-medium">ترتیب</th>
                <th class="px-4 py-3 text-right font-medium">کلید</th>
                <th class="px-4 py-3 text-right font-medium">نام</th>
                <th class="px-4 py-3 text-right font-medium">ارائه‌دهنده</th>
                <th class="px-4 py-3 text-right font-medium">فایل</th>
                <th class="px-4 py-3 text-right font-medium">مسیر اتصال</th>
                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                <th class="px-4 py-3 text-right font-medium">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($models as $model)
            @php($mode = $model->proxy_mode ?? ($model->use_proxy ? 'proxy' : 'direct'))
            <tr class="hover:bg-slate-50/50">
                <td class="px-4 py-3 text-slate-400">{{ $model->sort_order }}</td>
                <td class="px-4 py-3 font-mono text-xs text-indigo-600">{{ $model->key }}</td>
                <td class="px-4 py-3 font-semibold text-slate-800">{{ $model->name }}<div class="font-mono text-[11px] font-normal text-slate-400">{{ $model->model_mapping }}</div></td>
                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $model->provider }}</span></td>
                <td class="px-4 py-3">{!! $model->supports_files ? '<span class="text-emerald-600">✓ بله</span>' : '<span class="text-slate-300">—</span>' !!}</td>
                <td class="px-4 py-3">
                    @if($mode === 'auto')
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">خودکار</span>
                    @elseif($mode === 'proxy')
                        <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700">فقط پراکسی</span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">مستقیم</span>
                    @endif
                    @if($model->proxyServer)<div class="mt-1 text-[11px] text-slate-500">{{ $model->proxyServer->name }}</div>@endif
                </td>
                <td class="px-4 py-3">@if($model->is_active)<span class="badge-active">فعال</span>@else<span class="badge-expired">غیرفعال</span>@endif</td>
                <td class="px-4 py-3">
                    <div class="flex gap-1.5">
                        <a href="{{ route('admin.ai-models.edit', $model) }}" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-100">ویرایش</a>
                        <form method="POST" action="{{ route('admin.ai-models.destroy', $model) }}" onsubmit="return confirm('مدل حذف شود؟')">@csrf @method('DELETE')<button class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-500 hover:bg-red-100">حذف</button></form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
