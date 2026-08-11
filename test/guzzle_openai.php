<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

// API Key شما
$apiKey = getenv('OPENAI_API_KEY') ?: '';

$url = "https://api.openai.com/v1/chat/completions";
$proxy = "socks5h://127.0.0.1:1080";

// سوالی که می‌خواهید از هوش مصنوعی بپرسید
$question = "سلام، پایتخت ایران کجاست؟ یک جواب کوتاه بده.";

$data = [
    "model" => "gpt-5.4",
    "messages" => [
        [
            "role" => "user",
            "content" => $question
        ]
    ],
    "max_completion_tokens" => 100,
    "temperature" => 0.7
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);

// تنظیمات پراکسی
curl_setopt($ch, CURLOPT_PROXY, $proxy);

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "خطای cURL: " . curl_error($ch) . "\n";
} else {
    echo "کد وضعیت HTTP: " . $httpCode . "\n\n";
    
    if ($httpCode == 200) {
        $responseData = json_decode($response, true);
        echo "پاسخ هوش مصنوعی:\n";
        echo $responseData['choices'][0]['message']['content'] . "\n";
    } else {
        echo "خطا از سمت OpenAI:\n";
        echo $response . "\n";
    }
}

curl_close($ch);

?>
