<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->unsignedInteger('max_output_tokens')->nullable()->after('timeout_seconds');
        });

        $fallbackId = DB::table('ai_models')->where('key', 'deepseek')->value('id');

        DB::table('ai_models')
            ->where('model_mapping', 'gpt-5.4')
            ->where('endpoint', 'like', '%api.openai.com%')
            ->update([
                'timeout_seconds' => 90,
                'max_output_tokens' => 1800,
                'input_price_per_million' => 2.5,
                'cached_input_price_per_million' => 0.25,
                'output_price_per_million' => 15,
                'daily_cost_limit_usd' => 1.5,
                'fallback_model_id' => $fallbackId,
                'updated_at' => now(),
            ]);

        DB::table('ai_usage_logs')
            ->where('used_model', 'chatgpt-5.5')
            ->where('model_mapping', 'gpt-5.4')
            ->where('estimated_cost_usd', 0)
            ->update([
                'input_price_per_million' => 2.5,
                'cached_input_price_per_million' => 0.25,
                'output_price_per_million' => 15,
                'estimated_cost_usd' => DB::raw('ROUND(((GREATEST(input_tokens - cached_input_tokens, 0) * 2.5) + (cached_input_tokens * 0.25) + (output_tokens * 15)) / 1000000, 8)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('max_output_tokens');
        });
    }
};
