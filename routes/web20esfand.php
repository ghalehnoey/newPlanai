<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PaymentController;


/*
|--------------------------------------------------------------------------
| 🟦 احراز هویت (ورود، ثبت‌نام، خروج)
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLoginForm'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.post');

Route::get('/register', [AuthController::class, 'showRegisterForm'])
    ->name('register');

Route::post('/register', [AuthController::class, 'register'])
    ->name('register.post');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');



/*
|--------------------------------------------------------------------------
| 🟩 بازیابی رمز عبور با پیامک (OTP System)
|--------------------------------------------------------------------------
*/

Route::get('/forgot-password', [PasswordResetController::class, 'showForgetForm'])
    ->name('password.forgot');

Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])
    ->name('password.sendOtp');

Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])
    ->name('password.resetForm');

Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->name('password.reset');

Route::post('/admin/users/{user}/update-phone', [AdminController::class, 'updatePhone'])
    ->name('admin.updatePhone')
    ->middleware(['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| 🟥 جلوگیری از GET روی chat-stream
|--------------------------------------------------------------------------
*/

Route::get('/chat-stream', function () {
    return response()->json([
        'error' => 'Method Not Allowed. Use POST.'
    ], 405);
});



/*
|--------------------------------------------------------------------------
| 🟦 صفحه اصلی چت — فقط برای کاربران با اشتراک فعال
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('/', [ChatController::class, 'index'])
        ->name('home');
});



/*
|--------------------------------------------------------------------------
| 🟨 API چت (پیام‌ها، مکالمات، فایل‌ها)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::post('/chat/send', [ChatController::class, 'sendMessage'])
        ->name('chat.send');

    Route::get('/chat/{id}', [ChatController::class, 'getConversation'])
        ->name('chat.show');

    Route::delete('/chat/{id}', [ChatController::class, 'deleteConversation'])
        ->name('chat.delete');

    Route::patch('/chat/{id}/rename', [ChatController::class, 'renameConversation'])
        ->name('chat.rename');

    Route::get('/chat/file/{file}', [ChatController::class, 'viewFile'])
        ->name('chat.file');

    Route::post('/chat-stream', [ChatController::class, 'chatStream'])
        ->name('chat.stream');
});



/*
|--------------------------------------------------------------------------
| 🟧 مسیرهای خرید پلن، پرداخت و Callback — فقط کاربر لاگین‌شده
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/plans', [PlanController::class, 'index'])
        ->name('plans');

    Route::post('/plan/purchase/{plan}', [PlanController::class, 'purchase'])
        ->name('plan.purchase');

    Route::post('/payment/start/{plan}', [PaymentController::class, 'start'])
        ->name('payment.start');

    Route::get('/payment/callback', [PaymentController::class, 'callback'])
        ->name('payment.callback');
});



/*
|--------------------------------------------------------------------------
| 🟥 پنل مدیریت — فقط مدیر مجاز است
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->group(function () {

    Route::get('/admin', [AdminController::class, 'dashboard'])
        ->name('admin.dashboard');

    // 🚀 اضافه شده: تغییر رمز کاربران
    Route::post('/admin/users/{user}/change-password', [AdminController::class, 'changePassword'])
        ->name('admin.changePassword');
});

Route::get('/proxy-test', function () {
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(10)->get('https://httpbin.org/ip');
        return response()->json(['success' => true, 'data' => $response->json()]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
});
