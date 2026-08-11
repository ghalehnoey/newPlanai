<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // کلید داخلی مثل 'gemini'
            $table->string('name');                   // نام نمایشی مثل 'GPT-4.1 (IranGPU)'
            $table->string('title')->nullable();      // توضیح کوتاه
            $table->string('provider');               // مثلاً 'irangpu', 'gapgpt', 'deepseek'
            $table->string('endpoint');               // آدرس کامل API (یا base_url)
            $table->string('api_key')->nullable();    // کلید اختصاصی این مدل
            $table->string('model_mapping');          // نام مدل در API خارجی مثل 'openai/gpt-4.1-2025-04-14'
            $table->boolean('supports_files')->default(false); // آیا از فایل پشتیبانی می‌کند؟
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_models');
    }
};