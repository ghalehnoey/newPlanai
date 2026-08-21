@extends('layouts.admin')
@section('title', 'مدیریت پراکسی‌های هوش مصنوعی')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">مدیریت و راه‌اندازی پراکسی</h1>
        <p class="mt-1 text-sm text-slate-500">ثبت سرور، نصب امن Dante، تست IP خروجی و اتصال مدل‌ها از یک صفحه</p>
    </div>
    <a href="{{ route('admin.ai-test') }}" class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">تست مدل‌ها</a>
</div>

@if($errors->any())
<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
    <ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm leading-7 text-blue-900">
    <strong>روش کار:</strong> اطلاعات سرور مقصد را ذخیره کنید، سپس «نصب/راه‌اندازی روی سرور» را بزنید. پنل با SSH، بستهٔ Dante را نصب می‌کند، یک کاربر SOCKS با رمز تصادفی می‌سازد و فقط IP
    <code dir="ltr" class="rounded bg-white px-1.5 py-0.5">77.237.82.11</code>
    را مجاز می‌کند. رمزهای SSH و SOCKS رمزگذاری می‌شوند و در صفحه یا لاگ نمایش داده نمی‌شوند.
</div>

<div class="mb-8 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
    <h2 class="mb-4 font-bold text-slate-800">افزودن سرور پراکسی</h2>
    <form method="POST" action="{{ route('admin.ai-proxies.store') }}" class="space-y-5">
        @csrf
        <div>
            <h3 class="mb-3 text-sm font-bold text-slate-700">اتصال پراکسی</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">نام *</label><input name="name" value="{{ old('name', 'سرور آلمان') }}" required class="input-field"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">IP یا دامنه *</label><input name="host" value="{{ old('host', '185.110.189.3') }}" required class="input-field font-mono" dir="ltr"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">پورت SOCKS *</label><input type="number" name="port" value="{{ old('port', 1080) }}" min="1" max="65535" required class="input-field" dir="ltr"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">نوع</label><select name="scheme" class="input-field" dir="ltr"><option value="socks5h">SOCKS5H</option><option value="socks5">SOCKS5</option><option value="http">HTTP</option><option value="https">HTTPS</option></select></div>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">کاربر SOCKS</label><input name="username" value="{{ old('username') }}" class="input-field font-mono" dir="ltr" placeholder="خالی = ai_proxy"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">رمز SOCKS</label><input type="password" name="password" class="input-field" dir="ltr" autocomplete="new-password" placeholder="خالی = تولید خودکار"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">اولویت</label><input type="number" name="priority" value="{{ old('priority', 10) }}" min="1" max="10000" required class="input-field"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">مهلت اتصال</label><input type="number" name="connect_timeout" value="{{ old('connect_timeout', 10) }}" min="3" max="60" required class="input-field"></div>
            </div>
        </div>

        <div class="rounded-xl border border-violet-100 bg-violet-50/50 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-700">دسترسی SSH برای نصب خودکار</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">نام کاربری SSH</label><input name="ssh_username" value="{{ old('ssh_username', 'root') }}" class="input-field font-mono" dir="ltr"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">رمز SSH</label><input type="password" name="ssh_password" class="input-field" dir="ltr" autocomplete="new-password"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">پورت SSH</label><input type="number" name="ssh_port" value="{{ old('ssh_port', 22) }}" min="1" max="65535" required class="input-field"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">IP مجاز سایت</label><input name="allowed_source_ip" value="{{ old('allowed_source_ip', '77.237.82.11') }}" required class="input-field font-mono" dir="ltr"></div>
            </div>
            <p class="mt-2 text-xs text-slate-500">کاربر SSH باید root یا دارای sudo بدون درخواست رمز باشد. رمز فقط برای ورود SSH استفاده می‌شود.</p>
        </div>

        <div><label class="mb-1 block text-xs font-semibold text-slate-600">یادداشت</label><input name="notes" value="{{ old('notes') }}" class="input-field"></div>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }} class="accent-indigo-600">پس از تست در مسیر مدل‌ها فعال باشد</label>
            <button class="btn-primary">ذخیره سرور</button>
        </div>
    </form>
</div>

