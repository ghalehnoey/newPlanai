<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انتخاب پلن - وکیل هوش مصنوعی</title>

    <!-- فراخوانی فایل فونت استاندارد -->
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet" type="text/css">
    
    <script src="/assets/js/tailwind.min.js"></script>
    <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
    <style>
        /* اعمال فونت روی تمام اجزای صفحه */
        * {
            font-family: 'Vazirmatn', sans-serif !important;
        }
        
        body {
            background: linear-gradient(135deg, #151826, #231f45, #1e1b4b);
        }
        .glass {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .plan-card {
            transition: .25s;
        }
        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 32px rgba(120, 90, 255, 0.25);
        }
        .btn-gradient {
            background: linear-gradient(45deg, #8b5cf6, #ec4899);
        }
        .neon-border {
            box-shadow: 0 0 12px rgba(168, 85, 247, 0.6);
        }
    </style>
</head>
<body class="text-white">
<div class="container mx-auto px-4 py-10 max-w-5xl">
    <!-- هدر -->
    <div class="glass rounded-2xl p-6 mb-10 text-center shadow-lg border border-purple-500/20">
        <h1 class="text-3xl md:text-4xl font-extrabold mb-3 bg-gradient-to-r from-purple-300 to-pink-300 bg-clip-text text-transparent">
            انتخاب پلن اشتراک ماهانه
        </h1>
        <p class="text-gray-300 text-sm md:text-base">
            یکی از پلن‌های <span class="text-yellow-300 font-bold">یک ماهه</span> را انتخاب کنید تا از وکیل هوش مصنوعی بدون محدودیت استفاده کنید
        </p>
        <!-- نمایش نام کاربر -->
        <div class="mt-4 text-lg font-bold text-purple-200">
            کاربر گرامی:
            <span class="text-pink-300">
                {{ auth()->user()->name ?? auth()->user()->email }}
            </span>
        </div>
        <!-- دکمه خروج -->
        <form action="/logout" method="POST" class="mt-5">
            @csrf
            <button class="px-5 py-2 rounded-xl bg-red-500/80 hover:bg-red-600 transition text-sm font-bold">
                خروج از حساب
            </button>
        </form>
    </div>

    <!-- پلن فعال (فقط اگر سؤال باقی مانده داشته باشد) -->
    @if($subscription && $subscription->remainingQuestions() > 0)
        <div class="glass rounded-2xl p-6 mb-10 text-center neon-border shadow-lg">
            <h2 class="text-2xl font-bold mb-3">پلن ماهانه فعال شما</h2>
            <div class="text-3xl font-extrabold text-yellow-400 mb-3">
                {{ $subscription->plan->name }}
            </div>
            <p class="text-md">
                سوالات باقی‌مانده:
                <span class="text-green-400 font-bold">
                    {{ number_format($subscription->remainingQuestions()) }}
                </span>
            </p>
            <p class="text-gray-300 mt-2">
                تاریخ انقضای اشتراک: {{ \Morilog\Jalali\Jalalian::fromCarbon($subscription->expires_at)->format('Y/m/d H:i') }}
            </p>
        </div>
    @elseif($subscription && $subscription->remainingQuestions() <= 0)
        <!-- وقتی سؤالات تمام شده ولی هنوز اشتراک از نظر تاریخ فعال است -->
        <div class="glass rounded-2xl p-6 mb-10 text-center bg-red-900/30 border border-red-500/50 shadow-lg">
            <h2 class="text-2xl font-bold mb-3 text-red-300">⚠️ سؤالات پلن شما تمام شده</h2>
            <p class="text-lg text-gray-200 mb-4">
                پلن فعلی: <span class="font-bold text-yellow-300">{{ $subscription->plan->name }}</span>
            </p>
            <p class="text-gray-300 mb-4">
                تاریخ انقضای اشتراک: {{ \Morilog\Jalali\Jalalian::fromCarbon($subscription->expires_at)->format('Y/m/d H:i') }}
            </p>
            <p class="text-lg text-gray-200">
                برای ادامه استفاده از وکیل هوش مصنوعی، یکی از پلن‌های ماهانه زیر را مجدداً خریداری کنید.
            </p>
        </div>
    @endif

    <!-- پلن‌ها -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($plans as $plan)
            <div class="plan-card glass rounded-2xl p-6 shadow-lg {{ $plan->questions_limit >= 3000 ? 'neon-border' : '' }}">
                <h3 class="text-2xl font-bold mb-4 bg-gradient-to-r from-purple-300 to-pink-300 bg-clip-text text-transparent">
                    {{ $plan->name }}
                </h3>
                <div class="text-center mb-6">
                    <span class="text-4xl font-extrabold bg-gradient-to-r from-yellow-300 to-pink-400 bg-clip-text text-transparent">
                        {{ number_format($plan->price) }}
                    </span>
                    <span class="text-gray-300 text-sm">تومان <span class="text-yellow-300 font-bold">/ ماهانه</span></span>
                </div>
                <div class="text-xl font-bold mb-6">
                    {{ number_format($plan->questions_limit) }} سؤال در ماه
                </div>

                @if(optional($subscription)->plan_id == $plan->id && $subscription->remainingQuestions() > 0)
                    <button class="w-full py-3 rounded-xl bg-green-600/90 text-md font-bold cursor-not-allowed">
                        <i class="fas fa-check ml-2"></i> پلن فعال
                    </button>
                @else
                    <form action="{{ route('payment.start', $plan) }}" method="POST">
                        @csrf
                        <button class="btn-gradient w-full py-3 rounded-xl text-lg font-bold">
                            <i class="fas fa-credit-card ml-2"></i> خرید اشتراک   
                        </button>
                    </form>
                @endif

                <div class="text-gray-300 text-sm flex flex-col gap-3 mt-6">
                    <div><i class="fas fa-calendar-alt text-purple-400 ml-1"></i> اعتبار: <span class="text-white font-bold">۳۰ روز (یک ماه)</span></div>
                    <div><i class="fas fa-gift text-pink-400 ml-1"></i> فعال‌سازی فوری پس از پرداخت</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- بازگشت -->
    <div class="text-center mt-10">
        <a href="/" class="text-sm text-purple-300 hover:text-purple-200 transition">
            <i class="fas fa-arrow-left ml-1"></i> بازگشت به صفحه چت
        </a>
    </div>
</div>
</body>
</html>
