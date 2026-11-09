<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\SendSms;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    private function normalizeDigits(string $v): string
    {
        // Convert Persian (U+06F0-06F9) and Arabic-Indic (U+0660-0669) digits to ASCII
        return preg_replace_callback('/[\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', function ($m) {
            $ord = mb_ord($m[0], 'UTF-8');
            return (string) ($ord >= 0x06F0 ? $ord - 0x06F0 : $ord - 0x0660);
        }, $v);
    }

    public function showForgetForm(Request $request)
    {
        $phone = $this->normalizeDigits((string) session('recovery_phone', $request->query('phone', '')));
        if (! preg_match('/^09\d{9}$/', $phone)) {
            $phone = '';
        }

        return view('auth.forgot-password', compact('phone'));
    }

    public function sendOtp(Request $request)
    {
        $request->merge(['phone' => $this->normalizeDigits($request->phone ?? '')]);

        $request->validate([
            'phone' => ['required', 'regex:/^09\d{9}$/', 'exists:users,phone'],
        ], [
            'phone.regex' => 'شماره موبایل را به‌صورت 09XXXXXXXXX وارد کنید.',
            'phone.exists' => 'حسابی با این شماره پیدا نشد. ابتدا ثبت‌نام کنید.',
        ]);

        $otp = random_int(100000, 999999);
        $patternValue = (string) $otp;

        User::where('phone', $request->phone)->update([
            'otp_code'       => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        if (! SendSms::send($request->phone, $patternValue)) {
            Log::error('SMS Send Failed for phone: ' . $request->phone, ['otp' => $otp]);
            return back()->withInput()->withErrors(['error' => 'ارسال پیامک انجام نشد. لطفاً چند دقیقه دیگر دوباره تلاش کنید.']);
        }

        session(['reset_phone' => $request->phone]);

        return redirect()->route('password.resetForm')
            ->with('success', 'کد تأیید به شماره شما پیامک شد. این کد ۵ دقیقه اعتبار دارد.');
    }

    public function showResetForm()
    {
        if (! session('reset_phone')) {
            return redirect()->route('password.forgot')
                ->withErrors(['error' => 'ابتدا شماره موبایل خود را وارد کنید.']);
        }

        return view('auth.reset-password', [
            'phone' => session('reset_phone')
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->merge([
            'phone' => $this->normalizeDigits((string) $request->phone),
            'otp' => $this->normalizeDigits((string) $request->otp),
        ]);

        $request->validate([
            'phone'    => ['required', 'regex:/^09\d{9}$/'],
            'otp'      => ['required', 'digits:6'],
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::where('phone', $request->phone)
            ->where('otp_code', $request->otp)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (! $user) {
            return back()->withInput($request->only('phone'))->withErrors(['error' => 'کد تأیید اشتباه است یا اعتبار آن تمام شده؛ کد جدید دریافت کنید.']);
        }

        $user->password       = Hash::make($request->password);
        $user->otp_code       = null;
        $user->otp_expires_at = null;
        $user->save();

        session()->forget('reset_phone');

        return redirect()->route('login')
            ->with('success', 'رمز عبور با موفقیت تغییر کرد. اکنون می‌توانید وارد حساب خود شوید.');
    }
}
