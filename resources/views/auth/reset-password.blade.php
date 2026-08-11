<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغییر رمز عبور | وکیل هوش مصنوعی</title>
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
            border-radius: 12px; padding: 13px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s;
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
        <div style="width:64px;height:64px;background:linear-gradient(135deg,#059669,#10b981);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 20px rgba(16,185,129,.25);">
            <svg width="30" height="30" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <h1 style="font-size:21px;font-weight:800;color:#1e1b4b;margin:0 0 6px;">تغییر رمز عبور</h1>
        <p style="font-size:13px;color:#64748b;margin:0;">کد ارسال شده به موبایل خود را وارد کنید</p>
    </div>

    <div class="auth-card">

        @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;">
            {{ $errors->first() }}
        </div>
        @endif

        @if(session('success'))
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;">
            {{ session('success') }}
        </div>
        @endif

        <form method="POST" action="{{ route('password.reset') }}">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}">

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">کد تأیید ۶ رقمی</label>
                <input type="text" name="otp" required class="input-field" placeholder="کد ارسال شده به موبایل" dir="ltr" maxlength="6" inputmode="numeric">
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">رمز عبور جدید</label>
                <input type="password" name="password" required class="input-field" placeholder="حداقل ۶ کاراکتر">
            </div>

            <div style="margin-bottom:22px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">تکرار رمز عبور جدید</label>
                <input type="password" name="password_confirmation" required class="input-field" placeholder="رمز را دوباره وارد کنید">
            </div>

            <button type="submit" class="btn-primary">ثبت رمز عبور جدید</button>
        </form>

        <div style="margin-top:20px;text-align:center;font-size:13px;color:#64748b;">
            <a href="{{ route('password.forgot') }}" style="color:#4f46e5;font-weight:600;text-decoration:none;">ارسال مجدد کد</a>
            <span style="margin:0 8px;color:#cbd5e1;">|</span>
            <a href="{{ route('login') }}" style="color:#64748b;text-decoration:none;">بازگشت به ورود</a>
        </div>
    </div>
</div>

</body>
</html>
