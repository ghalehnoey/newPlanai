<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscriptions', 'zibal_track_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('zibal_track_id')->nullable()->after('expires_at');
            });
        }

        if (!Schema::hasColumn('subscriptions', 'paid')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->boolean('paid')->default(false)->after('zibal_track_id');
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique('zibal_track_id', 'subscriptions_zibal_track_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_zibal_track_id_unique');
        });
    }
};
