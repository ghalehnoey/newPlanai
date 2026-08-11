<?php

use App\Models\AiModel;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$model = AiModel::query()
    ->where('is_active', true)
    ->where(function ($query) {
        $query->where('key', 'vision')
            ->orWhere('provider', 'openai')
            ->orWhere('key', 'like', '%chatgpt%');
    })
    ->orderByRaw("CASE WHEN `key` = 'vision' THEN 0 ELSE 1 END")
    ->orderBy('sort_order')
    ->first();

if (!$model) {
    fwrite(STDERR, "No OpenAI-like model found.\n");
    exit(1);
}

$key = trim($model->api_key ?: config('services.openai.key') ?: env('OPENAI_API_KEY'));
$proxy = trim($model->proxy_url ?: config('services.openai.proxy') ?: env('OPENAI_PROXY') ?: 'socks5h://127.0.0.1:1080');
$endpoint = trim($model->endpoint ?: 'https://api.openai.com/v1/chat/completions');
$modelName = trim($model->model_mapping ?: $model->key);

$body = [
    'model' => $modelName,
    'messages' => [
        ['role' => 'user', 'content' => 'Reply with exactly: OK'],
    ],
    'max_completion_tokens' => 20,
    'temperature' => 0,
    'stream' => false,
];

$payload = json_encode($body, JSON_UNESCAPED_UNICODE);
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_PROXY => $proxy,
    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 45,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "MODEL_KEY={$model->key}\n";
echo "MODEL_NAME={$modelName}\n";
echo "ENDPOINT={$endpoint}\n";
echo "PROXY={$proxy}\n";
echo "CHAT_HTTP={$httpCode}\n";
echo "CHAT_ERRNO={$errno}\n";
echo "CHAT_RESPONSE_LEN=" . (is_string($response) ? strlen($response) : 0) . "\n";

if ($errno) {
    echo "CHAT_ERROR={$error}\n";
}

if (is_string($response) && $response !== '') {
    echo "CHAT_RESPONSE_PREVIEW=" . mb_substr($response, 0, 300) . "\n";
}

exit($httpCode >= 200 && $httpCode < 300 ? 0 : 1);
