<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $fillable = [
        'key', 'name', 'title', 'provider', 'endpoint', 'api_key',
        'model_mapping', 'supports_files', 'is_active', 'sort_order',
        'use_proxy', 'proxy_url', 'timeout_seconds' // اضافه شده
    ];

    protected $casts = [
        'supports_files' => 'boolean',
        'is_active' => 'boolean',
        'use_proxy' => 'boolean', // اضافه شده
    ];
}