<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فراموشی رمز عبور | وکیل هوش مصنوعی</title>
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
    <script src="{{ asset('js/tailwind.min.js') }}"></script>
    <style>
        * { font-family: 'Vazirmatn', sans-serif; }
        .input-field {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 12px 16px; font-size: 14px; outline: none; transition: all .2s; background: #fff;
        }
        .input-field:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
        .btn-primary {
            width: 100%; background: #4f46e5; color: #fff; border: none;
            border-radius: 12px; padding: 13px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-primary:hover { background: #4338ca; }
        .auth-card {
            background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(99,102,241,.08);
            border: 1px solid #e8eaf6; padding: 36px 32px;
        }
        @media (max-width: 480px) { .auth-card { padding: 28px 18px; } }
    </style>
</head>
<body style="min-height:100vh;background:linear-gradient(135deg,#f0f4ff 0%,#fff 50%,#f3f0ff 100%);display:flex;align-items:center;justify-content:center;padding:16px;">

<div style="width:100%;max-width:420px;">

    <div style="text-align:center;margin-bottom:28px;">
        <div style="width:64px;height:64px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 20px rgba(79,70,229,.25);">
            <svg width="30" height="30" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h1 style="font-size:21px;font-weight:800;color:#1e1b4b;margin:0 0 6px;">بازیابی رمز عبور</h1>
        <p style="font-size:13px;color:#64748b;margin:0;">شماره موبایل خود را وارد کنید تا کد ارسال شود</p>
    </div>

    <div class="auth-card">

        @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('password.sendOtp') }}">
            @csrf
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">شماره موبایل ثبت‌شده</label>
                <input type="text" name="phone" required class="input-field" placeholder="09XXXXXXXXX" dir="ltr" value="{{ old('phone') }}">
                <p style="font-size:12px;color:#94a3b8;margin-top:6px;margin-bottom:0;">کد ۶ رقمی به این شماره ارسال می‌شود</p>
            </div>
            <button type="submit" class="btn-primary">ارسال کد بازیابی</button>
        </form>

        <div style="margin-top:20px;text-align:center;font-size:13px;color:#64748b;">
            <a href="{{ route('login') }}" style="color:#4f46e5;font-weight:600;text-decoration:none;">بازگشت به ورود</a>
        </div>
    </div>
</div>

</body>
</html>
