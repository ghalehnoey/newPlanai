<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Plan;
use App\Models\Subscription;
use App\Helpers\SendSms;

class PaymentController extends Controller
{
    private $merchant;
    private const ALERT_PHONE = '09159703466';

    public function __construct()
    {
        // 🔹 فعلاً برای تست:
        //$this->merchant = "zibal";
       $this->merchant = "691899a1fa08c3000a7797fe";
    }

    private function sendPaymentAlert(string $message): void
    {
        try {
            SendSms::send(self::ALERT_PHONE, $message);
        } catch (\Throwable $e) {
            Log::error('Payment alert SMS failed', ['error' => $e->getMessage()]);
        }
    }

    // -------------------------------
    // ۱) شروع پرداخت
    // -------------------------------
    public function start(Request $request, Plan $plan)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $callbackUrl = route('payment.callback');

        try {
            $response = Http::timeout(15)->post("https://gateway.zibal.ir/v1/request", [
                "merchant"    => $this->merchant,
                "amount"      => $plan->price * 10,  // تبدیل تومان → ریال
                "callbackUrl" => $callbackUrl,
                "orderId"     => "PLAN-" . $plan->id . "-" . Auth::id(),
                "description" => "خرید پلن " . $plan->name,
            ]);

            $data = $response->json();

            if (($data["result"] ?? 0) != 100) {
                return back()->with("error", "خطا در اتصال به درگاه زیبال: " . ($data["message"] ?? 'نامشخص'));
            }

            // هدایت کاربر به صفحه پرداخت
            return redirect("https://gateway.zibal.ir/start/" . $data["trackId"]);

        } catch (\Throwable $e) {
            Log::error('Zibal payment start failed', [
                'userId' => Auth::id(),
                'planId' => $plan->id,
                'error'  => $e->getMessage(),
            ]);

            $this->sendPaymentAlert("خطا درگاه پرداخت: " . $e->getMessage());

            return back()->with('error', 'در حال حاضر اتصال به درگاه پرداخت برقرار نیست. لطفاً چند دقیقه دیگر تلاش کنید.');
        }
    }

    // -------------------------------
    // ۲) کال‌بک زیبال
    // -------------------------------
    public function callback(Request $request)
    {
        $trackId = $request->trackId;

        if (!$trackId) {
            return redirect()->route('plans')->with("error", "شناسه پرداخت دریافت نشد.");
        }

        // تایید پرداخت
        try {
            $verify = Http::timeout(15)->post("https://gateway.zibal.ir/v1/verify", [
                "merchant" => $this->merchant,
                "trackId"  => $trackId
            ])->json();
        } catch (\Throwable $e) {
            Log::error('Zibal payment verify failed', [
                'trackId' => $trackId,
                'error'   => $e->getMessage(),
            ]);

            $this->sendPaymentAlert("خطا تایید پرداخت trackId=" . $trackId . ": " . $e->getMessage());

            return redirect()->route('plans')->with('error', 'خطا در تایید پرداخت. لطفاً با پشتیبانی تماس بگیرید.');
        }

        if ($verify["result"] != 100) {
            return redirect()->route('plans')->with("error", "پرداخت موفق نبود: " . $verify["message"]);
        }

        // استخراج اطلاعات
        $amount    = $verify["amount"] ?? 0;
        $orderId   = $verify["orderId"] ?? null;

        // استخراج plan_id از orderId
        $planId = intval(explode("-", $orderId)[1]);
        $plan   = Plan::find($planId);

        if (!$plan) {
            return redirect()->route('plans')->with("error", "پلن یافت نشد.");
        }

        // ثبت اشتراک
        Subscription::create([
            'user_id'        => Auth::id(),
            'plan_id'        => $plan->id,
            'questions_used' => 0,
            'expires_at'     => now()->addDays(30),
            'paid'           => true,
            'gateway'        => 'zibal',
            'status'         => 'active',
            'activated_at'   => now(),
        ]);

        return redirect()->route("home")->with("success", "پرداخت موفق! پلن فعال شد.");
    }
}
