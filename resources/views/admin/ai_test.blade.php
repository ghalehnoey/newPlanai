@extends('layouts.admin')
@section('title', 'تست اتصال هوش مصنوعی')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">تست اتصال مدل‌ها</h1>
        <p class="mt-1 text-sm text-slate-500">تشخیص جداگانهٔ endpoint، کلید API، محدودیت IP و مسیر پراکسی</p>
    </div>
    <a href="{{ route('admin.ai-proxies.index') }}" class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">مدیریت پراکسی‌ها</a>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-800">انتخاب مسیر تست</h2>
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">مدل</label>
                    <select id="model_key" class="input-field">
                        @foreach($models as $model)
                        <option value="{{ $model->key }}">{{ $model->name }} — {{ $model->model_mapping }}{{ $model->is_active ? '' : ' (غیرفعال)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">پراکسی</label>
                    <select id="proxy_server_id" class="input-field" {{ $proxyServers->isEmpty() ? 'disabled' : '' }}>
                        @forelse($proxyServers as $proxy)
                        <option value="{{ $proxy->id }}">{{ $proxy->name }} — {{ $proxy->displayAddress() }}</option>
                        @empty
                        <option value="">پراکسی فعال وجود ندارد</option>
                        @endforelse
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="runTest('direct')" class="rounded-xl bg-slate-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">تست مستقیم</button>
                    <button onclick="runTest('proxy')" {{ $proxyServers->isEmpty() ? 'disabled' : '' }} class="rounded-xl bg-violet-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40">تست پراکسی</button>
                </div>
                <button onclick="runBothTests()" {{ $proxyServers->isEmpty() ? 'disabled' : '' }} class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">مقایسهٔ مستقیم و پراکسی</button>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-6 text-amber-900">
            <strong>تفسیر سریع:</strong><br>
            HTTP 403 معمولاً محدودیت IP/منطقه است.<br>
            HTTP 401 مربوط به کلید یا مجوز API است.<br>
            HTTP 429 مربوط به اعتبار یا rate limit است.<br>
            خطای 7 یعنی سرویس/پورت پراکسی در دسترس نیست.
        </div>
    </div>

    <div class="space-y-5 lg:col-span-2">
        <div id="result_box" class="hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-800">نتیجه تست</h2>
            <div id="result_content"></div>
        </div>
        <div id="compare_box" class="hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-800">مقایسهٔ مسیرها</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><div class="mb-2 text-xs font-bold text-slate-500">مستقیم</div><div id="compare_direct"></div></div>
                <div><div class="mb-2 text-xs font-bold text-slate-500">پراکسی</div><div id="compare_proxy"></div></div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-800">پیکربندی مدل‌ها</h2>
            <table class="w-full min-w-[650px] text-xs">
                <thead><tr class="border-b border-slate-100 text-slate-500"><th class="pb-2 text-right">مدل</th><th class="pb-2 text-right">Endpoint</th><th class="pb-2 text-center">حالت مسیر</th><th class="pb-2 text-center">پراکسی ترجیحی</th><th class="pb-2 text-center">وضعیت</th></tr></thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($models as $model)
                    @php($mode = $model->proxy_mode ?? ($model->use_proxy ? 'proxy' : 'direct'))
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="document.getElementById('model_key').value='{{ $model->key }}'">
                        <td class="py-2"><strong class="text-slate-800">{{ $model->name }}</strong><div class="font-mono text-slate-400">{{ $model->model_mapping }}</div></td>
                        <td class="max-w-[240px] truncate py-2 font-mono text-slate-500" dir="ltr" title="{{ $model->endpoint }}">{{ $model->endpoint }}</td>
                        <td class="py-2 text-center">{{ ['direct' => 'مستقیم', 'auto' => 'خودکار', 'proxy' => 'فقط پراکسی'][$mode] ?? $mode }}</td>
                        <td class="py-2 text-center text-slate-500">{{ $model->proxyServer?->name ?? 'بر اساس اولویت' }}</td>
                        <td class="py-2 text-center">@if($model->is_active)<span class="text-emerald-700">فعال</span>@else<span class="text-red-500">غیرفعال</span>@endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const testUrl = @json(route('admin.ai-test.run'));
const csrfToken = @json(csrf_token());

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function renderResult(container, data) {
    const okClass = data.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800';
    const rows = [
        ['وضعیت', data.diagnosis || (data.ok ? 'موفق' : 'ناموفق')],
        ['HTTP', data.http ?? 0],
        ['زمان', `${data.elapsed_ms ?? 0} ms`],
        ['مسیر', data.route_label || '—'],
        ['IP سرور API', data.api_server_ip || '—'],
    ];
    let html = `<div class="rounded-xl border p-3 ${okClass}"><div class="mb-2 font-bold">${data.ok ? '✓ موفق' : '✗ ناموفق'}</div>`;
    html += rows.map(row => `<div class="flex justify-between gap-4 border-t border-current/10 py-1.5 text-xs"><span>${escapeHtml(row[0])}</span><span class="text-left font-mono">${escapeHtml(row[1])}</span></div>`).join('');
    if (data.error) html += `<div class="mt-2 break-all rounded bg-white/60 p-2 text-xs" dir="ltr">${escapeHtml(data.error)}</div>`;
    if (data.answer) html += `<div class="mt-2 rounded bg-white/60 p-2 text-sm">پاسخ: ${escapeHtml(data.answer)}</div>`;
    html += '</div>';
    container.innerHTML = html;
}

async function requestTest(routeType) {
    const payload = {
        model_key: document.getElementById('model_key').value,
        route_type: routeType,
        proxy_server_id: routeType === 'proxy' ? document.getElementById('proxy_server_id').value : null,
    };
    const response = await fetch(testUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
        body: JSON.stringify(payload),
    });
    return response.json();
}

async function runTest(routeType) {
    document.getElementById('compare_box').classList.add('hidden');
    const box = document.getElementById('result_box');
    const content = document.getElementById('result_content');
    box.classList.remove('hidden');
    content.innerHTML = '<div class="py-6 text-center text-sm text-slate-400">در حال تست...</div>';
    try { renderResult(content, await requestTest(routeType)); }
    catch (error) { renderResult(content, {ok:false, diagnosis:'خطای ارتباط با پنل', error:error.message}); }
}

async function runBothTests() {
    document.getElementById('result_box').classList.add('hidden');
    const box = document.getElementById('compare_box');
    const direct = document.getElementById('compare_direct');
    const proxy = document.getElementById('compare_proxy');
    box.classList.remove('hidden');
    direct.textContent = 'در حال تست...'; proxy.textContent = 'در حال تست...';
    const [directResult, proxyResult] = await Promise.all([
        requestTest('direct').catch(error => ({ok:false, diagnosis:'خطا', error:error.message})),
        requestTest('proxy').catch(error => ({ok:false, diagnosis:'خطا', error:error.message})),
    ]);
    renderResult(direct, directResult); renderResult(proxy, proxyResult);
}
</script>
@endsection
