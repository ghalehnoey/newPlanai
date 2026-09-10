<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount_toman')->nullable()->after('paid');
        });

        DB::statement(
            'UPDATE subscriptions s INNER JOIN plans p ON p.id = s.plan_id '
            . 'SET s.paid_amount_toman = p.price '
            . 'WHERE s.paid = 1 AND s.zibal_track_id IS NOT NULL AND s.paid_amount_toman IS NULL'
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'usd_to_toman_rate'],
            ['value' => '0', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('paid_amount_toman');
        });
    }
};
