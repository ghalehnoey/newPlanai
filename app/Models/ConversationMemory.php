<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMemory extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'summary_text',
        'memory_items',
        'summarized_through_message_id',
        'source_message_count',
        'estimated_tokens',
        'version',
    ];

    protected $casts = [
        'memory_items' => 'array',
        'summarized_through_message_id' => 'integer',
        'source_message_count' => 'integer',
        'estimated_tokens' => 'integer',
        'version' => 'integer',
    ];
}
