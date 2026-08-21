<?php

namespace App\Services;

use App\Exceptions\AiRequestException;
use App\Models\AiModel;
use App\Models\AiProxyServer;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AiProxyRouter
{
    private const DIRECT_BLOCK_TTL_SECONDS = 300;

    /**
     * @return array<int, array{type:string,label:string,proxy:?AiProxyServer,proxy_url:?string}>
     */
    public function routesFor(AiModel $model): array
    {
        $mode = $model->proxy_mode ?: ($model->use_proxy ? 'proxy' : 'direct');
        $direct = [[
            'type' => 'direct',
            'label' => 'direct',
            'proxy' => null,
            'proxy_url' => null,
        ]];
        $proxies = $this->proxyRoutes($model);

        if ($mode === 'direct') {
            return $direct;
        }

        if ($mode === 'proxy') {
            return $proxies;
        }

        if (Cache::get($this->directBlockCacheKey($model), false) && $proxies !== []) {
            return [...$proxies, ...$direct];
        }

        return [...$direct, ...$proxies];
    }

    /** @return array<int, array{type:string,label:string,proxy:?AiProxyServer,proxy_url:?string}> */
    private function proxyRoutes(AiModel $model): array
    {
        $servers = AiProxyServer::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($model->proxy_server_id) {
            $servers = $servers
                ->sortBy(static fn (AiProxyServer $server) => $server->id === $model->proxy_server_id ? 0 : 1)
                ->values();
        }

        $routes = $servers->map(static fn (AiProxyServer $server): array => [
            'type' => 'proxy',
            'label' => $server->name,
            'proxy' => $server,
            'proxy_url' => $server->proxyUrl(),
        ])->all();

        if ($routes === [] && filled($model->proxy_url)) {
            $routes[] = [
                'type' => 'proxy',
                'label' => 'legacy-proxy',
                'proxy' => null,
                'proxy_url' => (string) $model->proxy_url,
            ];
        }

        return $routes;
    }

    /** @param array<int|string, mixed> $options */
    public function applyCurlProxy(array &$options, ?string $proxyUrl): void
    {
        if (!filled($proxyUrl)) {
            return;
        }

        $scheme = strtolower((string) parse_url($proxyUrl, PHP_URL_SCHEME));
        $options[CURLOPT_PROXY] = $proxyUrl;
        $options[CURLOPT_PROXYTYPE] = match ($scheme) {
            'socks5h' => CURLPROXY_SOCKS5_HOSTNAME,
            'socks5' => CURLPROXY_SOCKS5,
            'https' => defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : CURLPROXY_HTTP,
            default => CURLPROXY_HTTP,
        };
    }

    public function shouldRetryThroughProxy(Throwable $exception): bool
    {
        if ($exception instanceof AiRequestException) {
            if ($exception->httpCode === 403 && $this->isAccountOrPermissionError($exception->getMessage())) {
                return false;
            }

            return $exception->curlErrno !== 0
                || in_array($exception->httpCode, [403, 408, 451], true)
                || $exception->httpCode >= 500;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'http error 403');
    }

    public function markDirectSuccess(AiModel $model): void
    {
        Cache::forget($this->directBlockCacheKey($model));
    }

    public function markDirectFailure(AiModel $model, Throwable $exception): void
    {
        if ($this->shouldRetryThroughProxy($exception)) {
            Cache::put($this->directBlockCacheKey($model), true, self::DIRECT_BLOCK_TTL_SECONDS);
        }
    }

    public function markProxyResult(?AiProxyServer $proxy, bool $success, int $latencyMs, ?string $error = null, ?string $outboundIp = null): void
    {
        if (!$proxy) {
            return;
        }

        $proxy->forceFill([
            'last_status' => $success ? 'up' : 'down',
            'last_checked_at' => now(),
            'last_latency_ms' => max(0, $latencyMs),
            'last_outbound_ip' => $success ? $outboundIp : $proxy->last_outbound_ip,
            'last_error' => $success ? null : mb_substr($this->sanitizeError((string) $error), 0, 1000),
        ])->save();
    }

    /** @return array<string, mixed> */
    public function testProxy(AiProxyServer $proxy): array
    {
        $ch = curl_init('https://api.ipify.org?format=json');
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(3, (int) $proxy->connect_timeout),
            CURLOPT_TIMEOUT => max(8, (int) $proxy->connect_timeout + 8),
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        $this->applyCurlProxy($options, $proxy->proxyUrl());
        curl_setopt_array($ch, $options);

        $startedAt = microtime(true);
        $raw = curl_exec($ch);
        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = is_string($raw) ? json_decode($raw, true) : null;
        $outboundIp = is_array($data) ? ($data['ip'] ?? null) : null;
        $success = $errno === 0 && $httpCode === 200 && filter_var($outboundIp, FILTER_VALIDATE_IP);
        $diagnosis = $success
            ? 'پراکسی سالم است و IP خروجی دریافت شد.'
            : $this->diagnose($errno, $httpCode, $error);

        $this->markProxyResult($proxy, (bool) $success, $latency, $diagnosis . ($error ? " ({$error})" : ''), $outboundIp);

        return [
            'ok' => (bool) $success,
            'http' => $httpCode,
            'elapsed_ms' => $latency,
            'outbound_ip' => $outboundIp,
            'diagnosis' => $diagnosis,
        ];
    }

    public function diagnose(int $curlErrno, int $httpCode, string $curlError = ''): string
    {
        if ($this->isCreditError($curlError)) {
            return 'اعتبار API تمام شده یا حساب به سقف هزینهٔ ماهانه رسیده است.';
        }

        if ($this->isPermissionError($curlError)) {
            return 'حساب یا کلید API مجوز استفاده از این مدل را ندارد.';
        }

        if ($curlErrno === 7) {
            return 'اتصال به پراکسی رد شد؛ سرویس یا پورت در دسترس نیست.';
        }

        if ($curlErrno === 28) {
            return 'مهلت اتصال تمام شد.';
        }

        if ($curlErrno === 6) {
            return 'نام میزبان resolve نشد.';
        }

        if ($curlErrno !== 0) {
            return 'خطای شبکه: ' . $this->sanitizeError($curlError);
        }

        if ($httpCode === 403) {
            return 'دسترسی با HTTP 403 رد شد؛ اگر پیام API مالی/مجوزی نیست، محدودیت IP یا منطقه محتمل است.';
        }

        if ($httpCode === 401) {
            return 'کلید API نامعتبر یا فاقد دسترسی است.';
        }

        if ($httpCode === 429) {
            return 'محدودیت تعداد درخواست یا اعتبار حساب API.';
        }

        return $httpCode > 0 ? "پاسخ HTTP {$httpCode} دریافت شد." : 'اتصال برقرار نشد.';
    }

    public function sanitizeError(string $error): string
    {
        return (string) preg_replace('#(socks5h?|https?)://([^\s/@:]+):([^\s/@]+)@#i', '$1://***:***@', $error);
    }

    private function isAccountOrPermissionError(string $message): bool
    {
        return $this->isCreditError($message) || $this->isPermissionError($message);
    }

    private function isCreditError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'available credits')
            || str_contains($message, 'spending limit')
            || str_contains($message, 'insufficient_quota')
            || str_contains($message, 'billing');
    }

    private function isPermissionError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'does not have access')
            || str_contains($message, 'not authorized for model')
            || str_contains($message, 'permission denied');
    }

    private function directBlockCacheKey(AiModel $model): string
    {
        return 'ai:direct-blocked:' . $model->id;
    }
}
