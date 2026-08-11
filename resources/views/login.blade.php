<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود - وکیل هوش مصنوعی</title>

    <!-- Tailwind -->
  
<link href="{{ asset('css/full.min.css') }}" rel="stylesheet" type="text/css">

    <script src="{{ asset('js/tailwind.min.js') }}"></script>
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe); /* پس‌زمینه روشن */
            color: black; /* رنگ متن روشن */
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 1.8rem;
            padding: 2.5rem;
            box-shadow: 0 0 40px rgba(0,0,0,0.35);
        }
        .input-box {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25);
            padding: 0.85rem;
            border-radius: 0.9rem;
            color: #fff;
        }
        .input-box::placeholder {
            color: rgba(255,255,255,0.5);
        }
        .input-box:focus {
            outline: none;
            border-color: #818cf8;
            box-shadow: 0 0 10px rgba(129, 140, 248, 0.5);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #8b5cf6);
            transition: .3s;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #6366f1, #7c3aed);
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4">

<div class="glass-card w-full max-w-md">

    <h2 class="text-center text-3xl font-bold mb-6 bg-gradient-to-r from-purple-300 to-blue-300 bg-clip-text text-transparent">
        ورود به حساب
    </h2>

    <form method="POST" action="/login">
        @csrf

        {{-- ایمیل --}}
        <label class="block mb-2 text-sm">ایمیل</label>
        <input type="email" name="email" required class="input-box w-full" placeholder="example@gmail.com" autocomplete="email">

        {{-- رمز عبور --}}
        <label class="block mt-4 mb-2 text-sm">رمز عبور</label>
        <input type="password" name="password" required class="input-box w-full" placeholder="******" autocomplete="current-password">

        {{-- خطاها --}}
        @if ($errors->any())
            <div class="mt-3 bg-red-500/20 border border-red-400 text-red-200 p-3 rounded-xl text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- دکمه ورود --}}
        <button type="submit"
            class="btn-primary w-full py-3 rounded-xl font-bold text-lg mt-6 shadow-xl">
            ورود
        </button>
    </form>

    {{-- دکمه ثبت‌نام --}}
    <p class="text-center mt-6 text-gray-300">
        حساب ندارید؟
        <a href="{{ route('register') }}" class="text-purple-300 underline">ثبت‌نام کنید</a>
    </p>

</div>

</body>
</html>
