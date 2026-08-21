<?php

namespace App\Services;

use App\Exceptions\AiRequestException;
use App\Models\AiProxyServer;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\AiModel;

class AiApiService
{
    private const MAX_DOCUMENT_CONTEXT_CHARACTERS = 120000;
    private const MAX_SINGLE_DOCUMENT_CHARACTERS = 80000;
    private const MAX_PDF_VISION_PAGES_PER_FILE = 12;
    private const MAX_PDF_VISION_PAGES_PER_REQUEST = 20;

    public function __construct(
        private FileService $fileService,
        private AiProxyRouter $proxyRouter,
    ) {}

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

    /** @deprecated Kept temporarily for compatibility with the legacy fallback block. */
    private function client(int $timeout = 150): Client
    {
        return new Client(['timeout' => $timeout]);
    }

    /** @deprecated Routing is now handled by AiProxyRouter. */
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

    private function documentTextForAi(
        object $fileModel,
        string $fullPath,
        string $mime,
        int &$remainingCharacters
    ): ?string {
        if ($remainingCharacters <= 0) {
            return null;
        }

        $text = trim((string) ($fileModel->extracted_text ?? ''));

        if ($text === '') {
            $text = trim((string) $this->fileService->extractText($fullPath, $mime));
        }

        if ($text === '') {
            Log::warning('No readable text found in uploaded document', [
                'path' => $fileModel->path ?? null,
                'mime_type' => $mime,
            ]);

            return null;
        }

        $limit = min(self::MAX_SINGLE_DOCUMENT_CHARACTERS, $remainingCharacters);
        $textLength = mb_strlen($text);
        $includedLength = min($textLength, $limit);
        $text = mb_substr($text, 0, $limit);
        $remainingCharacters -= $includedLength;

        if ($textLength > $limit) {
            $text .= "\n\n[ادامه متن به‌دلیل طول زیاد فایل در این درخواست ارسال نشد.]";
        }

        return $text;
    }

