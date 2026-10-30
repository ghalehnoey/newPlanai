<?php

namespace App\Services;

use App\Models\AiModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SmartModelRouter
{
    public const SOURCE_MODEL_KEY = 'chatgpt-5.5';
    public const TARGET_MODEL_KEY = 'gpt-5.4-mini-router';

    public const SETTING_KEYS = [
        'ai_smart_routing_enabled',
        'ai_smart_routing_max_characters',
    ];

    public const DEFAULTS = [
        'ai_smart_routing_enabled' => 0,
        'ai_smart_routing_max_characters' => 1500,
    ];

    private const SETTINGS_CACHE_KEY = 'ai_smart_routing_settings:v1';

    /** @return array<string, int> */
    public function settings(): array
    {
        return Cache::remember(self::SETTINGS_CACHE_KEY, 60, static function (): array {
            $stored = DB::table('settings')
                ->whereIn('key', self::SETTING_KEYS)
                ->pluck('value', 'key');
            $settings = self::DEFAULTS;

            foreach (self::SETTING_KEYS as $key) {
                if ($stored->has($key)) {
                    $settings[$key] = (int) $stored->get($key);
                }
            }

            return $settings;
        });
    }

    public function forgetSettingsCache(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    /**
     * @return array{routed:bool,effective_model:string,reason:string}
     */
    public function decide(string $question, string $requestedModel, bool $hasFiles): array
    {
        $settings = $this->settings();
        $skip = fn (string $reason): array => [
            'routed' => false,
            'effective_model' => $requestedModel,
            'reason' => $reason,
        ];

        if (!(bool) $settings['ai_smart_routing_enabled']) {
            return $skip('disabled');
        }
        if ($requestedModel !== self::SOURCE_MODEL_KEY) {
            return $skip('source_model_not_selected');
        }
        if ($hasFiles) {
            return $skip('files_present');
        }

        $maxCharacters = max(200, min(4000, (int) $settings['ai_smart_routing_max_characters']));
        $reason = $this->classify($question, $maxCharacters);
        if ($reason === null) {
            return $skip('complex_or_uncertain');
        }

        $targetAvailable = AiModel::query()
            ->where('key', self::TARGET_MODEL_KEY)
            ->where('is_active', true)
            ->exists();
        if (!$targetAvailable) {
            return $skip('target_unavailable');
        }

        return [
            'routed' => true,
            'effective_model' => self::TARGET_MODEL_KEY,
            'reason' => $reason,
        ];
    }

    /**
     * Conservative, local-only classification. No extra AI request is made.
     */
    public function classify(string $question, int $maxCharacters = 1500): ?string
    {
        $text = $this->normalize($question);
        $length = mb_strlen($text);
        if ($text === '' || $length > $maxCharacters) {
            return null;
        }

        $complexPatterns = [
            '/(?:لایحه|دادخواست|شکواییه|اظهارنامه|قرارداد|وصیت.?نامه).{0,25}(?:بنویس|تنظیم|تهیه|اصلاح)/u',
            '/(?:تحلیل|بررسی|محاسبه|استخراج|استدلال|استناد).{0,20}(?:کن|کنید|شود)/u',
            '/(?:پرونده من|پرونده ام|دادنامه|رای دادگاه|رأی دادگاه|شماره پرونده)/u',
            '/(?:ماده|تبصره|بند)\s*[0-9۰-۹]+/u',
            '/(?:طبق|بر اساس|براساس).{0,30}(?:مدارک|اطلاعات|شرح|جزئیات)/u',
        ];
        foreach ($complexPatterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return null;
            }
        }

        if (preg_match('/(?:خلاصه|چکیده|کوتاه|در چند خط|نکات اصلی)/u', $text) === 1) {
            return 'summary';
        }

        if ($length <= 120 && preg_match('/^(?:سلام|درود|وقت بخیر|ممنون|متشکرم|سپاس|خداحافظ)/u', $text) === 1) {
            return 'small_talk';
        }

        $sentenceCount = preg_match_all('/[؟?!\n.]/u', $text);
        if ($length <= 500 && $sentenceCount <= 2 && preg_match(
            '/(?:چیست|چیه|یعنی چه|تعریف|تفاوت|فرق|شرایط|مدارک لازم|مهلت|هزینه|چقدر|چند|آیا|چگونه|چطور|از کجا|کجا)/u',
            $text,
        ) === 1) {
            return 'simple_faq';
        }

        return null;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], trim($text));
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}