<div class="space-y-5">
    @forelse($proxyServers as $proxy)
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm" id="proxy-card-{{ $proxy->id }}">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-bold text-slate-800">{{ $proxy->name }}</h2>
                    @if($proxy->last_status === 'up')<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">پراکسی سالم</span>
                    @elseif($proxy->last_status === 'down')<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">پراکسی قطع</span>
                    @else<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">تست نشده</span>@endif
                    @if($proxy->provision_status === 'ready')<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">نصب کامل</span>
                    @elseif($proxy->provision_status === 'failed')<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">نصب ناموفق</span>
                    @elseif($proxy->provision_status === 'installed_unreachable')<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">نصب شد؛ دسترسی قطع</span>@endif
                    @unless($proxy->is_active)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">غیرفعال برای مدل‌ها</span>@endunless
                </div>
                <div class="mt-1 font-mono text-xs text-slate-500" dir="ltr">{{ $proxy->displayAddress() }}</div>
            </div>
            <div class="text-left text-xs text-slate-500">
                <div>{{ $proxy->ai_models_count }} مدل متصل</div>
                @if($proxy->last_checked_at)<div>{{ $proxy->last_checked_at->diffForHumans() }} · {{ $proxy->last_latency_ms }} ms</div>@endif
                @if($proxy->last_outbound_ip)<div dir="ltr">Outbound IP: {{ $proxy->last_outbound_ip }}</div>@endif
            </div>
        </div>

        <form method="POST" action="{{ route('admin.ai-proxies.update', $proxy) }}" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="name" value="{{ $proxy->name }}" required class="input-field" placeholder="نام">
                <input name="host" value="{{ $proxy->host }}" required class="input-field font-mono" dir="ltr" placeholder="IP یا دامنه">
                <input type="number" name="port" value="{{ $proxy->port }}" min="1" max="65535" required class="input-field" title="پورت SOCKS">
                <select name="scheme" class="input-field" dir="ltr">@foreach(['socks5h','socks5','http','https'] as $scheme)<option value="{{ $scheme }}" {{ $proxy->scheme === $scheme ? 'selected' : '' }}>{{ $scheme }}</option>@endforeach</select>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="username" value="{{ $proxy->username }}" class="input-field font-mono" dir="ltr" placeholder="کاربر SOCKS">
                <input type="password" name="password" class="input-field" dir="ltr" autocomplete="new-password" placeholder="رمز جدید SOCKS (اختیاری)">
                <input type="number" name="priority" value="{{ $proxy->priority }}" min="1" max="10000" required class="input-field" title="اولویت">
                <input type="number" name="connect_timeout" value="{{ $proxy->connect_timeout }}" min="3" max="60" required class="input-field" title="مهلت اتصال">
            </div>

            <div class="rounded-xl border border-violet-100 bg-violet-50/50 p-4">
                <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-bold text-slate-700">SSH سرور مقصد</h3>@if($proxy->ssh_host_fingerprint)<span class="font-mono text-[10px] text-slate-500" dir="ltr" title="اثر انگشت ثبت‌شده">{{ $proxy->ssh_host_fingerprint }}</span>@endif</div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <input name="ssh_username" value="{{ $proxy->ssh_username }}" class="input-field font-mono" dir="ltr" placeholder="کاربر SSH">
                    <input type="password" name="ssh_password" class="input-field" dir="ltr" autocomplete="new-password" placeholder="{{ $proxy->ssh_password ? 'رمز SSH ذخیره شده؛ برای حفظ خالی بگذارید' : 'رمز SSH' }}">
                    <input type="number" name="ssh_port" value="{{ $proxy->ssh_port ?? 22 }}" min="1" max="65535" required class="input-field" title="پورت SSH">
                    <input name="allowed_source_ip" value="{{ $proxy->allowed_source_ip ?? '77.237.82.11' }}" required class="input-field font-mono" dir="ltr" title="IP مجاز سایت">
                </div>
                <div class="mt-3 flex flex-wrap gap-5 text-xs text-slate-500">
                    <label class="flex items-center gap-2"><input type="checkbox" name="clear_ssh_password" value="1">حذف رمز SSH ذخیره‌شده</label>
                    <span>رمز SSH: {{ $proxy->ssh_password ? 'ذخیره و رمزگذاری شده' : 'ثبت نشده' }}</span>
                    <span>رمز SOCKS: {{ $proxy->password ? 'ذخیره و رمزگذاری شده' : 'هنوز ساخته نشده' }}</span>
                </div>
            </div>

            <input name="notes" value="{{ $proxy->notes }}" class="input-field" placeholder="یادداشت">
            <div class="flex flex-wrap items-center gap-5 border-t border-slate-100 pt-4">
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" {{ $proxy->is_active ? 'checked' : '' }} class="accent-indigo-600">فعال برای مدل‌ها</label>
                <label class="flex items-center gap-2 text-sm text-slate-500"><input type="checkbox" name="clear_password" value="1">حذف رمز SOCKS</label>
                <button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">ذخیره تغییرات</button>
            </div>
        </form>

        @if($proxy->provision_message)
        <div class="mt-3 rounded-lg {{ $proxy->provision_status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700' }} p-3 text-xs">{{ $proxy->provision_message }}</div>
        @endif
        @if($proxy->last_error)<div class="mt-3 rounded-lg bg-red-50 p-2 text-xs text-red-700" dir="ltr">{{ $proxy->last_error }}</div>@endif

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button" onclick="provisionProxy({{ $proxy->id }}, this)" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700">نصب/راه‌اندازی روی سرور</button>
            <button type="button" onclick="testProxy({{ $proxy->id }}, this)" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">تست IP خروجی</button>
            <span id="proxy-result-{{ $proxy->id }}" class="text-xs text-slate-500"></span>
            <form method="POST" action="{{ route('admin.ai-proxies.destroy', $proxy) }}" class="mr-auto" onsubmit="return confirm('این پراکسی حذف شود؟')">@csrf @method('DELETE')<button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">حذف</button></form>
        </div>
    </div>
    @empty
    <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">هنوز سرور پراکسی ثبت نشده است.</div>
    @endforelse
