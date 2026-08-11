<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Helpers\SendSms;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const ADMIN_PHONE   = '09159703466';
    private const FAIL_LIMIT    = 20;
    private const FAIL_WINDOW   = 600;   // 10 minutes

    /* ----------------------------------------------------------
     |  تبدیل اعداد فارسی/عربی به انگلیسی
     | ---------------------------------------------------------- */
    private function normalizeDigits(string $value): string
    {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
        return str_replace(array_merge($persian, $arabic), array_merge($english, $english), $value);
    }

    /* ----------------------------------------------------------
     |  نمایش فرم ورود
     | ---------------------------------------------------------- */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /* ----------------------------------------------------------
     |  ورود کاربر (با ایمیل یا شماره موبایل)
     | ---------------------------------------------------------- */
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        // Normalize Persian/Arabic digits
        $loginInput = $this->normalizeDigits($request->login);
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user  = User::where($field, $loginInput)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Track login failures for alert
            $ip        = $request->ip();
            $cacheKey  = 'login_fail_' . $ip;
            $failCount = Cache::get($cacheKey, 0) + 1;
            Cache::put($cacheKey, $failCount, self::FAIL_WINDOW);

            // Send SMS alert to admin when threshold hit
            if ($failCount === self::FAIL_LIMIT) {
                try {
                    $msg = "هشدار: {$failCount} بار ورود ناموفق از IP {$ip} در ۱۰ دقیقه گذشته";
                    SendSms::send(self::ADMIN_PHONE, $msg);
                    Log::warning('Login failure alert sent', ['ip' => $ip, 'count' => $failCount]);
                } catch (\Throwable $e) {
                    Log::error('Failed to send login alert SMS', ['err' => $e->getMessage()]);
                }
            }

            return back()
                ->withErrors(['error' => 'اطلاعات ورود نادرست است.'])
                ->withInput();
        }

        Auth::login($user, $request->boolean('remember'));

        $active = method_exists($user, 'activeSubscription')
            ? $user->activeSubscription()
            : null;

        return $active
            ? redirect()->route('home')
            : redirect()->route('plans')->with('info', 'خوش آمدید! لطفاً یک پلن انتخاب کنید.');
    }

    /* ----------------------------------------------------------
     |  نمایش فرم ثبت‌نام
     | ---------------------------------------------------------- */
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /* ----------------------------------------------------------
     |  ثبت‌نام کاربر (شماره اجباری، ایمیل اختیاری)
     | ---------------------------------------------------------- */
    public function register(Request $request)
    {
        // Normalize phone digits before validation
        $request->merge(['phone' => $this->normalizeDigits($request->phone ?? '')]);

        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:20|unique:users,phone',
            'email'    => 'nullable|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name'           => $request->name,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'phone_verified' => false,
        ]);

        Auth::login($user);

        return redirect()->route('plans')
            ->with('info', 'ثبت‌نام با موفقیت انجام شد! لطفاً یک پلن انتخاب کنید.');
    }

    /* ----------------------------------------------------------
     |  خروج کاربر
     | ---------------------------------------------------------- */
    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
