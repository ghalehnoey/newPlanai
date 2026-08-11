<?php

$api_key = getenv('OPENAI_API_KEY') ?: '';
$image_path = "22.png";

if (!file_exists($image_path)) {
    die("Image not found: $image_path\n");
}

$image_data = base64_encode(file_get_contents($image_path));
$mime = mime_content_type($image_path);

$data = [
    "model" => "deepseek-vl2",
    "messages" => [
        [
            "role" => "user",
            "content" => "Extract the text inside this image."
        ]
    ],
    "image" => "data:$mime;base64,$image_data", // The image goes in a separate 'image' field
    "max_tokens" => 500
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.deepseek.com/vision", // Use the VISION endpoint
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
    // Check for success and display the result
    if (isset($result['choices'][0]['message']['content'])) {
        echo "✅ SUCCESS\n";
        echo "Response: " . $result['choices'][0]['message']['content'] . "\n";
    } elseif (isset($result['error'])) {
        echo "❌ API Error: " . json_encode($result['error']) . "\n";
    } else {
        print_r($result);
    }
}

curl_close($ch);