</div>

<script>
const proxyBaseUrl = @json(url('/admin/ai-proxies'));
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function testProxy(id, button) {
    const result = document.getElementById(`proxy-result-${id}`);
    button.disabled = true;
    result.className = 'text-xs text-slate-500';
    result.textContent = 'در حال تست...';
    try {
        const response = await fetch(`${proxyBaseUrl}/${id}/test`, {method:'POST', headers:{'X-CSRF-TOKEN':csrfToken,'Accept':'application/json'}});
        const data = await response.json();
        result.className = `text-xs ${data.ok ? 'text-emerald-700' : 'text-red-700'}`;
        result.textContent = data.ok ? `سالم — IP خروجی: ${data.outbound_ip} — ${data.elapsed_ms}ms` : (data.diagnosis || 'تست ناموفق بود.');
    } catch (error) {
        result.className = 'text-xs text-red-700'; result.textContent = 'خطای ارتباط با پنل.';
    } finally { button.disabled = false; }
}

async function provisionProxy(id, button) {
    if (!confirm('اطلاعات ذخیره‌شده برای نصب یا به‌روزرسانی Dante روی سرور مقصد استفاده شود؟')) return;
    const result = document.getElementById(`proxy-result-${id}`);
    button.disabled = true;
    result.className = 'text-xs text-violet-700';
    result.textContent = 'در حال اتصال SSH و نصب؛ این مرحله ممکن است چند دقیقه طول بکشد...';
    try {
        const response = await fetch(`${proxyBaseUrl}/${id}/provision`, {method:'POST', headers:{'X-CSRF-TOKEN':csrfToken,'Accept':'application/json'}});
        const data = await response.json();
        result.className = `text-xs ${data.ok ? 'text-emerald-700' : 'text-red-700'}`;
        result.textContent = data.message || (data.ok ? 'راه‌اندازی موفق بود.' : 'راه‌اندازی ناموفق بود.');
        if (data.ok) setTimeout(() => window.location.reload(), 1800);
    } catch (error) {
        result.className = 'text-xs text-red-700'; result.textContent = 'درخواست نصب کامل نشد؛ وضعیت سرور را بررسی کنید.';
    } finally { button.disabled = false; }
}
</script>
@endsection
