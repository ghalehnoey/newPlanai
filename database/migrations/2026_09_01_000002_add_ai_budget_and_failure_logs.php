<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->decimal('daily_cost_limit_usd', 12, 4)->nullable()->after('output_price_per_million');
            $table->unsignedBigInteger('daily_token_limit')->nullable()->after('daily_cost_limit_usd');
        });

        Schema::create('ai_failure_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->nullable()->index();
            $table->string('model_key', 100)->nullable()->index();
            $table->string('category', 40)->index();
            $table->string('message', 500);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedSmallInteger('curl_errno')->nullable();
            $table->string('route', 30)->nullable();
            $table->string('stage', 40)->nullable();
            $table->timestamps();
            $table->index(['created_at', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_failure_logs');
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn(['daily_cost_limit_usd', 'daily_token_limit']);
        });
    }
};
