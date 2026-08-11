<?php
// مسیر اتولودر را بر اساس پوشه‌ای که در آن هستید (test) تنظیم کردیم
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\RequestException;

$handler = new CurlHandler();
$stack = HandlerStack::create($handler);

$client = new Client([
    'handler' => $stack,
    'verify' => false,
    'proxy' => 'socks5h://127.0.0.1:1080',
    'timeout' => 30.0,
]);

// گرفتن ورودی از کاربر در ترمینال
echo "💬 پیام خود را بنویسید (برای خروج Ctrl+C را بزنید):\n> ";
$userMessage = trim(fgets(STDIN));

if (empty($userMessage)) {
    die("پیامی وارد نشد.\n");
}

echo "\n⏳ در حال ارسال به OpenAI...\n";

try {
    $response = $client->post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . (getenv('OPENAI_API_KEY') ?: ''), // کلید API خود را بگذارید
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $userMessage] // ارسال پیام شما
            ]
        ]
    ]);

    $data = json_decode($response->getBody()->getContents(), true);
    $botReply = $data['choices'][0]['message']['content'] ?? 'جوابی دریافت نشد.';

    echo "\n🤖 جواب هوش مصنوعی:\n" . $botReply . "\n\n";

} catch (RequestException $e) {
    echo "\n❌ خطا در ارتباط:\n" . $e->getMessage() . "\n";
}
