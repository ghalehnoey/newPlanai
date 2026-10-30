@extends('layouts.admin')
@section('title', 'گزارش بازه‌ای هزینه هوش مصنوعی')

@section('content')
@php
    $chartColors = ['#f97316', '#14b8a6', '#6366f1', '#e11d48', '#8b5cf6', '#0ea5e9', '#84cc16', '#f59e0b'];
    $totalCost = (float) $summary->cost_usd;
    $averageDailyCost = $rangeDays > 0 ? $totalCost / $rangeDays : 0;
    $todayJalali = \Morilog\Jalali\Jalalian::fromCarbon(now('Asia/Tehran'));
@endphp

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">گزارش بازه‌ای هزینه هوش مصنوعی</h1>
        <p class="mt-1 text-xs text-slate-500">مقایسه هزینه مدل‌ها و هزینه کل در بازه انتخابی ـ روزها براساس ساعت تهران</p>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div><label class="mb-1 block text-xs text-slate-500">از تاریخ شمسی</label><input type="text" name="from" value="{{ $fromJalali }}" class="input-field" data-jalali-date required></div>
        <div><label class="mb-1 block text-xs text-slate-500">تا تاریخ شمسی</label><input type="text" name="to" value="{{ $toJalali }}" class="input-field" data-jalali-date required></div>
        <div><label class="mb-1 block text-xs text-slate-500">مدل هوش مصنوعی</label><select name="model" class="input-field"><option value="">همه مدل‌ها</option>@foreach($models as $model)<option value="{{ $model }}" {{ request('model') === $model ? 'selected' : '' }}>{{ $model }}</option>@endforeach</select></div>
        <button class="btn-primary">ساخت گزارش</button>
    </form>
</div>

<div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
    <span class="text-slate-500">بازه سریع:</span>
    <a class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 hover:bg-slate-200" href="{{ route('admin.ai-usage', ['from' => $todayJalali->format('Y/m/d'), 'to' => $todayJalali->format('Y/m/d'), 'model' => request('model')]) }}">امروز</a>
    <a class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 hover:bg-slate-200" href="{{ route('admin.ai-usage', ['from' => \Morilog\Jalali\Jalalian::fromCarbon(now('Asia/Tehran')->subDays(6))->format('Y/m/d'), 'to' => $todayJalali->format('Y/m/d'), 'model' => request('model')]) }}">۷ روز</a>
    <a class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 hover:bg-slate-200" href="{{ route('admin.ai-usage', ['from' => \Morilog\Jalali\Jalalian::fromCarbon(now('Asia/Tehran')->subDays(29))->format('Y/m/d'), 'to' => $todayJalali->format('Y/m/d'), 'model' => request('model')]) }}">۳۰ روز</a>
    <span class="mr-auto text-slate-400">{{ number_format($rangeDays) }} روز ـ حداکثر ۳۶۶ روز</span>
</div>

