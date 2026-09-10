<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $fillable = [
        'key', 'name', 'title', 'provider', 'endpoint', 'api_key',
        'model_mapping', 'supports_files', 'is_active', 'sort_order',
        'use_proxy', 'proxy_url', 'timeout_seconds', 'max_output_tokens', 'proxy_mode', 'proxy_server_id',
        'input_price_per_million', 'cached_input_price_per_million',
        'output_price_per_million',
        'daily_cost_limit_usd', 'daily_token_limit', 'fallback_model_id',
    ];

    protected $casts = [
        'supports_files' => 'boolean',
        'is_active' => 'boolean',
        'use_proxy' => 'boolean',
        'input_price_per_million' => 'decimal:6',
        'cached_input_price_per_million' => 'decimal:6',
        'output_price_per_million' => 'decimal:6',
        'daily_cost_limit_usd' => 'decimal:4',
        'daily_token_limit' => 'integer',
        'max_output_tokens' => 'integer',
    ];

    public function proxyServer(): BelongsTo
    {
        return $this->belongsTo(AiProxyServer::class, 'proxy_server_id');
    }

    public function fallbackModel(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_model_id');
    }
}
