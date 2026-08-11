<?php

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\AiModel;

class AiApiService
{
    public function __construct(private FileService $fileService) {}

    // ============ کانفیگ ============
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
        $apiKey = $model->api_key ?: match (strtolower((string) $model->provider)) {
            'openai'  => config('services.openai.key'),
            'irangpu' => config('services.irangpu.key'),
            'gapgpt'  => config('services.gapgpt.key'),
            'deepseek'=> config('services.deepseek.key'),
            default   => null,
        };

        return [
            'base_url' => $model->endpoint,
            'api_key'  => $apiKey,
        ];
    }

    private function resolveModel(AiModel $model): string
    {
        return $model->model_mapping ?: $model->key;
    }

    private function headers(string $apiKey): array
    {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . trim($apiKey),
        ];
    }

    private function client(int $timeout = 150): Client
    {
        return new Client(['timeout' => $timeout]);
    }

    private function requiresProxy(AiModel $model): bool
    {
        return (bool) $model->use_proxy;
    }


    private function resizeImageForProxy(string $fullPath, string $mime): array
    {
        $raw = file_get_contents($fullPath);
        if (strlen($raw) <= 80000 || !function_exists('imagecreatefromstring')) {
            return [$raw, $mime];
        }
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return [$raw, $mime];
        }
        $origW = imagesx($src);
        $origH = imagesy($src);
        $maxDim = 768;
        if ($origW <= $maxDim && $origH <= $maxDim) {
            imagedestroy($src);
            return [$raw, $mime];
        }
        if ($origW >= $origH) {
            $newW = $maxDim;
            $newH = (int) round($origH * $maxDim / $origW);
        } else {
            $newH = $maxDim;
            $newW = (int) round($origW * $maxDim / $origH);
        }
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);
        ob_start();
        imagejpeg($dst, null, 75);
        imagedestroy($dst);
        $jpeg = ob_get_clean();
        return [$jpeg, 'image/jpeg'];
    }

    private function buildOpenAiSimpleContent(string $question, array $uploadedFiles): string|array
    {
        if (empty($uploadedFiles)) {
            return $question;
        }

        $content = [];

        if ($question !== '') {
            $content[] = [
                'type' => 'text',
                'text' => $question,
            ];
        }

        foreach ($uploadedFiles as $fileModel) {
            $fullPath = storage_path('app/public/' . $fileModel->path);

            if (!file_exists($fullPath)) {
                Log::error('OpenAI file not found', ['path' => $fileModel->path]);
                continue;
            }

            $mime = strtolower($fileModel->mime_type ?? '');

            if ($mime === '') {
                $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
            }

            if (str_starts_with($mime, 'image/')) {
                [$imageData, $imageMime] = $this->resizeImageForProxy($fullPath, $mime);
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$imageMime};base64," . base64_encode($imageData),
                    ],
                ];

                continue;
            }

            try {
                $extractedText = $this->fileService->extractText($fullPath, $mime);
                $fileName = $fileModel->original_name ?? 'file';

                if (trim((string) $extractedText) !== '') {
                    $content[] = [
                        'type' => 'text',
                        'text' => "\n\n--- File content {$fileName} ---\n{$extractedText}\n------------------\n",
                    ];
                }
            } catch (Throwable $e) {
                Log::error('OpenAI text extraction failed', [
                    'path' => $fileModel->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $content;
    }

    /**
     * مسیر ساده OpenAI:
     * فقط متن کاربر را می‌فرستد.
     * بدون history، بدون system prompt، بدون فایل، بدون context اضافه.
     */
    public function askOpenAiSimple(
        string $question,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $modelName = null,
        ?string $proxyOverride = null,
        array $uploadedFiles = []
    ): string {
        $question = trim($question);

        $apiKey = trim($apiKey ?: config('services.openai.key') ?: env('OPENAI_API_KEY'));

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY تنظیم نشده است.');
        }

        $proxyUrl = trim((string)($proxyOverride ?: config('services.openai.proxy') ?: env('OPENAI_PROXY') ?: ''));

        $url = trim($baseUrl ?: config('services.openai.chat_url') ?: 'https://api.openai.com/v1/chat/completions');

        if ($url === '') {
            $url = 'https://api.openai.com/v1/chat/completions';
        }

        $modelName = trim($modelName ?: config('services.openai.model') ?: 'gpt-5.4');
        $userContent = $this->buildOpenAiSimpleContent($question, $uploadedFiles);

        if ($question === '' && (!is_array($userContent) || empty($userContent))) {
            throw new RuntimeException('متن سوال یا فایل معتبر خالی است.');
        }

        $body = [
            'model' => $modelName,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'max_completion_tokens' => 600,
            'temperature' => 0.7,
            'stream' => false,
        ];

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        Log::info('OPENAI_SIMPLE_REQUEST_START', [
            'url' => $url,
            'model' => $modelName,
            'proxy' => $proxyUrl,
            'question_len' => mb_strlen($question),
            'payload_bytes' => strlen($payload),
            'message_count' => 1,
            'uploaded_files' => count($uploadedFiles),
            'content_parts' => is_array($userContent) ? count($userContent) : 1,
            'image_parts' => is_array($userContent)
                ? count(array_filter($userContent, fn($part) => ($part['type'] ?? null) === 'image_url'))
                : 0,
            'max_completion_tokens' => $body['max_completion_tokens'],
        ]);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Content-Length: ' . strlen($payload),
            ],
            ...($proxyUrl !== '' ? [CURLOPT_PROXY => $proxyUrl, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME] : []),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $response = curl_exec($ch);

        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);

        curl_close($ch);

        Log::info('OPENAI_SIMPLE_REQUEST_DONE', [
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'response_type' => gettype($response),
            'response_len' => is_string($response) ? strlen($response) : 0,
            'total_time' => $totalTime,
            'primary_ip' => $primaryIp,
            'response_preview' => is_string($response) ? mb_substr($response, 0, 700) : null,
        ]);

        if ($curlErrno) {
            throw new RuntimeException("OpenAI simple cURL error ({$curlErrno}): {$curlError}");
        }

        if ($response === false || $response === '') {
            throw new RuntimeException("پاسخ خالی از OpenAI دریافت شد. HTTP={$httpCode}");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new RuntimeException('پاسخ JSON معتبر نیست: ' . mb_substr((string) $response, 0, 500));
        }

        if (isset($data['error'])) {
            $message = is_array($data['error'])
                ? ($data['error']['message'] ?? json_encode($data['error'], JSON_UNESCAPED_UNICODE))
                : (string) $data['error'];

            throw new RuntimeException("OpenAI API error ({$httpCode}): {$message}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("OpenAI HTTP error {$httpCode}: " . mb_substr((string) $response, 0, 500));
        }

        $answer = $data['choices'][0]['message']['content'] ?? '';

        if (trim((string) $answer) === '') {
            throw new RuntimeException('متن پاسخ OpenAI خالی است: ' . mb_substr((string) $response, 0, 500));
        }

        return trim((string) $answer);
    }

    // ============ زنجیرهٔ مدل‌ها ============
    private function getModelChain(string $primaryModelKey, bool $needsFiles = false): array
    {
        $chain = [];
        $seen = [];

        try {
            $primary = $this->getModelConfig($primaryModelKey);
            $chain[] = $primary;
            $seen[] = $primary->key;
        } catch (RuntimeException $e) {
            Log::warning('Primary model unavailable', [
                'model' => $primaryModelKey,
                'error' => $e->getMessage(),
            ]);
        }

        $query = AiModel::where('is_active', true)
            ->where('key', '!=', $primaryModelKey);

        if ($needsFiles) {
            $query->where('supports_files', true);
        }

        if (Schema::hasColumn('ai_models', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        } else {
            $query->orderBy('id', 'asc');
        }

        foreach ($query->get() as $fb) {
            if (!in_array($fb->key, $seen, true)) {
                $chain[] = $fb;
                $seen[] = $fb->key;
            }
        }

        if (empty($chain)) {
            throw new RuntimeException('هیچ مدل فعالی برای پاسخ‌گویی موجود نیست.');
        }

        return $chain;
    }

    // ============ ساخت پیام‌ها برای مدل‌های غیر OpenAI ============
    private function buildMessagesWithFiles(
        string $systemPrompt,
        array $history,
        string $message,
        array $uploadedFiles = []
    ): array {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $m) {
            if (empty($m['content'] ?? '')) {
                continue;
            }

            $messages[] = [
                'role' => $m['role'],
                'content' => $m['content'],
            ];
        }

        $userContent = [];

        if (trim($message) !== '') {
            $userContent[] = [
                'type' => 'text',
                'text' => trim($message),
            ];
        }

        foreach ($uploadedFiles as $fileModel) {
            $fullPath = storage_path('app/public/' . $fileModel->path);

            if (!file_exists($fullPath)) {
                Log::error('File not found', ['path' => $fileModel->path]);
                continue;
            }

            $mime = strtolower($fileModel->mime_type ?? '');

            if (empty($mime)) {
                $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
            }

            if (str_starts_with($mime, 'image/')) {
                $base64 = base64_encode(file_get_contents($fullPath));

                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$mime};base64,{$base64}",
                    ],
                ];
            } else {
                try {
                    $extractedText = $this->fileService->extractText($fullPath, $mime);
                    $fileName = $fileModel->original_name ?? 'فایل';

                    $userContent[] = [
                        'type' => 'text',
                        'text' => "\n\n--- محتوای فایل {$fileName} ---\n{$extractedText}\n------------------\n",
                    ];
                } catch (Throwable $e) {
                    Log::error('Text extraction failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userContent,
        ];

        return $messages;
    }

    private function buildMessages(string $systemPrompt, array $history, string $message): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $m) {
            if (empty($m['content'] ?? '')) {
                continue;
            }

            $messages[] = [
                'role' => $m['role'],
                'content' => $m['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => trim($message),
        ];

        return $messages;
    }

    // ============ تماس غیر استریم با Fallback ============
    public function callWithHistory(
        string $message,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $uploadedFiles = []
    ): string {
        $history = $this->normalizeHistory($previousChatMessages);
        $needsFiles = !empty($uploadedFiles);
        $chain = $this->getModelChain($model, $needsFiles);
        $errors = [];

        foreach ($chain as $modelConfig) {
            try {
                $provider = $this->getProviderConfigByModel($modelConfig);

                if (!$provider['api_key']) {
                    throw new RuntimeException("کلید API برای مدل {$modelConfig->key} تنظیم نشده است.");
                }

                // برای OpenAI فقط همان سوال کاربر ارسال می‌شود
                if ($this->requiresProxy($modelConfig)) {
                    Log::info('OPENAI_SIMPLE_SYNC_START', [
                        'requested_model' => $model,
                        'model_key' => $modelConfig->key,
                        'resolved_model' => $this->resolveModel($modelConfig),
                        'question_len' => mb_strlen($message),
                    ]);

                    $content = $this->askOpenAiSimple(
                        $message,
                        $provider['api_key'],
                        $provider['base_url'],
                        $this->resolveModel($modelConfig),
                        $modelConfig->proxy_url ?: null,
                        $uploadedFiles
                    );

                    Log::info('OPENAI_SIMPLE_SYNC_SUCCESS', [
                        'model' => $modelConfig->key,
                        'content_len' => strlen((string) $content),
                    ]);

                    return $content;
                }

                // مدل‌های غیر OpenAI با منطق قبلی
                $messages = ($needsFiles && $modelConfig->supports_files)
                    ? $this->buildMessagesWithFiles($systemPrompt, $history, $message, $uploadedFiles)
                    : $this->buildMessages($systemPrompt, $history, $message);

                $body = [
                    'model' => $this->resolveModel($modelConfig),
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 4000,
                ];

                $response = $this->client()->post($provider['base_url'], [
                    'headers' => $this->headers($provider['api_key']),
                    'json' => $body,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $content = $data['choices'][0]['message']['content'] ?? '';

                if (trim((string) $content) === '') {
                    throw new RuntimeException("پاسخ خالی از مدل {$modelConfig->key}");
                }

                if ($modelConfig->key !== $model) {
                    Log::info('Fallback model used sync', [
                        'requested' => $model,
                        'used' => $modelConfig->key,
                        'errors' => $errors,
                    ]);
                }

                return $content;
            } catch (Throwable $e) {
                $errors[$modelConfig->key] = $e->getMessage();

                Log::warning('Sync attempt failed', [
                    'model' => $modelConfig->key,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        Log::error('All AI models failed sync', ['errors' => $errors]);

        return 'متأسفانه در حال حاضر امکان پاسخ‌گویی وجود ندارد. لطفاً چند لحظه بعد دوباره تلاش کنید.';
    }

    // ============ استریم با Fallback ============
    public function stream(
        string $question,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $uploadedFiles = [],
        callable $onChunk = null
    ): void {
        $history = $this->normalizeHistory($previousChatMessages);
        $needsFiles = !empty($uploadedFiles);
        $chain = $this->getModelChain($model, $needsFiles);
        $errors = [];
        $hasStarted = false;

        foreach ($chain as $modelConfig) {
            if ($hasStarted) {
                break;
            }

            try {
                $provider = $this->getProviderConfigByModel($modelConfig);

                if (!$provider['api_key']) {
                    throw new RuntimeException("کلید API برای مدل {$modelConfig->key} موجود نیست.");
                }

                // برای OpenAI استریم واقعی انجام نمی‌دهیم؛ فقط سوال کاربر را می‌فرستیم و جواب کامل را chunk می‌کنیم
                if ($this->requiresProxy($modelConfig)) {
                    Log::info('OPENAI_SIMPLE_STREAM_START', [
                        'requested_model' => $model,
                        'model_key' => $modelConfig->key,
                        'resolved_model' => $this->resolveModel($modelConfig),
                        'question_len' => mb_strlen($question),
                        'message_count' => 1,
                    ]);

                    $content = $this->askOpenAiSimple(
                        $question,
                        $provider['api_key'],
                        $provider['base_url'],
                        $this->resolveModel($modelConfig),
                        $modelConfig->proxy_url ?: null,
                        $uploadedFiles
                    );

                    if (trim((string) $content) === '') {
                        throw new RuntimeException('پاسخ خالی از OpenAI simple');
                    }

                    if ($onChunk) {
                        $onChunk($content);
                    }

                    $hasStarted = true;

                    Log::info('OPENAI_SIMPLE_STREAM_SUCCESS', [
                        'model' => $modelConfig->key,
                        'content_len' => strlen((string) $content),
                    ]);

                    return;
                }

                // مدل‌های غیر OpenAI با استریم قبلی
                $messages = ($needsFiles && $modelConfig->supports_files)
                    ? $this->buildMessagesWithFiles($systemPrompt, $history, $question, $uploadedFiles)
                    : $this->buildMessages($systemPrompt, $history, $question);

                $body = [
                    'model' => $this->resolveModel($modelConfig),
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 4000,
                    'stream' => true,
                ];

                $client = new Client([
                    'timeout' => 0,
                    'connect_timeout' => 15,
                ]);

                $response = $client->post($provider['base_url'], [
                    'headers' => $this->headers($provider['api_key']),
                    'json' => $body,
                    'stream' => true,
                ]);

                $bodyStream = $response->getBody();
                $buffer = '';

                while (!$bodyStream->eof()) {
                    $buffer .= $bodyStream->read(8192);

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = trim(substr($buffer, 0, $pos));
                        $buffer = substr($buffer, $pos + 1);

                        if ($line === '' || $line === 'data: [DONE]') {
                            continue;
                        }

                        if (str_starts_with($line, 'data: ')) {
                            $json = substr($line, 6);
                            $data = json_decode($json, true);

                            if (isset($data['choices'][0]['delta']['content'])) {
                                $chunk = (string) $data['choices'][0]['delta']['content'];

                                if ($chunk !== '') {
                                    $hasStarted = true;

                                    if ($onChunk) {
                                        $onChunk($chunk);
                                    }
                                }
                            }
                        }
                    }
                }

                if ($hasStarted && $modelConfig->key !== $model) {
                    Log::info('Stream fallback used', [
                        'requested' => $model,
                        'used' => $modelConfig->key,
                        'errors' => $errors,
                    ]);
                }

                return;
            } catch (Throwable $e) {
                $errors[$modelConfig->key] = $e->getMessage();

                Log::warning('Stream attempt failed', [
                    'model' => $modelConfig->key,
                    'has_started' => $hasStarted,
                    'error' => $e->getMessage(),
                ]);

                if ($hasStarted) {
                    return;
                }

                continue;
            }
        }

        Log::error('All stream models failed', ['errors' => $errors]);
    }

    private function normalizeHistory(array $messages): array
    {
        $out = [];

        foreach ($messages as $m) {
            if (!is_array($m)) {
                continue;
            }

            $role = $m['role'] ?? null;
            $content = trim((string) ($m['content'] ?? ''));

            if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $out[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $out;
    }

    public function supportsFiles(string $modelKey): bool
    {
        $model = AiModel::where('key', $modelKey)->first();

        return $model ? (bool) $model->supports_files : false;
    }
}
