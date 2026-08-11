<?php
$api_key = getenv('OPENAI_API_KEY') ?: '';  // کلید واقعی را بعداً حذف کن


$api_key = getenv('OPENAI_API_KEY') ?: '';

$file_path = "22.png"; // فایل اظهارنامه

if (!file_exists($file_path)) {
    die("فایل پیدا نشد");
}

$file_data = base64_encode(file_get_contents($file_path));
$mime = mime_content_type($file_path);

$question = "این تصویر یک اظهارنامه حقوقی است. متن آن را بررسی کن و یک پاسخ مناسب و رسمی برای این اظهارنامه بنویس.";

$data = [
    "model" => "openai/gpt-4.1-2025-04-14",
    "messages" => [
        [
            "role" => "user",
            "content" => [
                [
                    "type" => "text",
                    "text" => $question
                ],
                [
                    "type" => "image_url",
                    "image_url" => [
                        "url" => "data:$mime;base64,$file_data"
                    ]
                ]
            ]
        ]
    ]
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://services.irangpu.com/v1/chat/completions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ]
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch);
} else {
    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        echo "\nپاسخ پیشنهادی برای اظهارنامه:\n\n";
        echo $result['choices'][0]['message']['content'];
    } else {
        print_r($result);
    }
}

curl_close($ch);
