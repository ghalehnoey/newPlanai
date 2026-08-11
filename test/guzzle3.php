<?php
// نیازی به Guzzle در این کد نیست چون از cURL خام استفاده کرده‌اید، اما اگر جایی دیگر نیاز دارید بماند
require __DIR__ . '/../vendor/autoload.php';

// API Key شما (حتما این کلید را تغییر دهید!)
$apiKey = getenv('OPENAI_API_KEY') ?: '';

$url = "https://api.openai.com/v1/chat/completions";

// سوالی که می‌خواهید از هوش مصنوعی بپرسید
$question = "سلام، پایتخت ایران کجاست؟ یک جواب کوتاه بده.";

$data = [
    "model" => "gpt-4-turbo", // مدل gpt-5.4 وجود ندارد، به مدل معتبر تغییر یافت
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

// تنظیم اینترفیس شبکه به tun0 (یا می‌توانید آی‌پی آن 192.168.30.205 را وارد کنید)
curl_setopt($ch, CURLOPT_INTERFACE, "tun0");

// غیرفعال کردن بررسی SSL (در محیط پروداکشن توصیه نمی‌شود)
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
