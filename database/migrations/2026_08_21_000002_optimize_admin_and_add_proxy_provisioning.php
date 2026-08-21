<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['role', 'user_id'], 'idx_messages_role_user');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('expires_at', 'idx_subscriptions_expires_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'idx_users_created_at');
        });

        Schema::table('ai_proxy_servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('notes');
            $table->string('ssh_username')->nullable()->after('ssh_port');
            $table->text('ssh_password')->nullable()->after('ssh_username');
            $table->string('ssh_host_fingerprint')->nullable()->after('ssh_password');
            $table->string('allowed_source_ip', 64)->default('77.237.82.11')->after('ssh_host_fingerprint');
            $table->string('provision_status', 30)->default('not_started')->after('allowed_source_ip');
            $table->text('provision_message')->nullable()->after('provision_status');
            $table->timestamp('last_provision_attempt_at')->nullable()->after('provision_message');
            $table->timestamp('provisioned_at')->nullable()->after('last_provision_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_proxy_servers', function (Blueprint $table) {
            $table->dropColumn([
                'ssh_port', 'ssh_username', 'ssh_password', 'ssh_host_fingerprint',
                'allowed_source_ip', 'provision_status', 'provision_message',
                'last_provision_attempt_at', 'provisioned_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_created_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_expires_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_role_user');
        });
    }
};
