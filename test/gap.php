<?php

$api_key = getenv('OPENAI_API_KEY') ?: '';
$image_path = "22.png";

if (!file_exists($image_path)) {
    die("Image not found");
}

$image_data = base64_encode(file_get_contents($image_path));
$mime = mime_content_type($image_path);

/* مدل‌هایی که می‌خواهیم تست کنیم */
$models = [
    "gpt-5.2-chat-latest",
    "gpt-5.3-chat-latest",
    "gapgpt-qwen-3.6",
    "gapgpt-qwen-3.6-thinking",
    "gemini-2.5-pro",
    "claude-sonnet-4-20250514"
];

foreach ($models as $model) {

    echo "\n========================\n";
    echo "Testing model: $model\n";

    $data = [
        "model" => $model,
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "متن داخل این تصویر را استخراج کن."
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:$mime;base64,$image_data"
                        ]
                    ]
                ]
            ]
        ],
        "max_tokens" => 300
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.gapgpt.app/v1/chat/completions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $api_key"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Curl Error: " . curl_error($ch) . "\n";
        curl_close($ch);
        continue;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        echo "✅ SUCCESS\n";
        echo $result['choices'][0]['message']['content'] . "\n";
    } else {
        echo "❌ FAILED\n";
        print_r($result);
    }
}
