<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $fillable = [
        'key', 'name', 'title', 'provider', 'endpoint', 'api_key',
        'model_mapping', 'supports_files', 'is_active', 'sort_order',
        'use_proxy', 'proxy_url', 'timeout_seconds', 'proxy_mode', 'proxy_server_id',
    ];

    protected $casts = [
        'supports_files' => 'boolean',
        'is_active' => 'boolean',
        'use_proxy' => 'boolean',
    ];

    public function proxyServer(): BelongsTo
    {
        return $this->belongsTo(AiProxyServer::class, 'proxy_server_id');
    }
}
