@extends('layouts.admin')
@section('title', 'مدیریت مدل‌های AI')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">مدل‌های هوش مصنوعی</h1>
        <p class="text-slate-500 text-sm mt-1">مدیریت و پیکربندی مدل‌های AI</p>
    </div>
    <a href="{{ route('admin.ai-models.create') }}" class="btn-primary flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        مدل جدید
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs">
            <tr>
                <th class="px-4 py-3 text-right font-medium">ترتیب</th>
                <th class="px-4 py-3 text-right font-medium">کلید</th>
                <th class="px-4 py-3 text-right font-medium">نام</th>
                <th class="px-4 py-3 text-right font-medium">ارائه‌دهنده</th>
                <th class="px-4 py-3 text-right font-medium">فایل</th>
                <th class="px-4 py-3 text-right font-medium">پراکسی</th>
                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                <th class="px-4 py-3 text-right font-medium">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($models as $model)
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-4 py-3 text-slate-400">{{ $model->sort_order }}</td>
                <td class="px-4 py-3 font-mono text-xs text-indigo-600">{{ $model->key }}</td>
                <td class="px-4 py-3 font-semibold text-slate-800">{{ $model->name }}</td>
                <td class="px-4 py-3">
                    <span class="bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-full">{{ $model->provider }}</span>
                </td>
                <td class="px-4 py-3">
                    @if($model->supports_files)
                        <span class="text-emerald-500">✓ بله</span>
                    @else
                        <span class="text-slate-300">✕ خیر</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($model->use_proxy)
                        <span class="text-xs bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full">✓ فعال</span>
                    @else
                        <span class="text-slate-300 text-xs">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($model->is_active)
                        <span class="badge-active">فعال</span>
                    @else
                        <span class="badge-expired">غیرفعال</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex gap-1.5">
                        <a href="{{ route('admin.ai-models.edit', $model) }}"
                            class="px-2.5 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg text-xs font-medium">ویرایش</a>
                        <form method="POST" action="{{ route('admin.ai-models.destroy', $model) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf @method('DELETE')
                            <button class="px-2.5 py-1.5 bg-red-50 text-red-500 hover:bg-red-100 rounded-lg text-xs font-medium">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
