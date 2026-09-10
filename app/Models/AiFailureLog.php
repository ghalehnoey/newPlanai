<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFailureLog extends Model
{
    protected $fillable = [
        'request_id', 'model_key', 'category', 'message', 'http_status',
        'curl_errno', 'route', 'stage',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'curl_errno' => 'integer',
    ];
}
