<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_proxy_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scheme', 20)->default('socks5h');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(1080);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->unsignedSmallInteger('connect_timeout')->default(10);
            $table->string('last_status', 20)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->string('last_outbound_ip', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('ai_models', function (Blueprint $table) {
            $table->string('proxy_mode', 20)->default('direct')->after('use_proxy');
            $table->foreignId('proxy_server_id')
                ->nullable()
                ->after('proxy_mode')
                ->constrained('ai_proxy_servers')
                ->nullOnDelete();
        });

        DB::table('ai_models')->where('use_proxy', true)->update(['proxy_mode' => 'proxy']);
        DB::table('ai_models')
            ->where('endpoint', 'like', '%api.x.ai%')
            ->update(['proxy_mode' => 'auto']);
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proxy_server_id');
            $table->dropColumn('proxy_mode');
        });

        Schema::dropIfExists('ai_proxy_servers');
    }
};
