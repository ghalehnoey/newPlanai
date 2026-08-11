<?php

$env = [];

foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim(trim($value), "\"'");
}
$key = trim($env['OPENAI_API_KEY'] ?? '');

if ($key === '') {
    fwrite(STDERR, "OPENAI_API_KEY is empty.\n");
    exit(1);
}

$ch = curl_init('https://api.openai.com/v1/models');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_PROXY => $env['OPENAI_PROXY'] ?? 'socks5h://127.0.0.1:1080',
    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "OPENAI_MODELS_HTTP={$httpCode}\n";
echo "OPENAI_MODELS_ERRNO={$errno}\n";
echo "OPENAI_MODELS_RESPONSE_LEN=" . (is_string($response) ? strlen($response) : 0) . "\n";

if ($errno) {
    echo "OPENAI_MODELS_ERROR={$error}\n";
}

exit($httpCode >= 200 && $httpCode < 300 ? 0 : 1);
