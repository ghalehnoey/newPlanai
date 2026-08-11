<?php

#$api_key = getenv('OPENAI_API_KEY') ?: '';
$api_key = getenv('OPENAI_API_KEY') ?: ''; // کلید API واقعی رو اینجا بذار

$image_path = "22.png"; // یا download.jpg

if (!file_exists($image_path)) {
    die("Image not found: $image_path\n");
}

$image_data = base64_encode(file_get_contents($image_path));
$mime = mime_content_type($image_path);

// مدل‌هایی که از تصویر پشتیبانی می‌کنند
$models = [
    "openai/gpt-4.1-2025-04-14",
    "claude-sonnet-4.6",
    "gemini-2.5-flash"
];

foreach ($models as $model) {
    echo "\n========== TESTING: $model ==========\n";
    
    $data = [
        "model" => $model,
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "متن داخل این تصویر را استخراج کن"
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
        "max_tokens" => 500
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://services.irangpu.com/v1/chat/completions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $api_key"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "Curl Error: " . curl_error($ch) . "\n";
    } else {
        echo "HTTP Code: $http_code\n";
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            echo "✅ SUCCESS\n";
            echo "Response: " . $result['choices'][0]['message']['content'] . "\n";
        } elseif (isset($result['error'])) {
            echo "❌ API Error: " . json_encode($result['error']) . "\n";
        } elseif (isset($result['detail'])) {
            echo "❌ Detail Error: " . $result['detail'] . "\n";
        } else {
            echo "❌ Unknown response:\n";
            print_r($result);
        }
    }
    
    curl_close($ch);
    echo "\n";
}