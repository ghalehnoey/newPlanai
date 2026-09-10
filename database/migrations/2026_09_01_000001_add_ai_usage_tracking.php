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
            $table->decimal('input_price_per_million', 12, 6)->default(0)->after('timeout_seconds');
            $table->decimal('cached_input_price_per_million', 12, 6)->default(0)->after('input_price_per_million');
            $table->decimal('output_price_per_million', 12, 6)->default(0)->after('cached_input_price_per_million');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('user_message_id')->nullable()->index();
            $table->unsignedBigInteger('assistant_message_id')->nullable()->index();
            $table->string('requested_model', 100);
            $table->string('used_model', 100)->index();
            $table->string('provider', 100)->nullable();
            $table->string('model_mapping', 255)->nullable();
            $table->string('route', 30)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('input_price_per_million', 12, 6)->default(0);
            $table->decimal('cached_input_price_per_million', 12, 6)->default(0);
            $table->decimal('output_price_per_million', 12, 6)->default(0);
            $table->decimal('estimated_cost_usd', 16, 8)->default(0);
            $table->string('usage_source', 20)->default('provider');
            $table->unsignedInteger('elapsed_ms')->default(0);
            $table->timestamps();
            $table->index(['created_at', 'used_model']);
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'ai_history_message_limit'],
            ['value' => '1', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn([
                'input_price_per_million',
                'cached_input_price_per_million',
                'output_price_per_million',
            ]);
        });
    }
};
