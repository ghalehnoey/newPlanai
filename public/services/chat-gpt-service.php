<?php
// فایل: public/services/chat-gpt-service.php

// تنظیم هدرها برای استریم (Server-Sent Events)
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // خاموش کردن بافرینگ انجین‌اکس

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$model = $input['model'] ?? 'gpt-5.5'; // مدل پیش‌فرض

if (empty($message)) {
    echo "data: " . json_encode(['error' => 'Message is empty']) . "\n\n";
    flush();
    exit;
}

$apiKey = getenv('OPENAI_API_KEY') ?: ''; // کلید API خود را اینجا قرار دهید
$proxy  = 'socks5h://127.0.0.1:1080';
$url    = "https://api.openai.com/v1/chat/completions";

$postData = [
    'model' => $model,
    'messages' => [['role' => 'user', 'content' => $message]],
    'stream' => true, // فعال کردن استریم در OpenAI
    'max_tokens' => 1000
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postData, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ],
    CURLOPT_PROXY => $proxy,
    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
    CURLOPT_TIMEOUT => 120, // زمان بیشتر برای استریم طولانی
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    
    // تابع دریافت استریم تکه‌تکه
    CURLOPT_WRITEFUNCTION => function($curl, $data) {
        $length = strlen($data);
        // چاپ و ارسال فوری هر تکه به لاراول
        echo $data;
        flush();
        return $length;
    }
]);

curl_exec($ch);

if (curl_errno($ch)) {
    echo "data: " . json_encode(['error' => 'cURL Error: ' . curl_error($ch)]) . "\n\n";
    flush();
}

curl_close($ch);