    private function isPdfFile(object $fileModel, string $fullPath, string $mime): bool
    {
        return $mime === 'application/pdf'
            || strtolower((string) ($fileModel->type ?? '')) === 'pdf'
            || strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function appendPdfVisionPages(
        array &$content,
        object $fileModel,
        string $fullPath,
        int &$remainingPages
    ): int {
        if ($remainingPages <= 0) {
            return 0;
        }

        $pageLimit = min(self::MAX_PDF_VISION_PAGES_PER_FILE, $remainingPages);
        $pages = $this->fileService->renderPdfPagesAsImages($fullPath, $pageLimit);

        if (empty($pages)) {
            Log::warning('No PDF page images were rendered for AI', [
                'path' => $fileModel->path ?? null,
            ]);

            return 0;
        }

        $fileName = $fileModel->original_name ?? 'PDF';

        foreach ($pages as $page) {
            $content[] = [
                'type' => 'text',
                'text' => "تصویر صفحه {$page['page']} از فایل PDF «{$fileName}»:",
            ];
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$page['mime_type']};base64," . base64_encode($page['data']),
                ],
            ];
        }

        $renderedCount = count($pages);
        $remainingPages -= $renderedCount;

        return $renderedCount;
    }

    private function buildOpenAiSimpleContent(string $question, array $uploadedFiles): string|array
    {
        if (empty($uploadedFiles)) {
            return $question;
        }

        $content = [];

        $content[] = [
            'type' => 'text',
            'text' => trim($question) !== ''
                ? trim($question)
                : 'فایل پیوست را تحلیل کن و نکات مهم آن را توضیح بده.',
        ];

        $remainingDocumentCharacters = self::MAX_DOCUMENT_CONTEXT_CHARACTERS;
        $remainingPdfVisionPages = self::MAX_PDF_VISION_PAGES_PER_REQUEST;

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

            if ($this->isPdfFile($fileModel, $fullPath, $mime)) {
                try {
                    $fileName = $fileModel->original_name ?? 'PDF';
                    $extractedText = $this->documentTextForAi(
                        $fileModel,
                        $fullPath,
                        $mime,
                        $remainingDocumentCharacters
                    );

                    if (trim((string) $extractedText) !== '') {
                        $content[] = [
                            'type' => 'text',
                            'text' => "\n\n--- متن استخراج‌شده از PDF {$fileName} ---\n{$extractedText}\n------------------\n",
                        ];
                    }

                    $renderedPages = $this->appendPdfVisionPages(
                        $content,
                        $fileModel,
                        $fullPath,
                        $remainingPdfVisionPages
                    );

                    if ($renderedPages === 0 && trim((string) $extractedText) === '') {
                        throw new RuntimeException("محتوای PDF {$fileName} قابل پردازش نیست.");
                    }
                } catch (Throwable $e) {
                    Log::error('OpenAI PDF processing failed', [
                        'path' => $fileModel->path,
                        'error' => $e->getMessage(),
                    ]);
                }

                continue;
            }

            try {
                $extractedText = $this->documentTextForAi(
                    $fileModel,
                    $fullPath,
                    $mime,
                    $remainingDocumentCharacters
                );
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
            'proxy' => $proxyUrl !== '' ? preg_replace('#(://)([^:@/]+):([^@/]+)@#', '$1***:***@', $proxyUrl) : null,
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

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ];
        $this->proxyRouter->applyCurlProxy($curlOptions, $proxyUrl);
        curl_setopt_array($ch, $curlOptions);

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

    /**
     * @param array<string, mixed> $body
     * @param array{type:string,label:string,proxy:?AiProxyServer,proxy_url:?string} $route
     * @return array{data:array<string,mixed>,http:int,elapsed_ms:int,primary_ip:string}
     */
    private function requestJson(
        AiModel $model,
        string $url,
        string $apiKey,
        array $body,
        array $route
    ): array {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            throw new RuntimeException('ساخت درخواست JSON ناموفق بود.');
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . trim($apiKey),
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => min(30, max(5, (int) ($model->timeout_seconds ?? 120))),
            CURLOPT_TIMEOUT => max(10, (int) ($model->timeout_seconds ?? 120)),
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ];
        $this->proxyRouter->applyCurlProxy($options, $route['proxy_url']);
        curl_setopt_array($ch, $options);

        $startedAt = microtime(true);
        $response = curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new AiRequestException(
                $this->proxyRouter->sanitizeError("cURL error {$curlErrno}: {$curlError}"),
                $httpCode,
                $curlErrno,
                $elapsedMs,
                $primaryIp,
            );
        }

        $data = is_string($response) ? json_decode($response, true) : null;

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new AiRequestException(
                "HTTP {$httpCode}: " . $this->apiErrorMessage($data, (string) $response),
                $httpCode,
                0,
                $elapsedMs,
                $primaryIp,
            );
        }

        if (!is_array($data)) {
            throw new AiRequestException('پاسخ API از نوع JSON معتبر نیست.', $httpCode, 0, $elapsedMs, $primaryIp);
        }

        if (isset($data['error'])) {
            throw new AiRequestException(
                "API error: " . $this->apiErrorMessage($data, (string) $response),
                $httpCode,
                0,
                $elapsedMs,
                $primaryIp,
            );
        }

        return [
            'data' => $data,
            'http' => $httpCode,
            'elapsed_ms' => $elapsedMs,
            'primary_ip' => $primaryIp,
        ];
    }

    /** @param array<string, mixed>|null $data */
    private function apiErrorMessage(?array $data, string $raw): string
    {
        $error = $data['error'] ?? null;

        if (is_array($error)) {
            return mb_substr((string) ($error['message'] ?? json_encode($error, JSON_UNESCAPED_UNICODE)), 0, 800);
        }

        if (is_string($error) && $error !== '') {
            return mb_substr($error, 0, 800);
        }

        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($raw)));

        return $plain !== '' ? mb_substr($plain, 0, 500) : 'پاسخ خطا بدون توضیح دریافت شد.';
    }

    private function completionTokenField(AiModel $model): string
    {
        return str_contains(strtolower((string) $model->endpoint), 'api.openai.com')
            && preg_match('/^(gpt-5|o[134])/', strtolower((string) $model->model_mapping))
                ? 'max_completion_tokens'
                : 'max_tokens';
    }

    /**
     * @param array<string, mixed> $body
     * @param array{type:string,label:string,proxy:?AiProxyServer,proxy_url:?string} $route
     */
    private function requestStream(
        AiModel $model,
        string $url,
        string $apiKey,
        array $body,
        array $route,
        ?callable $onChunk
    ): int {
        $body['stream'] = true;
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            throw new RuntimeException('ساخت درخواست JSON ناموفق بود.');
        }

        $buffer = '';
        $rawBody = '';
        $receivedContent = false;
        $processLine = static function (string $line) use (&$receivedContent, $onChunk): void {
            $line = trim($line);

            if ($line === '' || $line === 'data: [DONE]' || !str_starts_with($line, 'data: ')) {
                return;
            }

            $data = json_decode(substr($line, 6), true);
            $chunk = $data['choices'][0]['delta']['content'] ?? '';

            if (is_string($chunk) && $chunk !== '') {
                $receivedContent = true;

                if ($onChunk) {
                    $onChunk($chunk);
                }
            }
        };

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Accept: text/event-stream',
                'Content-Type: application/json',
                'Authorization: Bearer ' . trim($apiKey),
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => min(30, max(5, (int) ($model->timeout_seconds ?? 120))),
            CURLOPT_TIMEOUT => max(30, (int) ($model->timeout_seconds ?? 120)),
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer, &$rawBody, $processLine): int {
                if (strlen($rawBody) < 20000) {
                    $rawBody .= substr($chunk, 0, 20000 - strlen($rawBody));
                }

                $buffer .= $chunk;

                while (($position = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $position);
                    $buffer = substr($buffer, $position + 1);
                    $processLine($line);
                }

                return strlen($chunk);
            },
        ];
        $this->proxyRouter->applyCurlProxy($options, $route['proxy_url']);
        curl_setopt_array($ch, $options);

        $startedAt = microtime(true);
        curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($buffer !== '') {
            $processLine($buffer);
        }

        if ($curlErrno !== 0) {
            throw new AiRequestException(
                $this->proxyRouter->sanitizeError("cURL error {$curlErrno}: {$curlError}"),
                $httpCode,
                $curlErrno,
                $elapsedMs,
                $primaryIp,
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $decoded = json_decode($rawBody, true);
            throw new AiRequestException(
                "HTTP {$httpCode}: " . $this->apiErrorMessage(is_array($decoded) ? $decoded : null, $rawBody),
                $httpCode,
                0,
                $elapsedMs,
                $primaryIp,
            );
        }

        if (!$receivedContent) {
            $decoded = json_decode($rawBody, true);
            $content = is_array($decoded) ? ($decoded['choices'][0]['message']['content'] ?? '') : '';

            if (!is_string($content) || trim($content) === '') {
                throw new AiRequestException('پاسخ مدل خالی بود.', $httpCode, 0, $elapsedMs, $primaryIp);
            }

            if ($onChunk) {
                $onChunk($content);
            }
        }

        return $elapsedMs;
    }

    /** @return array<string, mixed> */
    public function diagnoseModel(AiModel $model, string $routeType, ?AiProxyServer $proxy = null): array
    {
        $provider = $this->getProviderConfigByModel($model);

        if (!$provider['api_key']) {
            return ['ok' => false, 'http' => 0, 'elapsed_ms' => 0, 'diagnosis' => 'کلید API تنظیم نشده است.'];
        }

        if ($routeType === 'proxy' && !$proxy) {
            return ['ok' => false, 'http' => 0, 'elapsed_ms' => 0, 'diagnosis' => 'یک پراکسی فعال انتخاب کنید.'];
        }

        $route = [
            'type' => $routeType,
            'label' => $proxy?->name ?? 'direct',
            'proxy' => $proxy,
            'proxy_url' => $proxy?->proxyUrl(),
        ];
        $body = [
            'model' => $this->resolveModel($model),
            'messages' => [['role' => 'user', 'content' => 'Reply with exactly: OK']],
            'temperature' => 0,
            'stream' => false,
        ];
        $body[$this->completionTokenField($model)] = 10;

        try {
            $result = $this->requestJson($model, $provider['base_url'], $provider['api_key'], $body, $route);
            $answer = $result['data']['choices'][0]['message']['content'] ?? '';

            if ($routeType === 'direct') {
                $this->proxyRouter->markDirectSuccess($model);
            } else {
                $this->proxyRouter->markProxyResult($proxy, true, $result['elapsed_ms']);
            }

            return [
                'ok' => trim((string) $answer) !== '',
                'http' => $result['http'],
                'elapsed_ms' => $result['elapsed_ms'],
                'api_server_ip' => $result['primary_ip'],
                'answer' => trim((string) $answer),
                'diagnosis' => trim((string) $answer) !== '' ? 'اتصال و پاسخ مدل موفق بود.' : 'اتصال برقرار شد اما پاسخ مدل خالی بود.',
                'route_label' => $route['label'],
                'usage' => $result['data']['usage'] ?? null,
            ];
        } catch (Throwable $exception) {
            if ($routeType === 'direct') {
                $this->proxyRouter->markDirectFailure($model, $exception);
            } else {
                $this->proxyRouter->markProxyResult(
                    $proxy,
                    false,
                    $exception instanceof AiRequestException ? $exception->elapsedMs : 0,
                    $exception->getMessage(),
                );
            }

            $http = $exception instanceof AiRequestException ? $exception->httpCode : 0;
            $errno = $exception instanceof AiRequestException ? $exception->curlErrno : 0;

            return [
                'ok' => false,
                'http' => $http,
                'elapsed_ms' => $exception instanceof AiRequestException ? $exception->elapsedMs : 0,
                'api_server_ip' => $exception instanceof AiRequestException ? $exception->primaryIp : '',
                'diagnosis' => $this->proxyRouter->diagnose($errno, $http, $exception->getMessage()),
                'error' => $this->proxyRouter->sanitizeError($exception->getMessage()),
                'route_label' => $route['label'],
            ];
        }
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

        $userContent[] = [
            'type' => 'text',
            'text' => trim($message) !== ''
                ? trim($message)
                : 'فایل پیوست را تحلیل کن و نکات مهم آن را توضیح بده.',
        ];

        $remainingDocumentCharacters = self::MAX_DOCUMENT_CONTEXT_CHARACTERS;
        $remainingPdfVisionPages = self::MAX_PDF_VISION_PAGES_PER_REQUEST;

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
            } elseif ($this->isPdfFile($fileModel, $fullPath, $mime)) {
                try {
                    $fileName = $fileModel->original_name ?? 'PDF';
                    $extractedText = $this->documentTextForAi(
                        $fileModel,
                        $fullPath,
                        $mime,
                        $remainingDocumentCharacters
                    );

                    if (trim((string) $extractedText) !== '') {
                        $userContent[] = [
                            'type' => 'text',
                            'text' => "\n\n--- متن استخراج‌شده از PDF {$fileName} ---\n{$extractedText}\n------------------\n",
                        ];
                    }

                    $renderedPages = $this->appendPdfVisionPages(
                        $userContent,
                        $fileModel,
                        $fullPath,
                        $remainingPdfVisionPages
                    );

                    if ($renderedPages === 0 && trim((string) $extractedText) === '') {
                        throw new RuntimeException("محتوای PDF {$fileName} قابل پردازش نیست.");
                    }
                } catch (Throwable $e) {
                    Log::error('PDF vision processing failed', [
                        'path' => $fileModel->path,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                try {
                    $extractedText = $this->documentTextForAi(
                        $fileModel,
                        $fullPath,
                        $mime,
                        $remainingDocumentCharacters
                    );
                    $fileName = $fileModel->original_name ?? 'فایل';

                    if (trim((string) $extractedText) !== '') {
                        $userContent[] = [
                            'type' => 'text',
                            'text' => "\n\n--- محتوای فایل {$fileName} ---\n{$extractedText}\n------------------\n",
                        ];
                    }
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

        return $this->callWithRouting(
            $message,
            $model,
            $systemPrompt,
            $history,
            $uploadedFiles,
            $chain,
        );

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

        $this->streamWithRouting(
            $question,
            $model,
            $systemPrompt,
            $history,
            $uploadedFiles,
            $chain,
            $onChunk,
        );

        return;

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

    /**
     * @param array<int, AiModel> $chain
     */
    private function callWithRouting(
        string $message,
        string $requestedModel,
        string $systemPrompt,
        array $history,
        array $uploadedFiles,
        array $chain,
    ): string {
        $needsFiles = $uploadedFiles !== [];
        $errors = [];

        foreach ($chain as $modelConfig) {
            try {
                $provider = $this->getProviderConfigByModel($modelConfig);

                if (!$provider['api_key']) {
                    throw new RuntimeException("کلید API برای مدل {$modelConfig->key} تنظیم نشده است.");
                }

                $messages = ($needsFiles && $modelConfig->supports_files)
                    ? $this->buildMessagesWithFiles($systemPrompt, $history, $message, $uploadedFiles)
                    : $this->buildMessages($systemPrompt, $history, $message);
                $body = [
                    'model' => $this->resolveModel($modelConfig),
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'stream' => false,
                ];
                $body[$this->completionTokenField($modelConfig)] = 4000;
                $routes = $this->proxyRouter->routesFor($modelConfig);

                if ($routes === []) {
                    throw new RuntimeException("برای مدل {$modelConfig->key} مسیر پراکسی فعالی وجود ندارد.");
                }

                $lastException = null;

                foreach ($routes as $route) {
                    try {
                        $result = $this->requestJson(
                            $modelConfig,
                            $provider['base_url'],
                            $provider['api_key'],
                            $body,
                            $route,
                        );
                        $content = $result['data']['choices'][0]['message']['content'] ?? '';

                        if (trim((string) $content) === '') {
                            throw new AiRequestException(
                                "پاسخ خالی از مدل {$modelConfig->key}",
                                $result['http'],
                                0,
                                $result['elapsed_ms'],
                                $result['primary_ip'],
                            );
                        }

                        if ($route['type'] === 'direct') {
                            $this->proxyRouter->markDirectSuccess($modelConfig);
                        } else {
                            $this->proxyRouter->markProxyResult($route['proxy'], true, $result['elapsed_ms']);
                        }

                        Log::info('AI route succeeded', [
                            'requested_model' => $requestedModel,
                            'used_model' => $modelConfig->key,
                            'route' => $route['label'],
                            'elapsed_ms' => $result['elapsed_ms'],
                        ]);

                        return trim((string) $content);
                    } catch (Throwable $exception) {
                        $lastException = $exception;
                        $errors[$modelConfig->key . ':' . $route['label']] = $exception->getMessage();

                        if ($route['type'] === 'direct') {
                            $this->proxyRouter->markDirectFailure($modelConfig, $exception);

                            if (!$this->proxyRouter->shouldRetryThroughProxy($exception)) {
                                break;
                            }
                        } else {
                            $this->proxyRouter->markProxyResult(
                                $route['proxy'],
                                false,
                                $exception instanceof AiRequestException ? $exception->elapsedMs : 0,
                                $exception->getMessage(),
                            );
                        }

                        Log::warning('AI route failed', [
                            'model' => $modelConfig->key,
                            'route' => $route['label'],
                            'error' => $this->proxyRouter->sanitizeError($exception->getMessage()),
                        ]);
                    }
                }

                if ($lastException) {
                    throw $lastException;
                }
            } catch (Throwable $exception) {
                $errors[$modelConfig->key] = $exception->getMessage();
            }
        }

        Log::error('All AI models failed sync', ['errors' => $errors]);

        return 'متأسفانه در حال حاضر امکان پاسخ‌گویی وجود ندارد. لطفاً چند لحظه بعد دوباره تلاش کنید.';
    }

    /**
     * @param array<int, AiModel> $chain
     */
    private function streamWithRouting(
        string $question,
        string $requestedModel,
        string $systemPrompt,
        array $history,
        array $uploadedFiles,
        array $chain,
        ?callable $onChunk,
    ): void {
        $needsFiles = $uploadedFiles !== [];
        $errors = [];
        $hasStarted = false;

        foreach ($chain as $modelConfig) {
            try {
                $provider = $this->getProviderConfigByModel($modelConfig);

                if (!$provider['api_key']) {
                    throw new RuntimeException("کلید API برای مدل {$modelConfig->key} تنظیم نشده است.");
                }

                $messages = ($needsFiles && $modelConfig->supports_files)
                    ? $this->buildMessagesWithFiles($systemPrompt, $history, $question, $uploadedFiles)
                    : $this->buildMessages($systemPrompt, $history, $question);
                $body = [
                    'model' => $this->resolveModel($modelConfig),
                    'messages' => $messages,
                    'temperature' => 0.7,
                ];
                $body[$this->completionTokenField($modelConfig)] = 4000;
                $routes = $this->proxyRouter->routesFor($modelConfig);

                if ($routes === []) {
                    throw new RuntimeException("برای مدل {$modelConfig->key} مسیر پراکسی فعالی وجود ندارد.");
                }

                $lastException = null;

                foreach ($routes as $route) {
                    try {
                        $elapsedMs = $this->requestStream(
                            $modelConfig,
                            $provider['base_url'],
                            $provider['api_key'],
                            $body,
                            $route,
                            static function (string $chunk) use (&$hasStarted, $onChunk): void {
                                $hasStarted = true;

                                if ($onChunk) {
                                    $onChunk($chunk);
                                }
                            },
                        );

                        if ($route['type'] === 'direct') {
                            $this->proxyRouter->markDirectSuccess($modelConfig);
                        } else {
                            $this->proxyRouter->markProxyResult($route['proxy'], true, $elapsedMs);
                        }

                        Log::info('AI stream route succeeded', [
                            'requested_model' => $requestedModel,
                            'used_model' => $modelConfig->key,
                            'route' => $route['label'],
                            'elapsed_ms' => $elapsedMs,
                        ]);

                        return;
                    } catch (Throwable $exception) {
                        $lastException = $exception;
                        $errors[$modelConfig->key . ':' . $route['label']] = $exception->getMessage();

                        if ($hasStarted) {
                            Log::warning('AI stream interrupted after output started', [
                                'model' => $modelConfig->key,
                                'route' => $route['label'],
                                'error' => $this->proxyRouter->sanitizeError($exception->getMessage()),
                            ]);

                            return;
                        }

                        if ($route['type'] === 'direct') {
                            $this->proxyRouter->markDirectFailure($modelConfig, $exception);

                            if (!$this->proxyRouter->shouldRetryThroughProxy($exception)) {
                                break;
                            }
                        } else {
                            $this->proxyRouter->markProxyResult(
                                $route['proxy'],
                                false,
                                $exception instanceof AiRequestException ? $exception->elapsedMs : 0,
                                $exception->getMessage(),
                            );
                        }
                    }
                }

                if ($lastException) {
                    throw $lastException;
                }
            } catch (Throwable $exception) {
                $errors[$modelConfig->key] = $exception->getMessage();
            }
        }

        Log::error('All AI models failed stream', ['errors' => $errors]);
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
