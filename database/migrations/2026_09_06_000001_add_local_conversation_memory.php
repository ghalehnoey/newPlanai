<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->text('summary_text')->nullable();
            $table->longText('memory_items')->nullable();
            $table->unsignedBigInteger('summarized_through_message_id')->default(0);
            $table->unsignedInteger('source_message_count')->default(0);
            $table->unsignedInteger('estimated_tokens')->default(0);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();
        });

        $defaults = [
            'ai_memory_enabled' => '1',
            'ai_memory_recent_turns' => '3',
            'ai_memory_previous_conversations' => '3',
            'ai_memory_summary_max_characters' => '1600',
            'ai_memory_cross_chat_max_characters' => '900',
            'ai_memory_context_token_budget' => '3000',
            'ai_memory_recent_message_max_characters' => '3000',
            'ai_memory_source_message_limit' => '160',
            'ai_memory_reuse_files_limit' => '1',
            'ai_memory_file_reuse_turns' => '2',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_memories');
        DB::table('settings')->whereIn('key', [
            'ai_memory_enabled',
            'ai_memory_recent_turns',
            'ai_memory_previous_conversations',
            'ai_memory_summary_max_characters',
            'ai_memory_cross_chat_max_characters',
            'ai_memory_context_token_budget',
            'ai_memory_recent_message_max_characters',
            'ai_memory_source_message_limit',
            'ai_memory_reuse_files_limit',
            'ai_memory_file_reuse_turns',
        ])->delete();
    }
};
