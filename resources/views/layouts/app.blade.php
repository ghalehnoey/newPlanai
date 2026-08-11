<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پلان‌ها</title>
    <!-- اضافه کردن فایل‌های CSS و JS مورد نیاز -->
    <!--<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.0.2/dist/tailwind.min.css" rel="stylesheet">-->
     <script src="{{ asset('js/tailwind.min.js') }}"></script>
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet" type="text/css">
</head>

<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- هدر یا ناوبری -->
        <nav class="bg-blue-600 p-4 text-white">
            <div class="container mx-auto flex justify-between items-center">
                <a href="/" class="text-2xl font-semibold">وبسایت من</a>
                <div>
                    @auth
                        <a href="{{ route('logout') }}" class="px-4 py-2">خروج</a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2">ورود</a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- محتوای صفحه اصلی -->
        <div class="container mx-auto p-6">
            @yield('content') <!-- محتوای صفحه مورد نظر در اینجا وارد می‌شود -->
        </div>
    </div>
</body>

</html>
