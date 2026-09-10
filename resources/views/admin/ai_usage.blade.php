@extends('layouts.admin')
@section('title', 'مصرف و هزینه هوش مصنوعی')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold text-slate-800">مصرف و هزینه هوش مصنوعی</h1><p class="mt-1 text-xs text-slate-500">هزینه‌ها با تعرفه ثبت‌شده در لحظه پاسخ محاسبه می‌شوند.</p></div>
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <div><label class="block text-xs text-slate-500">از</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input-field"></div>
        <div><label class="block text-xs text-slate-500">تا</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input-field"></div>
        <div><label class="block text-xs text-slate-500">مدل</label><select name="model" class="input-field"><option value="">همه</option>@foreach($models as $model)<option value="{{ $model }}" {{ request('model') === $model ? 'selected' : '' }}>{{ $model }}</option>@endforeach</select></div>
        <button class="btn-primary">اعمال</button>
    </form>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
    @foreach([
        ['درخواست', number_format((int)$summary->requests)],
        ['توکن ورودی', number_format((int)$summary->input_tokens)],
        ['توکن خروجی', number_format((int)$summary->output_tokens)],
        ['کل توکن', number_format((int)$summary->total_tokens)],
        ['هزینه دلار', '$'.number_format((float)$summary->cost_usd, 6)],
    ] as [$label,$value])
    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><div class="text-xs text-slate-500">{{ $label }}</div><div class="mt-2 text-xl font-bold text-slate-800" dir="ltr">{{ $value }}</div></div>
    @endforeach
</div>

<div class="mb-6 grid gap-6 xl:grid-cols-2">
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b p-4 font-semibold text-slate-800">به تفکیک مدل</div>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-3 text-right">مدل</th><th>درخواست</th><th>کل توکن</th><th>هزینه</th></tr></thead><tbody>@forelse($byModel as $row)<tr class="border-t"><td class="p-3"><div class="font-medium">{{ $row->used_model }}</div><div class="text-xs text-slate-400" dir="ltr">{{ $row->model_mapping }}</div></td><td class="text-center">{{ number_format($row->requests) }}</td><td class="text-center">{{ number_format($row->total_tokens) }}</td><td class="text-center" dir="ltr">${{ number_format($row->cost_usd, 6) }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">هنوز مصرفی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b p-4 font-semibold text-slate-800">مصرف روزانه</div>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-3 text-right">تاریخ</th><th>درخواست</th><th>کل توکن</th><th>هزینه</th></tr></thead><tbody>@forelse($byDay as $row)<tr class="border-t"><td class="p-3" dir="ltr">{{ $row->usage_date }}</td><td class="text-center">{{ number_format($row->requests) }}</td><td class="text-center">{{ number_format($row->total_tokens) }}</td><td class="text-center" dir="ltr">${{ number_format($row->cost_usd, 6) }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">داده‌ای وجود ندارد.</td></tr>@endforelse</tbody></table></div>
    </div>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
    <div class="border-b p-4 font-semibold text-slate-800">آخرین درخواست‌ها</div>
    <div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-right">زمان</th><th>مدل</th><th>مسیر</th><th>ورودی</th><th>Cache</th><th>خروجی</th><th>Reasoning</th><th>هزینه</th><th>نوع</th></tr></thead><tbody>@forelse($logs as $log)<tr class="border-t"><td class="p-3 whitespace-nowrap" dir="ltr">{{ $log->created_at }}</td><td class="text-center">{{ $log->used_model }}</td><td class="text-center">{{ $log->route }}</td><td class="text-center">{{ number_format($log->input_tokens) }}</td><td class="text-center">{{ number_format($log->cached_input_tokens) }}</td><td class="text-center">{{ number_format($log->output_tokens) }}</td><td class="text-center">{{ number_format($log->reasoning_tokens) }}</td><td class="text-center" dir="ltr">${{ number_format($log->estimated_cost_usd, 8) }}</td><td class="text-center"><span class="rounded-full px-2 py-1 {{ $log->usage_source === 'provider' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $log->usage_source === 'provider' ? 'قطعی API' : 'تخمینی' }}</span></td></tr>@empty<tr><td colspan="9" class="p-8 text-center text-slate-400">هنوز گزارشی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
    <div class="p-4">{{ $logs->links() }}</div>
</div>
@endsection
