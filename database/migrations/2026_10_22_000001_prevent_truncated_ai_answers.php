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
            $table->string('finish_reason', 40)->nullable()->after('route');
            $table->unsignedTinyInteger('continuation_count')->default(0)->after('finish_reason');
        });

        DB::table('ai_models')
            ->where('model_mapping', 'gpt-5.4')
            ->where(function ($query) {
                $query->whereNull('max_output_tokens')
                    ->orWhere('max_output_tokens', '<', 4000);
            })
            ->update([
                'max_output_tokens' => 4000,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('ai_models')
            ->where('model_mapping', 'gpt-5.4')
            ->where('max_output_tokens', 4000)
            ->update([
                'max_output_tokens' => 1800,
                'updated_at' => now(),
            ]);

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['finish_reason', 'continuation_count']);
        });
    }
};
