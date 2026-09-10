<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
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

        $amount = (int) $plan->price * 10;

        if ($amount <= 0) {
            Log::warning('Invalid payment amount', [
                'userId' => Auth::id(),
                'planId' => $plan->id,
                'amount' => $amount,
            ]);

            return back()->with('error', 'مبلغ این پلن معتبر نیست. لطفاً با پشتیبانی تماس بگیرید.');
        }

        $callbackUrl = route('payment.callback');
        $paymentReferer = rtrim((string) config('app.url'), '/') . '/';

        try {
            $response = Http::withHeaders([
                'Referer' => $paymentReferer,
            ])->acceptJson()->asJson()->connectTimeout(5)->timeout(15)->post("https://gateway.zibal.ir/v1/request", [
                "merchant"    => $this->merchant,
                "amount"      => $amount,  // تبدیل تومان → ریال
                "callbackUrl" => $callbackUrl,
                "orderId"     => "PLAN-" . $plan->id . "-" . Auth::id(),
                "description" => "خرید پلن " . $plan->name,
            ]);

            $data = $response->json();

            if (!$response->successful() || !is_array($data) || (int) ($data["result"] ?? 0) !== 100 || empty($data["trackId"])) {
                Log::warning('Zibal payment request rejected', [
                    'userId' => Auth::id(),
                    'planId' => $plan->id,
                    'httpStatus' => $response->status(),
                    'result' => $data['result'] ?? null,
                    'message' => $data['message'] ?? null,
                ]);

                return back()->with("error", "خطا در اتصال به درگاه زیبال: " . ($data["message"] ?? 'پاسخ نامعتبر از درگاه'));
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
        $trackId = (string) $request->query('trackId', '');

        if ($trackId === '' || !ctype_digit($trackId)) {
            return redirect()->route('plans')->with("error", "شناسه پرداخت دریافت نشد.");
        }

        $existingSubscription = Subscription::where('zibal_track_id', $trackId)->first();

        if ($existingSubscription) {
            return $this->successfulPaymentRedirect(
                (int) $existingSubscription->user_id,
                'این پرداخت قبلاً تایید و پلن آن فعال شده است.'
            );
        }

        // تایید پرداخت
        try {
            $verifyResponse = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(15)->post("https://gateway.zibal.ir/v1/verify", [
                "merchant" => $this->merchant,
                "trackId"  => $trackId
            ]);

            $verify = $verifyResponse->json();

            if (!$verifyResponse->successful() || !is_array($verify)) {
                Log::warning('Invalid response from Zibal verify endpoint', [
                    'trackId' => $trackId,
                    'httpStatus' => $verifyResponse->status(),
                ]);

                return redirect()->route('plans')->with('error', 'پاسخ معتبر از درگاه دریافت نشد. لطفاً با پشتیبانی تماس بگیرید.');
            }
        } catch (\Throwable $e) {
            Log::error('Zibal payment verify failed', [
                'trackId' => $trackId,
                'error'   => $e->getMessage(),
            ]);

            $this->sendPaymentAlert("خطا تایید پرداخت trackId=" . $trackId . ": " . $e->getMessage());

            return redirect()->route('plans')->with('error', 'خطا در تایید پرداخت. لطفاً با پشتیبانی تماس بگیرید.');
        }

        if ((int) ($verify["result"] ?? 0) !== 100) {
            Log::warning('Zibal payment verification rejected', [
                'trackId' => $trackId,
                'result' => $verify['result'] ?? null,
                'message' => $verify['message'] ?? null,
            ]);

            return redirect()->route('plans')->with("error", "پرداخت موفق نبود: " . ($verify["message"] ?? 'وضعیت نامشخص'));
        }

        // استخراج اطلاعات
        $amount  = (int) ($verify["amount"] ?? 0);
        $orderId = $verify["orderId"] ?? null;

        if (!is_string($orderId) || !preg_match('/^PLAN-(\d+)-(\d+)$/', $orderId, $orderMatches)) {
            Log::error('Invalid Zibal orderId', [
                'trackId' => $trackId,
                'orderId' => $orderId,
            ]);

            return redirect()->route('plans')->with('error', 'اطلاعات سفارش معتبر نیست. لطفاً با پشتیبانی تماس بگیرید.');
        }

        $planId = (int) $orderMatches[1];
        $userId = (int) $orderMatches[2];
        $plan = Plan::find($planId);
        $user = User::find($userId);

        if (!$plan || !$user) {
            Log::error('Payment owner or plan not found', [
                'trackId' => $trackId,
                'planId' => $planId,
                'userId' => $userId,
            ]);

            return redirect()->route('plans')->with("error", "کاربر یا پلن مربوط به پرداخت یافت نشد.");
        }

        $expectedAmount = (int) $plan->price * 10;

        if ($amount !== $expectedAmount) {
            Log::critical('Zibal payment amount mismatch', [
                'trackId' => $trackId,
                'planId' => $planId,
                'userId' => $userId,
                'expectedAmount' => $expectedAmount,
                'paidAmount' => $amount,
            ]);

            return redirect()->route('plans')->with('error', 'مبلغ پرداخت با مبلغ پلن مطابقت ندارد. لطفاً با پشتیبانی تماس بگیرید.');
        }

        $subscription = DB::transaction(function () use ($trackId, $userId, $plan) {
            return Subscription::firstOrCreate(
                ['zibal_track_id' => $trackId],
                [
                    'user_id' => $userId,
                    'plan_id' => $plan->id,
                    'questions_used' => 0,
                    'expires_at' => now()->addDays(30),
                    'paid' => true,
                    'paid_amount_toman' => (int) $plan->price,
                ]
            );
        });

        Cache::forget('admin:financial-current-jalali');

        return $this->successfulPaymentRedirect(
            (int) $subscription->user_id,
            'پرداخت موفق بود و پلن شما فعال شد.'
        );
    }

    private function successfulPaymentRedirect(int $userId, string $message)
    {
        if ((int) Auth::id() === $userId) {
            return redirect()->route('home')->with('success', $message);
        }

        return redirect()->route('login')->with('success', $message . ' لطفاً وارد حساب خود شوید.');
    }
}
