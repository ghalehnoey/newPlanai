<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'request_id', 'user_id', 'conversation_id', 'user_message_id', 'assistant_message_id',
        'requested_model', 'used_model', 'provider', 'model_mapping', 'route',
        'input_tokens', 'cached_input_tokens', 'output_tokens', 'reasoning_tokens', 'total_tokens',
        'input_price_per_million', 'cached_input_price_per_million', 'output_price_per_million',
        'estimated_cost_usd', 'usage_source', 'elapsed_ms',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'reasoning_tokens' => 'integer',
        'total_tokens' => 'integer',
        'input_price_per_million' => 'decimal:6',
        'cached_input_price_per_million' => 'decimal:6',
        'output_price_per_million' => 'decimal:6',
        'estimated_cost_usd' => 'decimal:8',
        'elapsed_ms' => 'integer',
    ];
}
