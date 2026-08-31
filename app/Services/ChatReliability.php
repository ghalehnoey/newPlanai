<?php

namespace App\Services;

use App\Exceptions\AiRequestException;
use Illuminate\Support\Facades\Log;
use LengthException;
use Throwable;

final class ChatReliability
{
    // Conservative byte budget: text token counts cannot exceed UTF-8 bytes.
    // Leave room for the 4k output allowance and protocol/image overhead.
    public const INPUT_BUDGET = 22000;

    public static function prepareMessages(array $messages): array
    {
        $cost = static function (array $message): int {
            $content = $message['content'] ?? '';
            if (is_string($content)) {
                return strlen($content) + 16;
            }
            return array_sum(array_map(static fn(array $part): int =>
                ($part['type'] ?? '') === 'image_url' ? 2000 : strlen($part['text'] ?? '') + 16,
                $content)) + 16;
        };
        $trimmed = false;
        while (array_sum(array_map($cost, $messages)) > self::INPUT_BUDGET && count($messages) > 2) {
            array_splice($messages, 1, 1);
            // Never leave an orphan assistant answer at the start of retained history.
            while (count($messages) > 2 && ($messages[1]['role'] ?? '') === 'assistant') {
                array_splice($messages, 1, 1);
            }
            $trimmed = true;
        }
        if (array_sum(array_map($cost, $messages)) > self::INPUT_BUDGET) {
            throw new LengthException('chat_context_too_large');
        }
        return ['messages' => array_values($messages), 'trimmed' => $trimmed];
    }

    public static function canRetry(Throwable $error): bool
    {
        if (!$error instanceof AiRequestException) {
            return false;
        }
        if (preg_match('/quota|billing|credit|spend|request too large|context.length|maximum context/i', $error->getMessage())) {
            return false;
        }
        return in_array($error->curlErrno, [6, 7, 18, 28, 35, 52, 55, 56, 92], true)
            || in_array($error->httpCode, [408, 429, 500, 502, 503, 504], true);
    }

    public static function publicError(?Throwable $error, bool $partial = false): array
    {
        if ($partial) {
            return ['code' => 'incomplete_response', 'message' => 'دریافت پاسخ کامل نشد؛ متن نمایش‌داده‌شده ناقص است. اعتباری کسر نشد. می‌توانید دوباره تلاش کنید.'];
        }
        if ($error instanceof LengthException) {
            return ['code' => 'context_too_large', 'message' => 'حجم سؤال یا فایل برای یک پاسخ زیاد است. لطفاً متن یا فایل را به بخش‌های کوچک‌تر تقسیم کنید. اعتباری کسر نشد.'];
        }
        if ($error instanceof AiRequestException && $error->httpCode === 429) {
            return ['code' => 'provider_busy', 'message' => 'سرویس پاسخ‌گویی فعلاً ظرفیت کافی ندارد. کمی بعد دوباره تلاش کنید یا مدل دیگری انتخاب کنید. اعتباری کسر نشد.'];
        }
        return ['code' => 'ai_unavailable', 'message' => 'این بار پاسخ دریافت نشد. پیام شما محفوظ است؛ کمی بعد دوباره تلاش کنید یا مدل دیگری انتخاب کنید. اعتباری کسر نشد.'];
    }

    public static function report(Throwable $error, array $context = []): void
    {
        // Deliberately exclude prompt text, upstream response bodies and credentials.
        try {
            Log::build([
                'driver' => 'daily',
                'path' => storage_path('logs/chat-errors.log'),
                'level' => 'warning',
                'days' => 14,
                'permission' => 0664,
            ])->warning('Chat request issue', array_merge(
                array_intersect_key($context, array_flip(['request_id', 'model', 'stage', 'partial'])),
                [
                    'exception' => get_class($error),
                    'http_status' => $error instanceof AiRequestException ? $error->httpCode : null,
                    'curl_errno' => $error instanceof AiRequestException ? $error->curlErrno : null,
                    'code' => self::publicError($error)['code'],
                ],
            ));
        } catch (Throwable) {
            // Logging must never turn a recoverable upstream error into another failure.
        }
    }
}
