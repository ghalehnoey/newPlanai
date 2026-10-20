<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\BazaarSubscriptionVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BazaarReceiptController extends Controller
{
    public function verify(Request $request)
    {
        if (!config('services.bazaar.enabled', false)) {
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'Bazaar checkout is disabled',
            ], 503);
        }

        $accessToken = (string) config('services.bazaar.access_token', '');
        if ($accessToken === '') {
            Log::error('Bazaar verification is enabled without an access token');
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'Bazaar verification is not configured',
            ], 503);
        }

        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/'],
            'purchase_token' => ['required', 'string', 'max:512'],
            'order_id' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'string', 'max:4096'],
            'purchase_state' => ['required', 'string'],
            'subscription' => ['required', 'boolean', 'accepted'],
            'purchase_time' => ['nullable', 'integer'],
            'original_json' => ['nullable', 'string', 'max:20000'],
            'data_signature' => ['nullable', 'string', 'max:4096'],
        ]);

        $packageName = (string) config('services.bazaar.package_name');
        abort_unless(hash_equals($packageName, $data['package_name']), 422, 'invalid package');

        $productPlanIds = (array) config('services.bazaar.product_plan_ids', []);
        $planId = $productPlanIds[$data['product_id']] ?? null;
        $plan = $planId ? Plan::whereKey($planId)->where('is_active', true)->first() : null;
        abort_unless($plan, 422, 'invalid or unmapped product');

        try {
            $verification = app(BazaarSubscriptionVerifier::class)->verify(
                $packageName,
                $data['product_id'],
                $data['purchase_token'],
                $accessToken
            );
        } catch (\Throwable $e) {
            Log::error('Bazaar receipt request failed', ['exception' => get_class($e)]);
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'ارتباط با سرور پرداخت بازار برقرار نشد. لطفاً دوباره تلاش کنید.',
            ], 502);
        }

        if (!$verification['ok']) {
            Log::warning('Bazaar receipt validation rejected', ['status' => $verification['status']]);
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => $verification['message'],
            ], 422);
        }

        $receipt = $verification['receipt'];
        $receipt['verifiedPurchaseToken'] = $data['purchase_token'];
        $expiresAt = $verification['expires_at'];
        $user = $request->user();
        // Renewal tokens share a stable subscription identity. Restoring a
        // receipt must not add another month or reset the question allowance.
        $token = $verification['linked_subscription_token'];
        try {
            $result = DB::transaction(function () use ($data, $plan, $user, $receipt, $token, $expiresAt) {
                $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = Subscription::whereIn('bazaar_purchase_token', [$token, $data['purchase_token']])
                    ->lockForUpdate()->first();
                if ($existing && (int) $existing->user_id !== (int) $user->id) {
                    return 'conflict';
                }
                if ($existing && $existing->expires_at && !$expiresAt->greaterThan($existing->expires_at)) {
                    return 'already_granted';
                }
                $values = [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'questions_used' => 0,
                    'expires_at' => $expiresAt,
                    'paid' => true,
                    'paid_amount_toman' => (int) $plan->price,
                    'gateway' => 'cafebazaar',
                    'bazaar_purchase_token' => $token,
                    'bazaar_order_id' => $data['order_id'] ?? null,
                    'bazaar_product_id' => $data['product_id'],
                    'bazaar_payload' => $data['payload'] ?? null,
                    'bazaar_receipt' => json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
                if ($existing) {
                    $existing->update($values);
                } else {
                    Subscription::create($values);
                }
                return 'granted';
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            // Enforce the unique token even across concurrent website accounts.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $existing = Subscription::where('bazaar_purchase_token', $token)->first();
            if (!$existing) {
                throw $e;
            }
            $result = (int) $existing->user_id === (int) $user->id ? 'already_granted' : 'conflict';
        }
        if ($result === 'conflict') {
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'این خرید قبلاً به حساب دیگری متصل شده است.',
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'consume' => false,
            'message' => 'receipt verified and subscription activated',
        ]);
    }
}
