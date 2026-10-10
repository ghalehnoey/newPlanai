<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $apiUrl = sprintf(
            'https://api.cafebazaar.ir/api/v2/payments/%s/tokens/%s/',
            rawurlencode($packageName),
            rawurlencode($data['purchase_token'])
        );

        try {
            $bazaar = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->get($apiUrl);
        } catch (\Throwable $e) {
            Log::error('Bazaar receipt request failed', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'receipt validation unavailable',
            ], 502);
        }

        if (!$bazaar->successful()) {
            Log::warning('Bazaar receipt validation rejected', ['status' => $bazaar->status()]);
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'receipt validation failed',
            ], 422);
        }

        $receipt = $bazaar->json();
        $receiptState = (int) data_get($receipt, 'purchaseState', -1);
        $receiptProduct = data_get($receipt, 'productId', data_get($receipt, 'product_id'));
        if ($receiptState !== 0 || $receiptProduct !== $data['product_id']) {
            return response()->json([
                'ok' => false,
                'consume' => false,
                'message' => 'receipt is not a valid purchase',
            ], 422);
        }

        $user = $request->user();
        $token = $data['purchase_token'];
        $existing = Subscription::where('bazaar_purchase_token', $token)->first();
        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                Log::warning('Bazaar token replayed by another user', ['subscription_id' => $existing->id]);
                return response()->json(['ok' => false, 'consume' => false, 'message' => 'purchase token already used'], 409);
            }

            return response()->json([
                'ok' => true,
                'consume' => false,
                'message' => 'receipt already granted',
            ]);
        }

        DB::transaction(function () use ($data, $plan, $user, $receipt, $token) {
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'questions_used' => 0,
                'expires_at' => now()->addDays(30),
                'paid' => true,
                'paid_amount_toman' => (int) $plan->price,
                'gateway' => 'cafebazaar',
                'bazaar_purchase_token' => $token,
                'bazaar_order_id' => $data['order_id'] ?? null,
                'bazaar_product_id' => $data['product_id'],
                'bazaar_payload' => $data['payload'] ?? null,
                'bazaar_receipt' => json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        });

        return response()->json([
            'ok' => true,
            'consume' => false,
            'message' => 'receipt verified and subscription activated',
        ]);
    }
}
