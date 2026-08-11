<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // ابتدا مقادیر NULL را برای ردیف‌هایی که خالی هستند تنظیم می‌کنیم
        DB::statement('ALTER TABLE `ai_models` MODIFY `api_key` TEXT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE `ai_models` MODIFY `api_key` VARCHAR(255) NULL');
    }
};