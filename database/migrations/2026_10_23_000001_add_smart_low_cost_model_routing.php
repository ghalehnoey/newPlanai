<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->boolean('smart_routed')->default(false)->after('route')->index();
            $table->string('routing_reason', 40)->nullable()->after('smart_routed');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'ai_smart_routing_enabled'],
            ['value' => '0', 'updated_at' => now()],
        );
        DB::table('settings')->updateOrInsert(
            ['key' => 'ai_smart_routing_max_characters'],
            ['value' => '1500', 'updated_at' => now()],
        );

        $source = DB::table('ai_models')->where('key', 'chatgpt-5.5')->first();
        if ($source) {
            DB::table('ai_models')->updateOrInsert(
                ['key' => 'gpt-5.4-mini-router'],
                [
                    'name' => 'GPT-5.4 Mini (مسیریاب داخلی)',
                    'title' => 'مدل کم‌هزینه داخلی برای سؤال‌های ساده، خلاصه و FAQ',
                    'provider' => $source->provider,
                    'endpoint' => $source->endpoint,
                    'api_key' => $source->api_key,
                    'model_mapping' => 'gpt-5.4-mini',
                    'supports_files' => false,
                    'is_active' => true,
                    'sort_order' => 900,
                    'use_proxy' => $source->use_proxy,
                    'proxy_url' => $source->proxy_url,
                    'timeout_seconds' => $source->timeout_seconds,
                    'max_output_tokens' => min(4000, (int) ($source->max_output_tokens ?: 4000)),
                    'proxy_mode' => $source->proxy_mode,
                    'proxy_server_id' => $source->proxy_server_id,
                    'input_price_per_million' => 0.750000,
                    'cached_input_price_per_million' => 0.075000,
                    'output_price_per_million' => 4.500000,
                    'daily_cost_limit_usd' => null,
                    'daily_token_limit' => null,
                    'fallback_model_id' => $source->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('ai_models')->where('key', 'gpt-5.4-mini-router')->delete();
        DB::table('settings')->whereIn('key', [
            'ai_smart_routing_enabled',
            'ai_smart_routing_max_characters',
        ])->delete();

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['smart_routed']);
            $table->dropColumn(['smart_routed', 'routing_reason']);
        });
    }
};
