<?php

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;
use Illuminate\Support\Facades\Log;

class AiApiService
{
    private const MODEL_MAP = [
        'qwen'          => 'gapgpt-qwen-3.5',
        'gemini'        => 'openai/gpt-4.1-2025-04-14',
        'claude'        => 'claude-sonnet-4-6',
        'deepseek'      => 'deepseek-chat',
        'gemini_flash'  => 'gemini-2.5-flash',
    ];

    public function __construct(private FileService $fileService) {}

    private function getProviderConfig(string $model): array
    {
        if ($model === 'gemini') {
            return [
                'base_url' => config('services.irangpu.base_url') . '/v1/chat/completions',
                'api_key'  => (string) config('services.irangpu.key'),
            ];
        }

        if ($model === 'deepseek') {
            return [
                'base_url' => config('services.deepseek.base_url', 'https://api.deepseek.com') . '/v1/chat/completions',
                'api_key'  => (string) config('services.deepseek.key'),
            ];
        }

        return [
            'base_url' => 'https://api.gapgpt.app/v1/chat/completions',
            'api_key'  => (string) config('services.gapgpt.key'),
        ];
    }

    private function resolveModel(string $model): string
    {
        return self::MODEL_MAP[$model] ?? self::MODEL_MAP['qwen'];
    }

    private function headers(string $apiKey): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ];
    }

    private function client(): Client
    {
        return new Client(['timeout' => 150]);
    }

    /** ==================== BUILD WITH FILES ==================== */
    private function buildMessagesWithFiles(
        string $systemPrompt,
        array $history,
        string $message,
        array $uploadedFiles = []
    ): array {

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $m) {
            if (empty($m['content'] ?? '')) continue;
            $messages[] = [
                'role'    => $m['role'],
                'content' => $m['content']
            ];
        }

        $userContent = [];

        if (trim($message) !== '') {
            $userContent[] = ["type" => "text", "text" => trim($message)];
        }

        foreach ($uploadedFiles as $fileModel) {
            $fullPath = storage_path('app/public/' . $fileModel->path);

            if (!file_exists($fullPath)) {
                Log::error("File not found", ['path' => $fileModel->path]);
                continue;
            }

            $mime = strtolower($fileModel->mime_type ?? '');
            if (empty($mime)) {
                $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
            }

            if (str_starts_with($mime, 'image/')) {
                $imageData = file_get_contents($fullPath);
                $base64 = base64_encode($imageData);

                $userContent[] = [
                    "type" => "image_url",
                    "image_url" => [
                        "url" => "data:{$mime};base64,{$base64}"
                    ]
                ];

                Log::info("🖼️ IMAGE ADDED TO REQUEST", [
                    'model'         => 'gemini',
                    'file'          => $fileModel->original_name,
                    'mime'          => $mime,
                    'base64_size_kb'=> round(strlen($base64) / 1024, 1)
                ]);
            } else {
                try {
                    $extractedText = $this->fileService->extractText($fullPath, $mime);
                    $fileName = $fileModel->original_name ?? 'فایل';
                    $userContent[] = [
                        "type" => "text",
                        "text" => "\n\n--- محتوای فایل {$fileName} ---\n{$extractedText}\n------------------\n"
                    ];
                } catch (\Exception $e) {
                    Log::error("Text extraction failed", ['error' => $e->getMessage()]);
                }
            }
        }

        $messages[] = ["role" => "user", "content" => $userContent];

        return $messages;
    }

    private function buildMessages(
        string $systemPrompt,
        array $history,
        string $message
    ): array {

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $m) {
            if (empty($m['content'] ?? '')) continue;
            $messages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $messages[] = ['role' => 'user', 'content' => trim($message)];

        return $messages;
    }

    public function call(
        string $message,
        string $model,
        string $systemPrompt
    ): string {
        return $this->callWithHistory($message, $model, $systemPrompt, []);
    }

    public function callWithHistory(
        string $message,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $uploadedFiles = []
    ): string {

        $provider = $this->getProviderConfig($model);

        if (!$provider['api_key']) {
            $errorMsg = "❌ خطا: کلید API برای مدل {$model} تنظیم نشده است.";
            Log::error($errorMsg);
            return $errorMsg;
        }

        $history = $this->normalizeHistory($previousChatMessages);

        if (!empty($uploadedFiles) && in_array($model, ['qwen', 'gemini', 'gemini_flash', 'claude'])) {
            $messages = $this->buildMessagesWithFiles($systemPrompt, $history, $message, $uploadedFiles);
        } else {
            $messages = $this->buildMessages($systemPrompt, $history, $message);
        }

        $body = [
            'model' => $this->resolveModel($model),
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ];

        try {
            $response = $this->client()->post($provider['base_url'], [
                'headers' => $this->headers($provider['api_key']),
                'json' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';

        } catch (\Exception $e) {
            Log::error('AI API Exception', ['model' => $model, 'message' => $e->getMessage()]);
            return "❌ خطا: " . $e->getMessage();
        }
    }

    public function prepareStreamRequest(
        string $question,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $uploadedFiles = []
    ): array {

        $provider = $this->getProviderConfig($model);

        if (!$provider['api_key']) {
            throw new RuntimeException("کلید API برای مدل {$model} تنظیم نشده است.");
        }

        $history = $this->normalizeHistory($previousChatMessages);

        if (!empty($uploadedFiles) && in_array($model, ['qwen', 'gemini', 'gemini_flash', 'claude'])) {
            $messages = $this->buildMessagesWithFiles($systemPrompt, $history, $question, $uploadedFiles);
        } else {
            $messages = $this->buildMessages($systemPrompt, $history, $question);
        }

        $body = [
            'model'       => $this->resolveModel($model),
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 4000,
            'stream'      => true,
        ];

        return [$provider['base_url'], $this->headers($provider['api_key']), $body];
    }

    public function stream(
        string $question,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $uploadedFiles = [],
        callable $onChunk = null
    ): void {

        [$url, $headers, $body] = $this->prepareStreamRequest(
            $question,
            $model,
            $systemPrompt,
            $previousChatMessages,
            $uploadedFiles
        );

        $client = new Client(['timeout' => 0]);

        $response = $client->post($url, [
            'headers' => $headers,
            'json'    => $body,
            'stream'  => true,
        ]);

        $bodyStream = $response->getBody();
        $buffer = '';

        while (!$bodyStream->eof()) {
            $buffer .= $bodyStream->read(8192);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || $line === 'data: [DONE]') continue;

                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);

                    if (isset($data['choices'][0]['delta']['content'])) {
                        $onChunk((string) $data['choices'][0]['delta']['content']);
                    }
                }
            }
        }
    }

    private function normalizeHistory(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if (!is_array($m)) continue;

            $role = $m['role'] ?? null;
            $content = trim((string) ($m['content'] ?? ''));

            if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $out[] = ['role' => $role, 'content' => $content];
        }
        return $out;
    }
}