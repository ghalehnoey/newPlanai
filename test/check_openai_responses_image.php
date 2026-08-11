<?php

use App\Models\AiModel;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$image = __DIR__ . '/download.jpg';
$mime = mime_content_type($image) ?: 'image/jpeg';
$base64 = base64_encode(file_get_contents($image));

$model = AiModel::query()
    ->where('is_active', true)
    ->where('key', 'vision')
    ->first();

if (!$model) {
    fwrite(STDERR, "Model 'vision' not found.\n");
    exit(1);
}

$key = trim($model->api_key ?: config('services.openai.key') ?: env('OPENAI_API_KEY'));
$proxy = trim($model->proxy_url ?: config('services.openai.proxy') ?: env('OPENAI_PROXY') ?: 'socks5h://127.0.0.1:1080');
$modelName = trim($model->model_mapping ?: $model->key);

$body = [
    'model' => $modelName,
    'input' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'input_text', 'text' => 'Describe this image in one short sentence.'],
                ['type' => 'input_image', 'image_url' => "data:{$mime};base64,{$base64}"],
            ],
        ],
    ],
    'max_output_tokens' => 80,
];

$payload = json_encode($body, JSON_UNESCAPED_UNICODE);
$ch = curl_init('https://api.openai.com/v1/responses');
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
    CURLOPT_TIMEOUT => 90,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "RESPONSES_HTTP={$httpCode}\n";
echo "RESPONSES_ERRNO={$errno}\n";
echo "RESPONSES_RESPONSE_LEN=" . (is_string($response) ? strlen($response) : 0) . "\n";

if ($errno) {
    echo "RESPONSES_ERROR={$error}\n";
}

if (is_string($response) && $response !== '') {
    $data = json_decode($response, true);
    echo "OUTPUT_TEXT=" . ($data['output_text'] ?? mb_substr($response, 0, 500)) . "\n";
}

exit($httpCode >= 200 && $httpCode < 300 ? 0 : 1);
