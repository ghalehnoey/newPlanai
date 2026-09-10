@extends('layouts.admin')
@section('title', 'خطاهای هوش مصنوعی')

@section('content')
@php
    $labels = ['provider_credit'=>'اعتبار یا سهمیه ارائه‌دهنده تمام شده', 'authentication'=>'کلید API نامعتبر', 'access_blocked'=>'دسترسی مستقیم یا پراکسی مسدود', 'rate_limit'=>'محدودیت تعداد درخواست', 'timeout'=>'مهلت پاسخ تمام شده', 'network'=>'خطای شبکه یا پراکسی', 'provider_error'=>'خطای سرویس‌دهنده', 'invalid_request'=>'درخواست نامعتبر برای API', 'daily_cost_limit'=>'سقف دلاری روزانه مدل', 'daily_token_limit'=>'سقف توکن روزانه مدل', 'budget_pricing_missing'=>'تعرفه مدل برای سقف دلاری ناقص است', 'unknown'=>'خطای نامشخص'];
@endphp
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-2xl font-bold text-slate-800">خطاهای هوش مصنوعی</h1><p class="mt-1 text-xs text-slate-500">متن سؤال، فایل و API Key در این گزارش ذخیره نمی‌شوند.</p></div>
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <div><label class="block text-xs text-slate-500">نوع خطا</label><select name="category" class="input-field"><option value="">همه</option>@foreach($categories as $category)<option value="{{ $category }}" {{ request('category') === $category ? 'selected' : '' }}>{{ $labels[$category] ?? $category }}</option>@endforeach</select></div>
        <div><label class="block text-xs text-slate-500">مدل</label><select name="model" class="input-field"><option value="">همه</option>@foreach($models as $model)<option value="{{ $model }}" {{ request('model') === $model ? 'selected' : '' }}>{{ $model }}</option>@endforeach</select></div>
        <button class="btn-primary">اعمال</button>
    </form>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @forelse($summary as $item)<div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><div class="text-xs text-slate-500">{{ $labels[$item->category] ?? $item->category }}</div><div class="mt-2 text-2xl font-bold text-slate-800">{{ number_format($item->total) }}</div><div class="text-xs text-slate-400">۲۴ ساعت اخیر</div></div>@empty<div class="col-span-full rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">در ۲۴ ساعت اخیر خطایی ثبت نشده است.</div>@endforelse
</div>

<div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-right">زمان</th><th>مدل</th><th>نوع</th><th>HTTP</th><th>مسیر</th><th>مرحله</th><th class="text-right">شرح امن</th><th>Request ID</th></tr></thead><tbody>
    @forelse($logs as $log)<tr class="border-t"><td class="p-3 whitespace-nowrap" dir="ltr">{{ $log->created_at }}</td><td class="text-center">{{ $log->model_key ?: '—' }}</td><td class="p-3 text-center"><span class="rounded-full bg-amber-50 px-2 py-1 text-amber-700">{{ $labels[$log->category] ?? $log->category }}</span></td><td class="text-center" dir="ltr">{{ $log->http_status ?: '—' }}</td><td class="text-center">{{ $log->route ?: '—' }}</td><td class="text-center">{{ $log->stage ?: '—' }}</td><td class="max-w-md p-3 text-slate-600">{{ $log->message }}</td><td class="p-3 font-mono text-[10px] text-slate-400">{{ $log->request_id ?: '—' }}</td></tr>
    @empty<tr><td colspan="8" class="p-10 text-center text-slate-400">خطایی با این فیلتر وجود ندارد.</td></tr>@endforelse
    </tbody></table></div><div class="p-4">{{ $logs->links() }}</div>
</div>
@endsection
