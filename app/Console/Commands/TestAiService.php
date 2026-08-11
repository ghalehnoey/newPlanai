<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiApiService;

class TestAiService extends Command
{
    // نام کامند
    protected $signature = 'ai:test {model}';

    // توضیحات کامند
    protected $description = 'Test AI Service completely isolated';

    public function handle(AiApiService $aiApiService)
    {
        $model = $this->argument('model');
        $this->info("🚀 Testing Model: {$model}");
        $this->info("Waiting for response...\n");

        $question = 'یک پاراگراف کوتاه درباره زبان برنامه نویسی PHP بنویس.';
        $systemPrompt = 'تو یک دستیار برنامه‌نویس هستی.';
        $history = []; // تاریخچه خالی برای تست

        try {
            // ارسال دقیق پارامترها به ترتیبی که در سرویس تعریف شده است
            $aiApiService->stream(
                $question,
                $model,
                $systemPrompt,
                $history,
                [], // آرگومان پنجم: فایل‌های آپلود شده (در تست خالی است)
                function ($chunk) {
                    echo $chunk; // آرگومان ششم: تابع چاپ استریم
                }
            );

            echo "\n";
            $this->info("\n✅ Test Completed Successfully.");

        } catch (\Exception $e) {
            $this->error("\n❌ Error Occurred!");
            $this->error("Message: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
        }
    }
}
