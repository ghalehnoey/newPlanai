<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->boolean('use_proxy')->default(false)->after('is_active');
            $table->string('proxy_url')->nullable()->after('use_proxy');
            $table->integer('timeout_seconds')->default(30)->after('proxy_url');
        });
    }

    public function down()
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn(['use_proxy', 'proxy_url', 'timeout_seconds']);
        });
    }
};