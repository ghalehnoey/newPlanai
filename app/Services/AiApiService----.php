<?php

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;
use Illuminate\Support\Facades\Log;
use App\Models\AiModel;

class AiApiService
{
    public function __construct(private FileService $fileService) {}

    private function getModelConfig(string $modelKey): AiModel
    {
        $model = AiModel::where('key', $modelKey)->where('is_active', true)->first();
        if (!$model) {
            throw new RuntimeException("مدل {$modelKey} فعال یا تعریف نشده است.");
        }
        return $model;
    }

    private function getProviderConfigByModel(AiModel $model): array
    {
        // اگر api_key مستقیماً برای این مدل ذخیره شده باشد
        if ($model->api_key) {
            $apiKey = $model->api_key;
        } else {
            // در غیر این صورت از config کلی استفاده کن
            $apiKey = match($model->provider) {
                'irangpu' => config('services.irangpu.key'),
                'gapgpt'  => config('services.gapgpt.key'),
                'deepseek'=> config('services.deepseek.key'),
                default   => null,
            };
        }

        return [
            'base_url' => $model->endpoint,
            'api_key'  => $apiKey,
        ];
    }

    private function resolveModel(AiModel $model): string
    {
        return $model->model_mapping;
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

    // ================ BUILD MESSAGES (با فایل) ================
    private function buildMessagesWithFiles(
        string $systemPrompt,
        array $history,
        string $message,
        array $uploadedFiles = []
    ): array {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $m) {
            if (empty($m['content'] ?? '')) continue;
            $messages[] = ['role' => $m['role'], 'content' => $m['content']];
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
                    "image_url" => ["url" => "data:{$mime};base64,{$base64}"]
                ];
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

    public function callWithHistory(
        string $message,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $uploadedFiles = []
    ): string {
        $modelConfig = $this->getModelConfig($model);
        $provider = $this->getProviderConfigByModel($modelConfig);
        if (!$provider['api_key']) {
            return "❌ خطا: کلید API برای مدل {$model} تنظیم نشده است.";
        }

        $history = $this->normalizeHistory($previousChatMessages);

        if (!empty($uploadedFiles) && $modelConfig->supports_files) {
            $messages = $this->buildMessagesWithFiles($systemPrompt, $history, $message, $uploadedFiles);
        } else {
            $messages = $this->buildMessages($systemPrompt, $history, $message);
        }

        $body = [
            'model' => $this->resolveModel($modelConfig),
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
        $modelConfig = $this->getModelConfig($model);
        $provider = $this->getProviderConfigByModel($modelConfig);

        if (!$provider['api_key']) {
            throw new RuntimeException("کلید API برای مدل {$model} تنظیم نشده است.");
        }

        $history = $this->normalizeHistory($previousChatMessages);

        if (!empty($uploadedFiles) && $modelConfig->supports_files) {
            $messages = $this->buildMessagesWithFiles($systemPrompt, $history, $question, $uploadedFiles);
        } else {
            $messages = $this->buildMessages($systemPrompt, $history, $question);
        }

        $body = [
            'model' => $this->resolveModel($modelConfig),
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 4000,
            'stream' => true,
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

    // متد کمکی برای چک کردن پشتیبانی از فایل (مفید برای کنترلر)
    public function supportsFiles(string $modelKey): bool
    {
        $model = AiModel::where('key', $modelKey)->first();
        return $model ? $model->supports_files : false;
    }
}