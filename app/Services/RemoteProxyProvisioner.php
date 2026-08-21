<?php

namespace App\Services;

use App\Models\AiProxyServer;
use Illuminate\Support\Str;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class RemoteProxyProvisioner
{
    public function __construct(private AiProxyRouter $proxyRouter) {}

    /** @return array<string, mixed> */
    public function provision(AiProxyServer $proxy): array
    {
        set_time_limit(300);
        $this->assertPublicHost($proxy->host);

        if (!filled($proxy->ssh_username) || !filled($proxy->ssh_password)) {
            throw new RuntimeException('نام کاربری و رمز SSH سرور مقصد باید ذخیره شده باشد.');
        }

        if (!filled($proxy->username)) {
            $proxy->username = 'ai_proxy';
        }

        if (!filled($proxy->password)) {
            $proxy->password = Str::random(48);
        }

        $proxy->forceFill([
            'provision_status' => 'running',
            'provision_message' => 'در حال اتصال امن به سرور مقصد...',
            'last_provision_attempt_at' => now(),
        ])->save();

        try {
            $ssh = new SSH2((string) $proxy->host, (int) ($proxy->ssh_port ?: 22), 15);
            $ssh->setTimeout(240);
            $hostKey = $ssh->getServerPublicHostKey();

            if (!is_string($hostKey) || $hostKey === '') {
                throw new RuntimeException('کلید میزبان SSH دریافت نشد.');
            }

            $fingerprint = $this->fingerprint($hostKey);

            if (filled($proxy->ssh_host_fingerprint) && !hash_equals((string) $proxy->ssh_host_fingerprint, $fingerprint)) {
                throw new RuntimeException('اثر انگشت کلید SSH سرور تغییر کرده است؛ برای جلوگیری از حملهٔ MITM عملیات متوقف شد.');
            }

            if (!$ssh->login((string) $proxy->ssh_username, (string) $proxy->ssh_password)) {
                throw new RuntimeException('ورود SSH ناموفق بود؛ نام کاربری یا رمز سرور را بررسی کنید.');
            }

            if (!filled($proxy->ssh_host_fingerprint)) {
                $proxy->ssh_host_fingerprint = $fingerprint;
                $proxy->save();
            }

            $isRoot = trim((string) $ssh->exec('id -u')) === '0';

            if (!$isRoot) {
                $ssh->exec('sudo -n true');

                if ($ssh->getExitStatus() !== 0) {
                    throw new RuntimeException('کاربر SSH باید root یا دارای sudo بدون درخواست رمز باشد. رمز SSH داخل فرمان sudo قرار داده نمی‌شود.');
                }
            }

            $this->exec($ssh, 'command -v apt-get >/dev/null 2>&1');
            $os = trim((string) $ssh->exec(". /etc/os-release 2>/dev/null; printf '%s' \"\${PRETTY_NAME:-unknown}\""));
            $interface = trim((string) $ssh->exec("ip -4 route show default | awk 'NR==1 {print \$5}'"));

            if (!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $interface)) {
                throw new RuntimeException('رابط خروجی شبکهٔ سرور مقصد قابل تشخیص نیست.');
            }

            $sourceIp = (string) ($proxy->allowed_source_ip ?: '77.237.82.11');

            if (!filter_var($sourceIp, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('IP مجاز سرور سایت معتبر نیست.');
            }

            $proxyUsername = (string) $proxy->username;

            if (!preg_match('/^[a-z_][a-z0-9_-]{0,30}$/', $proxyUsername)) {
                throw new RuntimeException('نام کاربری پراکسی باید فقط شامل حروف کوچک انگلیسی، عدد، خط تیره یا زیرخط باشد.');
            }

            $proxyPort = (int) $proxy->port;
            $salt = bin2hex(random_bytes(8));
            $passwordHash = crypt((string) $proxy->password, '$6$rounds=100000$' . $salt . '$');

            if (!is_string($passwordHash) || !str_starts_with($passwordHash, '$6$')) {
                throw new RuntimeException('ساخت hash امن رمز پراکسی ناموفق بود.');
            }

            $installCommand = 'env DEBIAN_FRONTEND=noninteractive apt-get update -qq'
                . ' && env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq dante-server';
            $this->execPrivileged($ssh, $installCommand, $isRoot);

            $userCommand = 'id -u ' . escapeshellarg($proxyUsername) . ' >/dev/null 2>&1'
                . ' || useradd -M -s /usr/sbin/nologin ' . escapeshellarg($proxyUsername)
                . '; usermod -p ' . escapeshellarg($passwordHash) . ' ' . escapeshellarg($proxyUsername);
            $this->execPrivileged($ssh, $userCommand, $isRoot);

            $config = $this->danteConfig($interface, $sourceIp, $proxyPort);
            $encodedConfig = base64_encode($config);
            $writeConfig = "printf '%s' " . escapeshellarg($encodedConfig)
                . ' | base64 -d > /etc/danted.conf'
                . ' && chmod 600 /etc/danted.conf';
            $this->execPrivileged($ssh, $writeConfig, $isRoot);
            $this->execPrivileged(
                $ssh,
                'systemctl enable danted >/dev/null 2>&1 && systemctl restart danted && systemctl is-active --quiet danted',
                $isRoot,
            );

            $proxy->forceFill([
                'scheme' => 'socks5h',
                'provision_status' => 'installed',
                'provision_message' => "Dante روی {$os} نصب و سرویس فعال شد؛ در حال تست IP خروجی...",
                'provisioned_at' => now(),
            ])->save();

            $health = $this->proxyRouter->testProxy($proxy->fresh());
            $ready = (bool) ($health['ok'] ?? false);
            $message = $ready
                ? 'پراکسی نصب شد و IP خروجی با موفقیت تأیید شد.'
                : 'نصب انجام شد اما تست بیرونی ناموفق بود: ' . ($health['diagnosis'] ?? 'خطای نامشخص');

            $proxy->forceFill([
                'provision_status' => $ready ? 'ready' : 'installed_unreachable',
                'provision_message' => $message,
            ])->save();

            return [
                'ok' => $ready,
                'status' => $proxy->provision_status,
                'message' => $message,
                'fingerprint' => $fingerprint,
                'health' => $health,
            ];
        } catch (Throwable $exception) {
            $message = mb_substr($this->proxyRouter->sanitizeError($exception->getMessage()), 0, 1500);
            $proxy->forceFill([
                'provision_status' => 'failed',
                'provision_message' => $message,
            ])->save();

            throw new RuntimeException($message, 0, $exception);
        }
    }

    private function exec(SSH2 $ssh, string $command): string
    {
        $output = (string) $ssh->exec($command);
        $exitStatus = $ssh->getExitStatus();

        if ($exitStatus !== false && $exitStatus !== 0) {
            $safeOutput = trim((string) preg_replace('/\s+/', ' ', strip_tags($output)));
            throw new RuntimeException('اجرای عملیات روی سرور مقصد ناموفق بود: ' . mb_substr($safeOutput, 0, 600));
        }

        return $output;
    }

    private function execPrivileged(SSH2 $ssh, string $command, bool $isRoot): string
    {
        $wrapped = ($isRoot ? '' : 'sudo -n ') . 'sh -c ' . escapeshellarg($command);

        return $this->exec($ssh, $wrapped);
    }

    private function danteConfig(string $interface, string $sourceIp, int $port): string
    {
        return <<<CONFIG
logoutput: syslog
internal: 0.0.0.0 port = {$port}
external: {$interface}

clientmethod: none
socksmethod: username

user.privileged: root
user.notprivileged: nobody

client pass {
    from: {$sourceIp}/32 to: 0.0.0.0/0
    log: error connect disconnect
}

socks pass {
    from: {$sourceIp}/32 to: 0.0.0.0/0
    command: connect
    socksmethod: username
    log: error connect disconnect
}
CONFIG;
    }

    private function fingerprint(string $hostKey): string
    {
        $parts = preg_split('/\s+/', trim($hostKey));
        $decoded = isset($parts[1]) ? base64_decode($parts[1], true) : false;

        if (!is_string($decoded)) {
            throw new RuntimeException('فرمت کلید میزبان SSH معتبر نیست.');
        }

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
    }

    private function assertPublicHost(string $host): void
    {
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } elseif (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);

            foreach ($records ?: [] as $record) {
                $addresses[] = $record['ip'] ?? $record['ipv6'] ?? null;
            }
        }

        $addresses = array_values(array_filter($addresses, 'is_string'));

        if ($addresses === []) {
            throw new RuntimeException('آدرس عمومی سرور مقصد قابل resolve نیست.');
        }

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('برای provisioning فقط آدرس عمومی سرور مقصد مجاز است.');
            }
        }
    }
}
