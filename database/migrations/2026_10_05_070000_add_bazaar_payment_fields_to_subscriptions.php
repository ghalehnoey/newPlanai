<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addedToken = false;

        Schema::table('subscriptions', function (Blueprint $table) use (&$addedToken): void {
            if (!Schema::hasColumn('subscriptions', 'gateway')) {
                $table->string('gateway', 32)->nullable()->after('paid');
            }
            if (!Schema::hasColumn('subscriptions', 'bazaar_purchase_token')) {
                $table->string('bazaar_purchase_token', 512)->nullable()->after('gateway');
                $addedToken = true;
            }
            if (!Schema::hasColumn('subscriptions', 'bazaar_order_id')) {
                $table->string('bazaar_order_id')->nullable()->after('bazaar_purchase_token');
            }
            if (!Schema::hasColumn('subscriptions', 'bazaar_product_id')) {
                $table->string('bazaar_product_id', 80)->nullable()->after('bazaar_order_id');
            }
            if (!Schema::hasColumn('subscriptions', 'bazaar_payload')) {
                $table->text('bazaar_payload')->nullable()->after('bazaar_product_id');
            }
            if (!Schema::hasColumn('subscriptions', 'bazaar_receipt')) {
                $table->longText('bazaar_receipt')->nullable()->after('bazaar_payload');
            }
        });

        if ($addedToken) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->unique('bazaar_purchase_token', 'subscriptions_bazaar_purchase_token_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'bazaar_purchase_token')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->dropUnique('subscriptions_bazaar_purchase_token_unique');
            });
        }

        $columns = ['gateway', 'bazaar_purchase_token', 'bazaar_order_id', 'bazaar_product_id', 'bazaar_payload', 'bazaar_receipt'];
        foreach ($columns as $column) {
            if (Schema::hasColumn('subscriptions', $column)) {
                Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
