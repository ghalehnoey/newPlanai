<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class BazaarSubscriptionVerifier
{
    public function verify(string $packageName, string $productId, string $purchaseToken, string $apiSecret): array
    {
        // Accept initial and renewal tokens; use Bazaar's SKU and expiry.
        $url = sprintf(
            'https://pardakht.cafebazaar.ir/devapi/v2/api/applications/%s/active-subscriptions/%s/',
            rawurlencode($packageName),
            rawurlencode($purchaseToken)
        );
        $response = Http::withHeaders(['CAFEBAZAAR-PISHKHAN-API-SECRET' => $apiSecret])
            ->acceptJson()->connectTimeout(10)->timeout(20)->get($url);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'message' => 'اعتبارسنجی خرید بازار کامل نشد. لطفاً دوباره تلاش کنید.',
            ];
        }

        $subscriptions = $response->json('subscriptions');
        if (!is_array($subscriptions)) {
            return $this->invalidReceipt();
        }
        foreach ($subscriptions as $subscription) {
            if (!is_array($subscription)
                || ($subscription['kind'] ?? '') !== 'androidpublisher#subscriptionPurchase'
                || ($subscription['sku'] ?? '') !== $productId) {
                continue;
            }
            $linkedToken = $subscription['linkedSubscriptionToken'] ?? null;
            $expiryMs = filter_var($subscription['validUntilTimestampMsec'] ?? null, FILTER_VALIDATE_INT);
            if (!is_string($linkedToken) || $linkedToken === '' || strlen($linkedToken) > 512
                || $expiryMs === false || $expiryMs <= 0) {
                return $this->invalidReceipt();
            }
            try {
                // MySQL's existing expires_at column stores whole seconds.
                // Normalize before comparison so a restored millisecond receipt
                // cannot look like a renewal and reset the question allowance.
                $expiresAt = CarbonImmutable::createFromTimestampMs($expiryMs, 'UTC')
                    ->startOfSecond()->setTimezone(config('app.timezone', 'UTC'));
            } catch (\Throwable $e) {
                return $this->invalidReceipt();
            }
            if (!$expiresAt->isFuture()) {
                return $this->invalidReceipt();
            }
            return [
                'ok' => true,
                'status' => 200,
                'expires_at' => $expiresAt,
                'linked_subscription_token' => $linkedToken,
                'receipt' => $subscription,
            ];
        }
        return $this->invalidReceipt();
    }

    private function invalidReceipt(): array
    {
        return [
            'ok' => false,
            'status' => 422,
            'message' => 'اشتراک فعال و معتبر برای این طرح در بازار پیدا نشد.',
        ];
    }
}