<div class="mb-6 grid gap-4 xl:grid-cols-[2fr_1fr]">
    <div class="rounded-3xl border border-orange-100 bg-gradient-to-br from-orange-50 via-white to-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><div class="text-sm font-medium text-slate-500">هزینه کل بازه</div><div class="mt-2 text-4xl font-black text-slate-900" dir="ltr">${{ number_format($totalCost, 6) }}</div><div class="mt-2 text-xs text-slate-400">میانگین روزانه <span dir="ltr">${{ number_format($averageDailyCost, 6) }}</span></div></div>
            <div class="rounded-2xl bg-white/80 px-4 py-3 text-left shadow-sm"><div class="text-xs text-slate-400">بازه شمسی گزارش</div><div class="mt-1 font-semibold text-slate-700" dir="ltr">{{ $fromJalali }} تا {{ $toJalali }}</div></div>
        </div>
        <div class="relative mt-6 h-80 w-full" id="aiCostChartWrap">
            <canvas id="aiCostChart" class="h-full w-full"></canvas>
            <div id="aiCostTooltip" class="pointer-events-none absolute hidden min-w-40 rounded-xl border border-slate-200 bg-white p-3 text-xs shadow-xl"></div>
            @if($byDay->isEmpty())<div class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">در این بازه مصرفی ثبت نشده است.</div>@endif
        </div>
        <div class="mt-4 flex flex-wrap justify-center gap-x-5 gap-y-2">
            @foreach($byModel as $index => $row)
                <div class="flex items-center gap-2 text-xs"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $chartColors[$index % count($chartColors)] }}"></span><span class="text-slate-600">{{ $row->used_model }}</span><strong dir="ltr">${{ number_format($row->cost_usd, 4) }}</strong></div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
        <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">کل توکن</div><div class="mt-2 text-3xl font-black text-slate-800">{{ number_format((int)$summary->total_tokens) }}</div><div class="mt-2 text-xs text-slate-400">ورودی {{ number_format((int)$summary->input_tokens) }} ـ خروجی {{ number_format((int)$summary->output_tokens) }}</div></div>
        <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">تعداد درخواست</div><div class="mt-2 text-3xl font-black text-slate-800">{{ number_format((int)$summary->requests) }}</div><div class="mt-2 text-xs text-slate-400">میانگین {{ number_format($rangeDays > 0 ? $summary->requests / $rangeDays : 0, 1) }} درخواست در روز</div></div>
        <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">هزینه متوسط هر درخواست</div><div class="mt-2 text-3xl font-black text-slate-800" dir="ltr">${{ number_format($summary->requests > 0 ? $totalCost / $summary->requests : 0, 6) }}</div><div class="mt-2 text-xs text-slate-400">بودجه روزانه: <span dir="ltr">{{ $dailyBudgetUsd > 0 ? '$'.number_format($dailyBudgetUsd, 2) : 'نامحدود' }}</span></div></div>
    </div>
</div>

<div class="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-bold text-slate-800">هزینه هر مدل در بازه انتخابی</h2><p class="mt-1 text-xs text-slate-400">مبلغ و سهم هر هوش مصنوعی از هزینه کل</p></div>@if(request('model'))<a href="{{ route('admin.ai-usage', ['from' => $fromJalali, 'to' => $toJalali]) }}" class="text-xs font-semibold text-indigo-700">نمایش همه مدل‌ها</a>@endif</div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($byModel as $index => $row)
            @php $modelPercent = $totalCost > 0 ? ((float)$row->cost_usd / $totalCost) * 100 : 0; @endphp
            <div class="rounded-2xl border border-slate-100 p-4">
                <div class="flex items-start justify-between gap-2"><div><div class="font-semibold text-slate-800">{{ $row->used_model }}</div><div class="mt-1 text-[11px] text-slate-400" dir="ltr">{{ $row->model_mapping }}</div></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ number_format($modelPercent, 1) }}٪</span></div>
                <div class="mt-4 flex items-end justify-between"><strong class="text-2xl text-slate-900" dir="ltr">${{ number_format($row->cost_usd, 6) }}</strong><span class="text-xs text-slate-400">{{ number_format($row->requests) }} درخواست</span></div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" style="width: {{ min(100, $modelPercent) }}%; background: {{ $chartColors[$index % count($chartColors)] }}"></div></div>
                <div class="mt-2 text-xs text-slate-400">{{ number_format($row->total_tokens) }} توکن</div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center text-slate-400">برای بازه انتخابی هزینه‌ای ثبت نشده است.</div>
        @endforelse
    </div>
</div>

