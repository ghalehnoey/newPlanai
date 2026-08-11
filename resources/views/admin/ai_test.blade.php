@extends('layouts.admin')
@section('title', 'تست اتصال هوش مصنوعی')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-800">تست اتصال هوش مصنوعی</h1>
    <span class="text-sm text-slate-500">بررسی وضعیت API و اتصال مدل‌ها</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- فرم تست --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h3 class="font-semibold text-slate-800 mb-4">تنظیمات تست</h3>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">انتخاب مدل</label>
                    <select id="model_key" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        @foreach($models as $m)
                        <option value="{{ $m->key }}" data-provider="{{ $m->provider }}" data-endpoint="{{ $m->endpoint }}" {{ $m->is_active ? '' : 'class=text-slate-400' }}>
                            {{ $m->name }} — {{ $m->title }} {{ $m->is_active ? '✓' : '(غیرفعال)' }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">سوال تست</label>
                    <textarea id="question" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" placeholder="سوال...">سلام، یک جمله کوتاه به فارسی بگو.</textarea>
                </div>

                <div class="border border-slate-200 rounded-lg p-3 space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="use_proxy" class="rounded">
                        <span class="text-sm font-medium text-slate-700">استفاده از Proxy</span>
                    </label>
                    <input id="proxy_url" type="text" value="socks5h://127.0.0.1:1080"
                        class="w-full border border-slate-200 rounded px-2 py-1 text-xs font-mono disabled:bg-slate-50 disabled:text-slate-400 focus:outline-none"
                        disabled placeholder="socks5h://127.0.0.1:1080">
                </div>

                <button onclick="runTest()" id="btn_run"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    اجرای تست
                </button>

                <button onclick="runBothTests()" id="btn_both"
                    class="w-full bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                    تست با Proxy و بدون Proxy (مقایسه)
                </button>
            </div>
        </div>

        {{-- راهنما --}}
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800 space-y-1.5">
            <p class="font-semibold">تفسیر نتایج:</p>
            <p>• <span class="font-mono">curl errno 7</span> = اتصال رد شد (proxy نیست)</p>
            <p>• <span class="font-mono">HTTP 401</span> = کلید API اشتباه</p>
            <p>• <span class="font-mono">HTTP 429</span> = اعتبار تمام / rate limit</p>
            <p>• <span class="font-mono">HTTP 200</span> + جواب = همه چیز سالم ✓</p>
        </div>
    </div>

    {{-- نتیجه --}}
    <div class="lg:col-span-2 space-y-4">
        <div id="result_box" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hidden">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-800">نتیجه تست</h3>
                <span id="result_badge" class="text-xs font-bold px-3 py-1 rounded-full"></span>
            </div>
            <div id="result_content" class="space-y-3 text-sm"></div>
        </div>

        <div id="compare_box" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hidden">
            <h3 class="font-semibold text-slate-800 mb-4">مقایسه: با Proxy vs بدون Proxy</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-slate-500 mb-2">بدون Proxy (مستقیم)</p>
                    <div id="compare_direct" class="space-y-2 text-sm"></div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 mb-2">با Proxy</p>
                    <div id="compare_proxy" class="space-y-2 text-sm"></div>
                </div>
            </div>
        </div>

        {{-- لیست مدل‌ها --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h3 class="font-semibold text-slate-800 mb-4">وضعیت مدل‌ها در دیتابیس</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-100">
                            <th class="text-right pb-2 font-medium">نام</th>
                            <th class="text-right pb-2 font-medium">مدل</th>
                            <th class="text-right pb-2 font-medium">Provider</th>
                            <th class="text-center pb-2 font-medium">Proxy</th>
                            <th class="text-center pb-2 font-medium">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($models as $m)
                        <tr class="hover:bg-slate-50 cursor-pointer" onclick="selectModel('{{ $m->key }}')">
                            <td class="py-2 font-medium text-slate-800">{{ $m->name }}</td>
                            <td class="py-2 font-mono text-slate-600">{{ $m->model_mapping }}</td>
                            <td class="py-2 text-slate-500">{{ $m->provider }}</td>
                            <td class="py-2 text-center">
                                @if($m->use_proxy)
                                    <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs">فعال</span>
                                @else
                                    <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-xs">ندارد</span>
                                @endif
                            </td>
                            <td class="py-2 text-center">
                                @if($m->is_active)
                                    <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs">فعال</span>
                                @else
                                    <span class="bg-red-50 text-red-500 px-2 py-0.5 rounded text-xs">غیرفعال</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('use_proxy').addEventListener('change', function() {
    document.getElementById('proxy_url').disabled = !this.checked;
});

function selectModel(key) {
    document.getElementById('model_key').value = key;
}

function renderResult(container, data, withProxy) {
    const ok = data.ok;
    const color = ok ? 'emerald' : 'red';
    const icon  = ok ? '✓' : '✗';

    let html = '';
    html += `<div class="flex justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">وضعیت</span>
        <span class="font-bold ${ok ? 'text-emerald-600' : 'text-red-600'}">${icon} ${data.diagnosis}</span>
    </div>`;
    html += `<div class="flex justify-between py-1"><span class="text-slate-500">HTTP Code</span><span class="font-mono font-bold ${data.http===200?'text-emerald-600':'text-red-500'}">${data.http}</span></div>`;
    html += `<div class="flex justify-between py-1"><span class="text-slate-500">زمان پاسخ</span><span class="font-mono">${data.elapsed_ms} ms</span></div>`;

    if (data.server_ip) {
        html += `<div class="flex justify-between py-1"><span class="text-slate-500">IP سرور API</span><span class="font-mono text-slate-600">${data.server_ip}</span></div>`;
    }
    if (data.credit) {
        html += `<div class="flex justify-between py-1"><span class="text-slate-500">اعتبار API</span><span class="font-semibold ${data.credit.startsWith('$') ? 'text-emerald-600' : 'text-amber-600'}">${data.credit}</span></div>`;
    }
    if (data.usage) {
        html += `<div class="flex justify-between py-1"><span class="text-slate-500">Tokens مصرفی</span><span class="font-mono text-xs">${data.usage.prompt_tokens} prompt + ${data.usage.completion_tokens} completion</span></div>`;
    }
    if (data.error) {
        html += `<div class="mt-2 bg-red-50 border border-red-100 rounded p-2 text-red-700 text-xs font-mono break-all">${data.error}</div>`;
    }
    if (data.answer) {
        html += `<div class="mt-2 bg-emerald-50 border border-emerald-100 rounded p-3 text-slate-700"><p class="text-xs text-slate-400 mb-1">جواب AI:</p><p class="leading-relaxed">${data.answer}</p></div>`;
    }
    container.innerHTML = html;
}

async function runTest() {
    const btn = document.getElementById('btn_run');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> در حال تست...';

    const box = document.getElementById('result_box');
    document.getElementById('compare_box').classList.add('hidden');
    box.classList.remove('hidden');
    document.getElementById('result_content').innerHTML = '<p class="text-slate-400 text-center py-4">در حال اتصال...</p>';

    const data = {
        _token: '{{ csrf_token() }}',
        model_key: document.getElementById('model_key').value,
        question:  document.getElementById('question').value,
        use_proxy: document.getElementById('use_proxy').checked ? 1 : 0,
        proxy_url: document.getElementById('proxy_url').value,
    };

    try {
        const res = await fetch('{{ route("admin.ai-test.run") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': data._token},
            body: JSON.stringify(data)
        });
        const json = await res.json();

        const badge = document.getElementById('result_badge');
        badge.textContent = json.ok ? 'موفق' : 'ناموفق';
        badge.className = `text-xs font-bold px-3 py-1 rounded-full ${json.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`;

        renderResult(document.getElementById('result_content'), json, data.use_proxy);
    } catch(e) {
        document.getElementById('result_content').innerHTML = `<p class="text-red-600">خطا: ${e.message}</p>`;
    }

    btn.disabled = false;
    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> اجرای تست';
}

async function runBothTests() {
    const btn = document.getElementById('btn_both');
    btn.disabled = true;
    btn.textContent = 'در حال تست...';
    document.getElementById('result_box').classList.add('hidden');
    const cBox = document.getElementById('compare_box');
    cBox.classList.remove('hidden');
    document.getElementById('compare_direct').innerHTML = '<p class="text-slate-400">در حال تست...</p>';
    document.getElementById('compare_proxy').innerHTML = '<p class="text-slate-400">در حال تست...</p>';

    const base = {
        model_key: document.getElementById('model_key').value,
        question:  document.getElementById('question').value,
        proxy_url: document.getElementById('proxy_url').value,
    };
    const token = '{{ csrf_token() }}';

    const [resDirect, resProxy] = await Promise.all([
        fetch('{{ route("admin.ai-test.run") }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token},
            body: JSON.stringify({...base, use_proxy: 0})
        }).then(r=>r.json()).catch(e=>({ok:false,diagnosis:'خطای شبکه',error:e.message,http:0,elapsed_ms:0})),
        fetch('{{ route("admin.ai-test.run") }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token},
            body: JSON.stringify({...base, use_proxy: 1})
        }).then(r=>r.json()).catch(e=>({ok:false,diagnosis:'خطای شبکه',error:e.message,http:0,elapsed_ms:0})),
    ]);

    renderResult(document.getElementById('compare_direct'), resDirect, false);
    renderResult(document.getElementById('compare_proxy'),  resProxy,  true);
    btn.disabled = false;
    btn.textContent = 'تست با Proxy و بدون Proxy (مقایسه)';
}
</script>
@endsection
