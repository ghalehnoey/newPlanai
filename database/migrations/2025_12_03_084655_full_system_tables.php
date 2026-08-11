<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        /* ------------------ USERS ------------------ */
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('username');
            }
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('phone');
            }
        });

        /* ------------------ PLANS ------------------ */
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('price');
                $table->integer('questions_limit');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        /* ------------------ SUBSCRIPTIONS ------------------ */
        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
                $table->integer('questions_used')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        /* ------------------ CONVERSATIONS ------------------ */
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('title')->default('چت جدید');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }

        /* ------------------ MESSAGES ------------------ */
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->enum('role', ['user', 'assistant']);
                $table->longText('content');
                $table->string('model')->nullable();
                $table->timestamps();
            });
        }

        /* ------------------ UPLOADED FILES ------------------ */
        if (!Schema::hasTable('uploaded_files')) {
            Schema::create('uploaded_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');

                $table->string('original_name');
                $table->string('stored_name');
                $table->string('path');
                $table->string('mime_type');
                $table->bigInteger('size');
                $table->string('type')->nullable();
                $table->longText('extracted_text')->nullable();

                $table->timestamps();
            });
        }

        /* ------------------ MESSAGE-FILE PIVOT ------------------ */
        if (!Schema::hasTable('message_file')) {
            Schema::create('message_file', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
                $table->foreignId('file_id')->constrained('uploaded_files')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('message_file');
        Schema::dropIfExists('uploaded_files');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
