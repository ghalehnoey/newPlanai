<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'free_access_started_at')) {
                $table->timestamp('free_access_started_at')->nullable()->after('free_question_used_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'free_access_started_at')) {
                $table->dropColumn('free_access_started_at');
            }
        });
    }
};
