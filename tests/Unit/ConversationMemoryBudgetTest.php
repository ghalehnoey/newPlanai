<?php

namespace Tests\Unit;

use App\Services\ConversationMemoryService;
use App\Services\LocalMemorySummarizer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ConversationMemoryBudgetTest extends TestCase
{
    public function test_context_never_keeps_an_oversized_raw_turn_outside_the_budget(): void
    {
        $service = new ConversationMemoryService(new LocalMemorySummarizer());
        $turns = [];

        for ($turn = 0; $turn < 3; $turn++) {
            $turns[] = [
                ['id' => $turn * 2 + 1, 'role' => 'user', 'content' => str_repeat('پرسش حقوقی طولانی با اطلاعات پرونده. ', 350)],
                ['id' => $turn * 2 + 2, 'role' => 'assistant', 'content' => str_repeat('تحلیل مستند و نتیجه نهایی پاسخ. ', 350)],
            ];
        }

        $result = $this->invoke($service, 'fitContextBudget', [
            $turns,
            str_repeat("- کاربر: شماره پرونده ۱۲۳۴ و تصمیم مهم\n", 100),
            [['title' => 'پرونده قبلی', 'summary' => str_repeat('خلاصه مرتبط ', 200), 'score' => 50]],
            3000,
            900,
        ]);

        self::assertLessThanOrEqual(3000, $result['estimated_tokens']);
        self::assertSame(1, $result['recent_turns_used']);
        self::assertTrue($result['trimmed']);
        self::assertTrue($result['memory_used']);
    }

    public function test_old_files_are_only_reused_for_an_explicit_file_follow_up(): void
    {
        $service = new ConversationMemoryService(new LocalMemorySummarizer());

        self::assertTrue($this->invoke($service, 'refersToPriorFile', ['صفحه دوم همان PDF را هم تحلیل کن']));
        self::assertFalse($this->invoke($service, 'refersToPriorFile', ['یک سؤال حقوقی تازه دارم']));
    }

    public function test_cross_chat_budget_is_shared_between_all_selected_conversations(): void
    {
        $service = new ConversationMemoryService(new LocalMemorySummarizer());
        $result = $this->invoke($service, 'buildCrossChatMemory', [[
            ['title' => 'پرونده اول', 'summary' => 'نشانگر-اول ' . str_repeat('خلاصه اول ', 100), 'score' => 30],
            ['title' => 'پرونده دوم', 'summary' => 'نشانگر-دوم ' . str_repeat('خلاصه دوم ', 100), 'score' => 20],
            ['title' => 'پرونده سوم', 'summary' => 'نشانگر-سوم ' . str_repeat('خلاصه سوم ', 100), 'score' => 10],
        ], 900, 900]);

        self::assertStringContainsString('نشانگر-اول', $result);
        self::assertStringContainsString('نشانگر-دوم', $result);
        self::assertStringContainsString('نشانگر-سوم', $result);
        self::assertLessThanOrEqual(900, mb_strlen($result));
    }

    private function invoke(object $target, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
