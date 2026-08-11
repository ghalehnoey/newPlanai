
<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

$apiKey = getenv('OPENAI_API_KEY') ?: '';


$client = new Client([
    'proxy' => 'socks5h://127.0.0.1:1080',
    'timeout' => 10,
]);

try {
    $response = $client->get('https://api.openai.com/v1/models', [
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
        ],
    ]);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: " . substr($response->getBody(), 0, 200) . "...";
} catch (\Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
