<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiApiService
{
    /**
     * فراخوانی مدل هوش مصنوعی
     *
     * @param string $message      متن سؤال یا پرامپت
     * @param string $model        gpt | claude | grok | deepseek
     * @param string $systemPrompt پرامپت سیستمی
     * @param array  $imagePaths   آرایه‌ای از مسیرهای لوکال فایل‌های تصویر (برای GPT)
     */
    public function call(
        string $message,
        string $model,
        string $systemPrompt,
        array $imagePaths = []
    ): string {
        $key = config("services.{$model}.key");

        if (!$key) {
            return "خطا: کلید API برای مدل {$model} تنظیم نشده.";
        }

        $url     = '';
        $headers = ['Content-Type' => 'application/json'];
        $body    = [];

        switch ($model) {
            /* =========================================================
             * GPT (OpenAI) — متن + امکان تصویر
             * =======================================================*/
            case 'gpt':

                // اگر تصویر نداریم → همان لاجیک قبلی chat/completions
                if (empty($imagePaths)) {
                    $url = 'https://api.openai.com/v1/chat/completions';
                    $headers['Authorization'] = "Bearer {$key}";

                    $body = [
                        'model'    => 'gpt-5.1',
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $message],
                        ],
                        'temperature'           => 0.1,
                        'max_completion_tokens' => 5000, // افزایش تعداد توکن
                    ];
                }

                // اگر تصویر داریم → از /v1/responses با Base64 استفاده کن
                else {
                    $url = 'https://api.openai.com/v1/responses';
                    $headers['Authorization'] = "Bearer {$key}";

                    // تبدیل تصاویر به بلوک‌های input_image (data URL)
                    $imageBlocks = [];
                    foreach ($imagePaths as $path) {
                        if (!is_string($path) || !file_exists($path)) {
                            continue;
                        }

                        $base64 = base64_encode(file_get_contents($path));
                        $mime   = $this->mimeFromPath($path);

                        $imageBlocks[] = [
                            'type'      => 'input_image',
                            'image_url' => "data:{$mime};base64,{$base64}",
                        ];
                    }

                    // اگر به هر دلیل هیچ تصویر معتبری نبود، برگرد به حالت متنی
                    if (empty($imageBlocks)) {
                        $url = 'https://api.openai.com/v1/chat/completions';
                        $headers['Authorization'] = "Bearer {$key}";

                        $body = [
                            'model'    => 'gpt-5.1',
                            'messages' => [
                                ['role' => 'system', 'content' => $systemPrompt],
                                ['role' => 'user',   'content' => $message],
                            ],
                            'temperature'           => 0.1,
                            'max_completion_tokens' => 5000, // افزایش تعداد توکن
                        ];
                    } else {
                        // content کاربر شامل متن + تصاویر
                        $userContent = [
                            [
                                'type' => 'input_text',
                                'text' => $message,
                            ],
                        ];

                        $userContent = array_merge($userContent, $imageBlocks);

                        $body = [
                            'model' => 'gpt-4o-mini',
                            'input' => [
                                [
                                    'role'    => 'system',
                                    'content' => [
                                        [
                                            'type' => 'input_text',
                                            'text' => $systemPrompt,
                                        ],
                                    ],
                                ],
                                [
                                    'role'    => 'user',
                                    'content' => $userContent,
                                ],
                            ],
                            'max_output_tokens' => 6000, // افزایش تعداد توکن خروجی
                            'temperature'       => 0.1,
                        ];
                    }
                }

                break;

            /* =========================================================
             * Claude
             * =======================================================*/
            case 'claude':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers['x-api-key']         = $key;
                $headers['anthropic-version'] = '2023-06-01';

                $body = [
                    'model'    => 'claude-sonnet-4-5',
                    'system'   => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 5000, // افزایش تعداد توکن
                ];
                break;

            /* =========================================================
             * Grok
             * =======================================================*/
            case 'grok':
                $url = 'https://api.x.ai/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $body = [
                    'model'    => 'grok-4',
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 5000, // افزایش تعداد توکن
                ];
                break;

            /* =========================================================
             * DeepSeek
             * =======================================================*/
            case 'deepseek':
                $url = 'https://api.deepseek.com/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $body = [
                    'model'    => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 5000, // افزایش تعداد توکن
                ];
                break;

            default:
                return "مدل ناشناخته است.";
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(180)        // تایم‌اوت بیشتر برای پردازش
                ->retry(5, 3000)      // پنج بار تلاش با ۳ ثانیه فاصله
                ->post($url, $body);

            if (!$response->successful()) {
                return "خطای API: " . $response->body();
            }

            $data = $response->json();

            // پاسخ‌دهی بر اساس نوع مدل
            switch ($model) {
                case 'claude':
                    return $data['content'][0]['text'] ?? 'پاسخی دریافت نشد.';

                case 'gpt':
                    // اگر از /v1/responses آمده (vision + تصویر)
                    if (isset($data['output_text'])) {
                        return $data['output_text'] ?: 'پاسخی دریافت نشد.';
                    }

                    if (isset($data['output'][0]['content'][0]['text'])) {
                        return $data['output'][0]['content'][0]['text'] ?: 'پاسخی دریافت نشد.';
                    }

                    // حالت کلاسیک chat/completions
                    return $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';

                default:
                    return $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';
            }

        } catch (\Exception $e) {
            return "خطا در ارتباط: " . $e->getMessage();
        }
    }

    /**
     * آماده‌سازی اطلاعات برای استریم (برای استفاده در ChatController)
     * فقط متن؛ تصویر در استریم استفاده نشده
     */
    public function prepareStreamRequest(string $question, string $model, string $systemPrompt): array
    {
        $key = config("services.{$model}.key");

        if (!$key) {
            throw new RuntimeException("کلید API تنظیم نشده است.");
        }

        $finalPrompt = "سؤال کاربر:\n{$question}\n\nپاسخ دقیق:";

        $url     = '';
        $headers = ['Content-Type' => 'application/json'];
        $body    = [];

        switch ($model) {
            case 'gpt':
                $url = 'https://api.openai.com/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $body = [
                    'model'    => 'gpt-5.1',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $finalPrompt],
                    ],
                    'temperature'           => 0.1,
                    'max_completion_tokens' => 5000, // افزایش تعداد توکن
                    'stream'                => true,
                ];
                break;

            case 'claude':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers['x-api-key']         = $key;
                $headers['anthropic-version'] = '2023-06-01';

                $body = [
                    'model'    => 'claude-sonnet-4-5',
                    'system'   => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $finalPrompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 5000, // افزایش تعداد توکن
                    'stream'      => true,
                ];
                break;

            default:
                throw new RuntimeException("استریم برای این مدل پشتیبانی نمی‌شود.");
        }

        return [$url, $headers, $body];
    }

    /**
     * تشخیص MIME بر اساس پسوند فایل
     */
    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'image/jpeg',
        };
    }
}
