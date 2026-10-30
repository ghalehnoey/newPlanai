<?php

namespace Tests\Unit;

use App\Services\SmartModelRouter;
use PHPUnit\Framework\TestCase;

class SmartModelRouterTest extends TestCase
{
    private SmartModelRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new SmartModelRouter();
    }

    public function test_it_routes_simple_faq_questions(): void
    {
        $this->assertSame('simple_faq', $this->router->classify('شرایط دریافت ارث چیست؟'));
        $this->assertSame('simple_faq', $this->router->classify('تفاوت وصیت و ارث چیه؟'));
    }

    public function test_it_routes_explicit_summaries_and_small_talk(): void
    {
        $this->assertSame('summary', $this->router->classify('لطفاً پاسخ قبلی را در چند خط خلاصه کن'));
        $this->assertSame('small_talk', $this->router->classify('سلام وقت بخیر'));
    }

    public function test_it_keeps_complex_legal_work_on_the_primary_model(): void
    {
        $this->assertNull($this->router->classify('با توجه به جزئیات پرونده من یک لایحه اعتراض تنظیم کن'));
        $this->assertNull($this->router->classify('سهم هر وارث را بر اساس این اطلاعات محاسبه کن'));
        $this->assertNull($this->router->classify('ماده ۴۷۷ قانون آیین دادرسی چه کاربردی دارد؟'));
    }

    public function test_it_rejects_text_over_the_configured_limit(): void
    {
        $this->assertNull($this->router->classify(str_repeat('شرایط چیست؟ ', 100), 200));
    }
}
