<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProxyServer extends Model
{
    protected $fillable = [
        'name', 'scheme', 'host', 'port', 'username', 'password',
        'is_active', 'priority', 'connect_timeout', 'last_status',
        'last_checked_at', 'last_latency_ms', 'last_outbound_ip',
        'last_error', 'notes',
        'ssh_port', 'ssh_username', 'ssh_password', 'ssh_host_fingerprint',
        'allowed_source_ip', 'provision_status', 'provision_message',
        'last_provision_attempt_at', 'provisioned_at',
    ];

    protected $hidden = ['password', 'ssh_password'];

    protected $casts = [
        'password' => 'encrypted',
        'ssh_password' => 'encrypted',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_provision_attempt_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public function aiModels(): HasMany
    {
        return $this->hasMany(AiModel::class, 'proxy_server_id');
    }

    public function proxyUrl(): string
    {
        $auth = '';

        if (filled($this->username)) {
            $auth = rawurlencode((string) $this->username);

            if (filled($this->password)) {
                $auth .= ':' . rawurlencode((string) $this->password);
            }

            $auth .= '@';
        }

        return sprintf('%s://%s%s:%d', $this->scheme, $auth, $this->host, $this->port);
    }

    public function displayAddress(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