<div class="mb-6 overflow-hidden rounded-3xl border border-indigo-100 bg-white shadow-sm">
    <div class="border-b border-indigo-100 bg-indigo-50/60 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-800">گزارش عبور خودکار به مدل دوم</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">وقتی هزینه روزانه مدل اصلی به سقف برسد، درخواست بعدی قبل از ارسال به مدل دوم منتقل می‌شود؛ روز مالی براساس ساعت تهران محاسبه می‌شود.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @forelse($budgetRules as $rule)
                    <span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm">
                        {{ $rule->name }}: <span dir="ltr">${{ number_format((float)$rule->daily_cost_limit_usd, 2) }}</span>
                        ← {{ $rule->fallbackModel?->name ?? 'بدون مدل جایگزین' }}
                    </span>
                @empty
                    <span class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700">قانون سقف فعالی وجود ندارد</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-100 p-4"><div class="text-xs text-slate-500">دفعات رسیدن به سقف در بازه</div><div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($budgetSwitchSummary->attempts) }}</div></div>
        <div class="rounded-2xl border border-slate-100 p-4"><div class="text-xs text-slate-500">انتقال موفق به مدل دوم</div><div class="mt-2 text-3xl font-black text-emerald-700">{{ number_format($budgetSwitchSummary->successful) }}</div></div>
        <div class="rounded-2xl border border-slate-100 p-4"><div class="text-xs text-slate-500">هزینه مدل دوم پس از انتقال</div><div class="mt-2 text-3xl font-black text-slate-800" dir="ltr">${{ number_format($budgetSwitchSummary->fallback_cost_usd, 6) }}</div></div>
    </div>

    <div class="overflow-x-auto border-t border-slate-100">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-right">زمان شمسی</th><th>مدل اصلی</th><th>مدل دوم</th><th>توکن مدل دوم</th><th>هزینه مدل دوم</th><th>نتیجه</th></tr></thead>
            <tbody>
            @forelse($budgetSwitches as $switch)
                <tr class="border-t">
                    <td class="p-3 whitespace-nowrap" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($switch->created_at)->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td>
                    <td class="text-center">{{ $switch->source_model }}</td>
                    <td class="text-center">{{ $switch->target_model ?? '—' }}</td>
                    <td class="text-center">{{ number_format((int)($switch->total_tokens ?? 0)) }}</td>
                    <td class="text-center" dir="ltr">${{ number_format((float)($switch->fallback_cost_usd ?? 0), 8) }}</td>
                    <td class="text-center"><span class="rounded-full px-2 py-1 {{ $switch->target_model ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $switch->target_model ? 'موفق' : 'بدون پاسخ ثبت‌شده' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-slate-400">در بازه انتخابی هنوز عبور از سقف ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<details class="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm" open>
    <summary class="cursor-pointer border-b p-4 font-semibold text-slate-800">جزئیات روزانه</summary>
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-3 text-right">تاریخ شمسی</th><th>درخواست</th><th>کل توکن</th><th>هزینه کل روز</th><th class="min-w-36">مصرف بودجه</th></tr></thead><tbody>@forelse($byDay as $row)@php $rowCost = (float)$row->cost_usd; $rowPercent = $dailyBudgetUsd > 0 ? ($rowCost / $dailyBudgetUsd) * 100 : 0; $rowBar = min(100, ($rowCost / $dailyScaleUsd) * 100); $rowColor = $rowPercent >= 90 ? 'bg-rose-500' : ($rowPercent >= 70 ? 'bg-amber-500' : 'bg-emerald-500'); @endphp<tr class="border-t"><td class="p-3" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($row->usage_date))->format('Y/m/d') }}</td><td class="text-center">{{ number_format($row->requests) }}</td><td class="text-center">{{ number_format($row->total_tokens) }}</td><td class="text-center font-semibold" dir="ltr">${{ number_format($rowCost, 6) }}</td><td class="px-3"><div class="mb-1 text-center text-[10px] text-slate-500">{{ $dailyBudgetUsd > 0 ? number_format($rowPercent, 1).'٪' : 'نسبی' }}</div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $rowColor }}" style="width: {{ $rowBar }}%"></div></div></td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-slate-400">داده‌ای وجود ندارد.</td></tr>@endforelse</tbody></table></div>
</details>

<details class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
    <summary class="cursor-pointer border-b p-4 font-semibold text-slate-800">ریز آخرین درخواست‌ها</summary>
    <div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3 text-right">زمان شمسی</th><th>مدل</th><th>مسیر</th><th>ورودی</th><th>Cache</th><th>خروجی</th><th>Reasoning</th><th>هزینه</th><th>نوع</th></tr></thead><tbody>@forelse($logs as $log)<tr class="border-t"><td class="p-3 whitespace-nowrap" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td><td class="text-center">{{ $log->used_model }}</td><td class="text-center">{{ $log->route }}</td><td class="text-center">{{ number_format($log->input_tokens) }}</td><td class="text-center">{{ number_format($log->cached_input_tokens) }}</td><td class="text-center">{{ number_format($log->output_tokens) }}</td><td class="text-center">{{ number_format($log->reasoning_tokens) }}</td><td class="text-center" dir="ltr">${{ number_format($log->estimated_cost_usd, 8) }}</td><td class="text-center"><span class="rounded-full px-2 py-1 {{ $log->usage_source === 'provider' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $log->usage_source === 'provider' ? 'قطعی API' : 'تخمینی' }}</span></td></tr>@empty<tr><td colspan="9" class="p-8 text-center text-slate-400">هنوز گزارشی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
    <div class="p-4">{{ $logs->links() }}</div>
