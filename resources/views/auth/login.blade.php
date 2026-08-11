<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود | وکیل هوشمند</title>
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
        .alert-error   { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; }
        .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .alert-info    { background:#eff6ff; border:1px solid #bfdbfe; color:#2563eb; }
        .field { margin-bottom: 16px; }
        .field label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
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
        .row-between { display:flex; align-items:center; justify-content:space-between; margin: 10px 0 22px; }
        .remember { display:flex; align-items:center; gap:7px; font-size:13px; color:#64748b; cursor:pointer; }
        .remember input { width:15px; height:15px; accent-color:#4f46e5; }
        .forgot { font-size:13px; color:#4f46e5; text-decoration:none; font-weight:600; }
        .forgot:hover { text-decoration:underline; }
        .btn-primary {
            width:100%; background:#4f46e5; color:#fff; border:none;
            border-radius:12px; padding:13px; font-size:15px; font-weight:700;
            cursor:pointer; transition: background .2s, opacity .2s;
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
        .btn-register {
            display:block; width:100%; margin-top:12px;
            background:#fff; color:#4f46e5; font-weight:700; font-size:15px;
            border:2px solid #4f46e5; border-radius:12px; padding:12px;
            text-align:center; text-decoration:none; transition: background .2s;
        }
        .btn-register:hover { background:#f0f0ff; }
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
        <h1>وکیل هوشمند</h1>
        <p>مشاور حقوقی هوشمند</p>
    </div>

    <div class="card">

        @if($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" id="loginForm">
            @csrf

            <div class="field">
                <label>ایمیل یا شماره موبایل</label>
                <input type="text" name="login" value="{{ old('login') }}" required autofocus
                    class="input-field" placeholder="09XXXXXXXXX یا email@example.com" dir="ltr">
            </div>

            <div class="field" style="margin-bottom:6px;">
                <label>رمز عبور</label>
                <div class="input-wrap">
                    <input type="password" name="password" id="loginPass" required
                        class="input-field with-eye" placeholder="رمز عبور خود را وارد کنید"
                        dir="ltr" style="text-align:left;">
                    <button type="button" class="eye-btn" onclick="togglePass('loginPass')" tabindex="-1">
                        <svg id="loginPass-eye" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="row-between">
                <label class="remember">
                    <input type="checkbox" name="remember">
                    مرا به خاطر بسپار
                </label>
                <a href="{{ route('password.forgot') }}" class="forgot">فراموشی رمز؟</a>
            </div>

            <button type="submit" class="btn-primary" id="loginBtn">
                <span class="spinner" id="loginSpinner"></span>
                <span id="loginBtnText">ورود به سیستم</span>
            </button>
        </form>

        <a href="{{ route('register') }}" class="btn-register">ثبت‌نام حساب جدید</a>

    </div>

    <p class="footer">© {{ date('Y') }} وکیل هوشمند — تمامی حقوق محفوظ است</p>
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

document.getElementById('loginForm').addEventListener('submit', function() {
    var btn  = document.getElementById('loginBtn');
    var spin = document.getElementById('loginSpinner');
    var txt  = document.getElementById('loginBtnText');
    btn.disabled = true;
    spin.style.display = 'block';
    txt.textContent = 'در حال ورود...';
});
</script>
</body>
</html>
