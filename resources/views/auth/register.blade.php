<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام | وکیل هوش مصنوعی</title>
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        * { font-family: Vazirmatn, sans-serif; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef2ff 0%, #fff 45%, #f5f3ff 100%);
            display: flex; align-items: center; justify-content: center; padding: 16px;
        }
        .wrapper { width: 100%; max-width: 420px; }
        .brand { text-align: center; margin-bottom: 28px; }
        .brand-icon {
            width: 64px; height: 64px; margin: 0 auto 14px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 18px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 24px rgba(79,70,229,.28);
        }
        .brand h1 { font-size: 22px; font-weight: 800; color: #1e1b4b; margin-bottom: 4px; }
        .brand p  { font-size: 13px; color: #64748b; }
        .card {
            background: #fff; border-radius: 20px; padding: 36px 32px;
            border: 1px solid #e8eaf6;
            box-shadow: 0 8px 40px rgba(99,102,241,.09);
            animation: cardIn .45s cubic-bezier(.22,.68,0,1.2) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(22px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        @media (max-width: 480px) { .card { padding: 28px 18px; } }
        .alert { padding: 11px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; }
        .alert-error li { list-style: none; padding: 2px 0; }
        .field { margin-bottom: 14px; }
        .field label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
        .opt-badge {
            display: inline-block; font-size: 10px; font-weight: 500;
            color: #64748b; background: #f1f5f9; border-radius: 4px;
            padding: 1px 6px; margin-right: 4px; vertical-align: middle;
        }
        .input-wrap { position: relative; }
        .input-field {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 12px 16px; font-size: 14px; outline: none;
            transition: border-color .2s, box-shadow .2s; background: #fff; color: #1e293b;
        }
        .input-field:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.13); }
        .eye-btn {
            position: absolute; top: 50%; left: 12px; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 4px;
            color: #94a3b8; line-height: 0; transition: color .2s;
        }
        .eye-btn:hover { color: #4f46e5; }
        .with-eye { padding-left: 40px; }
        /* strength bar */
        .strength-wrap { margin-top: 6px; display: none; }
        .strength-bar { height: 4px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; border-radius: 4px; transition: width .3s, background .3s; }
        .strength-label { font-size: 11px; color: #64748b; margin-top: 3px; }
        .btn-primary {
            width:100%; background:#4f46e5; color:#fff; border:none;
            border-radius:12px; padding:13px; font-size:15px; font-weight:700;
            cursor:pointer; transition: background .2s, opacity .2s; margin-top:6px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover:not(:disabled) { background:#4338ca; }
        .btn-primary:disabled { opacity:.7; cursor:not-allowed; }
        .spinner {
            width:18px; height:18px; border:2.5px solid rgba(255,255,255,.35);
            border-top-color:#fff; border-radius:50%;
            animation: spin .7s linear infinite; display:none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .login-link {
            display:block; width:100%; margin-top:12px;
            background:#fff; color:#4f46e5; font-weight:700; font-size:15px;
            border:2px solid #4f46e5; border-radius:12px; padding:12px;
            text-align:center; text-decoration:none; transition: background .2s;
        }
        .login-link:hover { background:#f0f0ff; }
        .footer { text-align:center; font-size:11px; color:#94a3b8; margin-top:20px; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="brand">
        <div class="brand-icon">
            <svg width="32" height="32" fill="none" stroke="white" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <h1>وکیل هوش مصنوعی</h1>
        <p>ساخت حساب کاربری جدید</p>
    </div>

    <div class="card">

        @if($errors->any())
            <div class="alert alert-error">
                <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" id="regForm">
            @csrf

            <div class="field">
                <label>نام کامل</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="input-field" placeholder="نام و نام خانوادگی">
            </div>

            <div class="field">
                <label>شماره موبایل</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required
                    class="input-field" placeholder="09XXXXXXXXX" dir="ltr" style="text-align:left;">
            </div>

            <div class="field">
                <label>ایمیل <span class="opt-badge">اختیاری</span></label>
                <input type="email" name="email" value="{{ old('email') }}"
                    class="input-field" placeholder="email@example.com" dir="ltr" style="text-align:left;">
            </div>

            <div class="field">
                <label>رمز عبور</label>
                <div class="input-wrap">
                    <input type="password" name="password" id="regPass" required
                        class="input-field with-eye" placeholder="حداقل ۶ کاراکتر"
                        dir="ltr" style="text-align:left;" oninput="checkStrength(this.value)">
                    <button type="button" class="eye-btn" onclick="togglePass('regPass')" tabindex="-1">
                        <svg id="regPass-eye" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
                <div class="strength-wrap" id="strengthWrap">
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>
            </div>

            <div class="field">
                <label>تکرار رمز عبور</label>
                <div class="input-wrap">
                    <input type="password" name="password_confirmation" id="regPass2" required
                        class="input-field with-eye" placeholder="رمز عبور را دوباره وارد کنید"
                        dir="ltr" style="text-align:left;">
                    <button type="button" class="eye-btn" onclick="togglePass('regPass2')" tabindex="-1">
                        <svg id="regPass2-eye" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary" id="regBtn">
                <span class="spinner" id="regSpinner"></span>
                <span id="regBtnText">ساخت حساب کاربری</span>
            </button>
        </form>

        <a href="{{ route('login') }}" class="login-link">قبلاً ثبت‌نام کرده‌ام</a>

    </div>

    <p class="footer">© {{ date('Y') }} وکیل هوش مصنوعی — تمامی حقوق محفوظ است</p>
</div>

<script>
var EYE_OPEN  = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
var EYE_CLOSE = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';

function togglePass(id) {
    var inp = document.getElementById(id);
    var eye = document.getElementById(id + '-eye');
    var showing = inp.type === 'password';
    inp.type = showing ? 'text' : 'password';
    eye.innerHTML = showing ? EYE_CLOSE : EYE_OPEN;
}

function checkStrength(val) {
    var wrap  = document.getElementById('strengthWrap');
    var fill  = document.getElementById('strengthFill');
    var label = document.getElementById('strengthLabel');
    if (!val) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    var score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) || /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    var levels = [
        { pct:'20%', color:'#ef4444', text:'خیلی ضعیف' },
        { pct:'40%', color:'#f97316', text:'ضعیف' },
        { pct:'60%', color:'#eab308', text:'متوسط' },
        { pct:'80%', color:'#22c55e', text:'قوی' },
        { pct:'100%',color:'#16a34a', text:'خیلی قوی' },
    ];
    var lv = levels[Math.max(0, score - 1)];
    fill.style.width = lv.pct;
    fill.style.background = lv.color;
    label.textContent = 'قدرت رمز: ' + lv.text;
    label.style.color = lv.color;
}

document.getElementById('regForm').addEventListener('submit', function() {
    var btn  = document.getElementById('regBtn');
    var spin = document.getElementById('regSpinner');
    var txt  = document.getElementById('regBtnText');
    btn.disabled = true;
    spin.style.display = 'block';
    txt.textContent = 'در حال ثبت‌نام...';
});
</script>
</body>
</html>
