<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\BazaarReceiptController;
use Illuminate\Support\Facades\Route;

/* ─────────────────── AUTH ─────────────────── */
Route::get('/login',    [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.post');
Route::post('/logout',  [AuthController::class, 'logout'])->name('logout');

/* ─────────────────── فراموشی رمز عبور (OTP پیامک) ─────────────────── */
Route::get('/forgot-password',  [PasswordResetController::class, 'showForgetForm'])->name('password.forgot');
Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])->middleware('throttle:3,10')->name('password.sendOtp');
Route::get('/reset-password',   [PasswordResetController::class, 'showResetForm'])->name('password.resetForm');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,10')->name('password.reset');

/* ─────────────────── جلوگیری از GET روی chat-stream ─────────────────── */
Route::get('/chat-stream', function () {
    return response()->json(['error' => 'Method Not Allowed. Use POST.'], 405);
});

/* ─────────────────── CHAT ─────────────────── */
Route::middleware('auth')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('home');
    Route::post('/chat/send',          [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat-stream',        [ChatController::class, 'sendMessage'])->name('chat.stream');
    Route::get('/chat/{id}',           [ChatController::class, 'getConversation'])->name('chat.get');
    Route::delete('/chat/{id}',        [ChatController::class, 'deleteConversation'])->name('chat.delete');
    Route::patch('/chat/{id}/rename',  [ChatController::class, 'renameConversation'])->name('chat.rename');
    Route::get('/chat/file/{file}',    [ChatController::class, 'viewFile'])->name('chat.file');
});

/* ─────────────────── PLANS & PAYMENT ─────────────────── */
Route::middleware('auth')->group(function () {
    Route::get('/plans',                  [PlanController::class, 'index'])->name('plans');
    Route::post('/plan/purchase/{plan}',  [PlanController::class, 'purchase'])->name('plan.purchase');
    Route::post('/payment/start/{plan}',  [PaymentController::class, 'start'])->name('payment.start');

    Route::post('/api/mobile/bazaar/verify', [BazaarReceiptController::class, 'verify'])
        ->middleware('throttle:30,1')
        ->name('mobile.bazaar.verify');
});

// Callback must remain reachable even if the browser session is lost at the bank.
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

/* ─────────────────── ADMIN PANEL ─────────────────── */
Route::middleware(['auth', 'modir2'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                                   [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/users/{user}/change-password',      [AdminController::class, 'changePassword'])->name('users.change-password');
    Route::post('/users/{user}/update-phone',         [AdminController::class, 'updatePhone'])->name('users.update-phone');
    Route::post('/users/{user}/update-role',          [AdminController::class, 'updateRole'])->name('users.update-role');
    Route::post('/users/{user}/subscription',         [AdminController::class, 'updateSubscription'])->name('users.subscription');
    Route::post('/bulk-extend',                       [AdminController::class, 'bulkExtendSubscriptions'])->name('bulk-extend');
    Route::post('/users',                             [AdminController::class, 'createUser'])->name('users.create');
    Route::delete('/users/{user}',                    [AdminController::class, 'deleteUser'])->name('users.delete');
    Route::get('/users/{user}/purchases',             [AdminController::class, 'userPurchases'])->name('users.purchases');
    Route::get('/users/export',                       [AdminController::class, 'exportUsers'])->name('users.export');
});

Route::middleware(['auth', 'manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/top-users',                   [AdminController::class, 'topUsers'])->name('top-users');
    Route::get('/ai-models',                  [AdminController::class, 'aiModelsIndex'])->name('ai-models.index');
    Route::get('/ai-models/create',           [AdminController::class, 'aiModelsCreate'])->name('ai-models.create');
    Route::post('/ai-models',                 [AdminController::class, 'aiModelsStore'])->name('ai-models.store');
    Route::get('/ai-models/{aiModel}/edit',   [AdminController::class, 'aiModelsEdit'])->name('ai-models.edit');
    Route::put('/ai-models/{aiModel}',        [AdminController::class, 'aiModelsUpdate'])->name('ai-models.update');
    Route::delete('/ai-models/{aiModel}',     [AdminController::class, 'aiModelsDestroy'])->name('ai-models.destroy');
    Route::get('/ai-proxies',                 [AdminController::class, 'aiProxyServersIndex'])->name('ai-proxies.index');
    Route::post('/ai-proxies',                [AdminController::class, 'aiProxyServersStore'])->name('ai-proxies.store');
    Route::put('/ai-proxies/{aiProxyServer}', [AdminController::class, 'aiProxyServersUpdate'])->name('ai-proxies.update');
    Route::delete('/ai-proxies/{aiProxyServer}', [AdminController::class, 'aiProxyServersDestroy'])->name('ai-proxies.destroy');
    Route::post('/ai-proxies/{aiProxyServer}/test', [AdminController::class, 'aiProxyServersTest'])->name('ai-proxies.test');
    Route::post('/ai-proxies/{aiProxyServer}/provision', [AdminController::class, 'aiProxyServersProvision'])->name('ai-proxies.provision');
    Route::get('/stats',                      [AdminController::class, 'stats'])->name('stats');
    Route::get('/reports/repeat-purchasers',   [AdminController::class, 'repeatPurchasersExport'])->name('reports.repeat-purchasers');
    Route::get('/system',                     [AdminController::class, 'systemSettings'])->name('system');
    Route::post('/system/settings',           [AdminController::class, 'systemSettingsSave'])->name('system.settings.save');
    Route::post('/system/memory/reset',        [AdminController::class, 'resetConversationMemories'])->name('system.memory.reset');
    Route::get('/ai-test',                    [AdminController::class, 'aiTest'])->name('ai-test');
    Route::get('/ai-usage',                   [AdminController::class, 'aiUsage'])->name('ai-usage');
    Route::get('/ai-errors',                  [AdminController::class, 'aiErrors'])->name('ai-errors');
    Route::post('/ai-test/run',               [AdminController::class, 'aiTestRun'])->name('ai-test.run');
    Route::get('/chatbot-settings',           [AdminController::class, 'chatbotSettings'])->name('chatbot-settings');
    Route::post('/chatbot-settings',          [AdminController::class, 'chatbotSettingsSave'])->name('chatbot-settings.save');
    Route::post('/chatbot-settings/reset',    [AdminController::class, 'chatbotSettingsReset'])->name('chatbot-settings.reset');
});
