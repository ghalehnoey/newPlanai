<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\SendSms;
use Illuminate\Support\Facades\Log;

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

    public function showForgetForm()
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $request->merge(['phone' => $this->normalizeDigits($request->phone ?? '')]);

        $request->validate(['phone' => 'required|exists:users,phone']);

        $otp = rand(100000, 999999);
        $patternValue = (string) $otp;

        User::where('phone', $request->phone)->update([
            'otp_code'       => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        if (! SendSms::send($request->phone, $patternValue)) {
            Log::error('SMS Send Failed for phone: ' . $request->phone, ['otp' => $otp]);
            return back()->withErrors(['error' => '??? ?? ????? ?????. ???? ?????? ???? ?????? ????.']);
        }

        session(['reset_phone' => $request->phone]);

        return redirect()->route('password.resetForm')
            ->with('success', '?? ??????? ?? ?????? ????? ??');
    }

    public function showResetForm()
    {
        return view('auth.reset-password', [
            'phone' => session('reset_phone')
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone'    => 'required',
            'otp'      => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::where('phone', $request->phone)
            ->where('otp_code', $request->otp)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (! $user) {
            return back()->withErrors(['error' => '?? ?????? ?? ????? ???']);
        }

        $user->password       = bcrypt($request->password);
        $user->otp_code       = null;
        $user->otp_expires_at = null;
        $user->save();

        session()->forget('reset_phone');

        return redirect()->route('login')
            ->with('success', '??? ???? ?? ?????? ????? ???');
    }
}