</details>

@push('scripts')
<script>
(() => {
    const data = {{ Js::from($chartData) }};
    const colors = {{ Js::from($chartColors) }};
    const canvas = document.getElementById('aiCostChart');
    const wrap = document.getElementById('aiCostChartWrap');
    const tooltip = document.getElementById('aiCostTooltip');
    if (!canvas || !wrap || !data.labels.length || !data.series.length) return;
    const ctx = canvas.getContext('2d');
    let state = null;

    function money(value) { return '$' + Number(value).toFixed(value >= 1 ? 3 : 5); }
    function draw() {
        const width = Math.max(320, wrap.clientWidth);
        const height = Math.max(260, wrap.clientHeight);
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, width, height);
        const pad = {left: 58, right: 14, top: 18, bottom: 38};
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;
        const totals = data.labels.map((_, i) => data.series.reduce((sum, series) => sum + Number(series.values[i] || 0), 0));
        const maxValue = Math.max(...totals, 0.000001) * 1.12;
        const slot = plotW / data.labels.length;
        const barWidth = Math.max(2, Math.min(28, slot * 0.68));
        ctx.font = '11px sans-serif'; ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        for (let line = 0; line <= 4; line++) {
            const y = pad.top + (plotH * line / 4); const value = maxValue * (1 - line / 4);
            ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(width - pad.right, y); ctx.stroke();
            ctx.fillStyle = '#94a3b8'; ctx.fillText(money(value), pad.left - 7, y);
        }
        data.labels.forEach((_, index) => {
            const x = pad.left + slot * index + slot / 2; let bottom = pad.top + plotH;
            data.series.forEach((series, seriesIndex) => {
                const value = Number(series.values[index] || 0); if (value <= 0) return;
                const segmentHeight = value / maxValue * plotH; ctx.fillStyle = colors[seriesIndex % colors.length];
                ctx.fillRect(x - barWidth / 2, bottom - segmentHeight, barWidth, segmentHeight); bottom -= segmentHeight;
            });
        });
        const labelStep = Math.max(1, Math.ceil(data.labels.length / 7));
        ctx.fillStyle = '#64748b'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        data.jalaliLabels.forEach((label, index) => { if (index % labelStep !== 0 && index !== data.labels.length - 1) return; const x = pad.left + slot * index + slot / 2; ctx.fillText(label, x, pad.top + plotH + 10); });
        state = {pad, slot, totals};
    }
    canvas.addEventListener('mousemove', event => {
        if (!state) return;
        const rect = canvas.getBoundingClientRect(); const x = event.clientX - rect.left;
        const index = Math.floor((x - state.pad.left) / state.slot);
        if (index < 0 || index >= data.labels.length) { tooltip.classList.add('hidden'); return; }
        tooltip.replaceChildren();
        const title = document.createElement('div'); title.className = 'mb-2 font-bold text-slate-800'; title.textContent = data.jalaliLabels[index]; tooltip.appendChild(title);
        data.series.forEach((series, seriesIndex) => { const value = Number(series.values[index] || 0); if (value <= 0) return; const line = document.createElement('div'); line.className = 'flex items-center justify-between gap-5 py-0.5'; const name = document.createElement('span'); name.textContent = series.name; name.style.color = colors[seriesIndex % colors.length]; const amount = document.createElement('strong'); amount.dir = 'ltr'; amount.textContent = money(value); line.append(name, amount); tooltip.appendChild(line); });
        const total = document.createElement('div'); total.className = 'mt-2 border-t pt-2 font-bold'; total.dir = 'ltr'; total.textContent = 'Total: ' + money(state.totals[index]); tooltip.appendChild(total);
        tooltip.classList.remove('hidden'); const tipWidth = tooltip.offsetWidth;
        tooltip.style.left = Math.max(4, Math.min(wrap.clientWidth - tipWidth - 4, x - tipWidth / 2)) + 'px'; tooltip.style.top = '8px';
    });
    canvas.addEventListener('mouseleave', () => tooltip.classList.add('hidden'));
    new ResizeObserver(draw).observe(wrap); draw();
})();
</script>
@endpush
@endsection
