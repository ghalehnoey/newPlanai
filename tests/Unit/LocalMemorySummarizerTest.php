<?php

namespace Tests\Unit;

use App\Services\LocalMemorySummarizer;
use PHPUnit\Framework\TestCase;

class LocalMemorySummarizerTest extends TestCase
{
    public function test_it_preserves_high_value_facts_without_external_ai(): void
    {
        $service = new LocalMemorySummarizer();
        $result = $service->summarize([], [
            ['id' => 10, 'role' => 'user', 'content' => 'سلام'],
            ['id' => 11, 'role' => 'assistant', 'content' => 'در خدمتم'],
            ['id' => 12, 'role' => 'user', 'content' => 'شماره پرونده ۱۴۰۵۱۲۳ است و من می‌خواهم پاسخ‌ها کوتاه اما مستند باشند.'],
            ['id' => 13, 'role' => 'assistant', 'content' => 'نتیجه: برای اعتراض باید تا تاریخ ۱۴۰۵/۰۷/۱۵ اقدام شود.'],
        ], 1200);

        self::assertStringContainsString('۱۴۰۵۱۲۳', $result['summary']);
        self::assertStringContainsString('کوتاه اما مستند', $result['summary']);
        self::assertStringContainsString('۱۴۰۵/۰۷/۱۵', $result['summary']);
        self::assertStringNotContainsString('- کاربر: سلام', $result['summary']);
        self::assertGreaterThan(0, $result['estimated_tokens']);
    }

    public function test_it_deduplicates_rolling_items_and_respects_budget(): void
    {
        $service = new LocalMemorySummarizer();
        $first = $service->summarize([], [
            ['id' => 20, 'role' => 'user', 'content' => 'هدف من ابطال سند رسمی شماره ۱۲۳۴ است.'],
            ['id' => 21, 'role' => 'assistant', 'content' => 'پیشنهاد: اصل سند و گواهی ثبت را آماده کنید.'],
        ], 700);
        $second = $service->summarize($first['items'], [
            ['id' => 22, 'role' => 'user', 'content' => 'هدف من ابطال سند رسمی شماره ۱۲۳۴ است.'],
            ['id' => 23, 'role' => 'assistant', 'content' => str_repeat('نتیجه پرونده باید دقیق بررسی شود. ', 30)],
        ], 700);

        self::assertSame(1, substr_count($second['summary'], 'ابطال سند رسمی شماره ۱۲۳۴'));
        self::assertLessThanOrEqual(700, mb_strlen($second['summary']));
    }
}
