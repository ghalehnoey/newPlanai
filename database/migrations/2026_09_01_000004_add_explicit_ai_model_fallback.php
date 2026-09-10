<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->foreignId('fallback_model_id')
                ->nullable()
                ->after('daily_token_limit')
                ->constrained('ai_models')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fallback_model_id');
        });
    }
};
