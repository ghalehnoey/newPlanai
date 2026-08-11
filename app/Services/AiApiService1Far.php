<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
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
            case 'gpt':
                if (empty($imagePaths)) {
                    $url = 'https://api.openai.com/v1/chat/completions';
                    $headers['Authorization'] = "Bearer {$key}";

                    $body = [
                        'model'    => 'gpt-5.4',
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $message],
                        ],
                        'temperature'           => 0.1,
                        'max_completion_tokens' => 10000,
                    ];
                } else {
                    $url = 'https://api.openai.com/v1/responses';
                    $headers['Authorization'] = "Bearer {$key}";

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

                    if (empty($imageBlocks)) {
                        $url = 'https://api.openai.com/v1/chat/completions';
                        $headers['Authorization'] = "Bearer {$key}";

                        $body = [
                            'model'    => 'gpt-5.4',
                            'messages' => [
                                ['role' => 'system', 'content' => $systemPrompt],
                                ['role' => 'user',   'content' => $message],
                            ],
                            'temperature'           => 0.1,
                            'max_completion_tokens' => 10000,
                        ];
                    } else {
                        $userContent = [
                            [
                                'type' => 'input_text',
                                'text' => $message,
                            ],
                        ];

                        $userContent = array_merge($userContent, $imageBlocks);

                        $body = [
                            'model' => 'gpt-5.4',
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
                            'max_output_tokens' => 4000,
                            'temperature'       => 0.1,
                        ];
                    }
                }
                break;

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
                    'max_tokens'  => 4000,
                ];
                break;

            case 'grok':
                $url = 'https://api.x.ai/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $body = [
                    'model'    => 'grok-3',
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 2500,
                ];
                break;

            case 'deepseek':
                $url = 'https://api.deepseek.com/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $body = [
                    'model'    => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 2500,
                ];
                break;

            default:
                return "مدل ناشناخته است.";
        }

        try {
            $client = $this->createClient($url);
            
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $body,
                'timeout' => 150,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            switch ($model) {
                case 'claude':
                    return $data['content'][0]['text'] ?? 'پاسخی دریافت نشد.';

                case 'gpt':
                    if (isset($data['output_text'])) {
                        return $data['output_text'] ?: 'پاسخی دریافت نشد.';
                    }

                    if (isset($data['output'][0]['content'][0]['text'])) {
                        return $data['output'][0]['content'][0]['text'] ?: 'پاسخی دریافت نشد.';
                    }

                    return $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';

                default:
                    return $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';
            }

        } catch (\Exception $e) {
            return "خطا در ارتباط: " . $e->getMessage();
        }
    }

    public function callWithHistory(
        string $message,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = [],
        array $imagePaths = []
    ): string {
        $key = config("services.{$model}.key");

        if (!$key) {
            return "خطا: کلید API برای مدل {$model} تنظیم نشده.";
        }

        $history = $this->normalizeHistory($previousChatMessages);

        $url     = '';
        $headers = ['Content-Type' => 'application/json'];
        $body    = [];

        switch ($model) {
            case 'gpt':
                if (empty($imagePaths)) {
                    $url = 'https://api.openai.com/v1/chat/completions';
                    $headers['Authorization'] = "Bearer {$key}";

                    $messages = [];
                    $messages[] = ['role' => 'system', 'content' => $systemPrompt];

                    foreach ($history as $m) {
                        $messages[] = [
                            'role'    => $m['role'],
                            'content' => $m['content'],
                        ];
                    }

                    $messages[] = ['role' => 'user', 'content' => $message];

                    $body = [
                        'model'    => 'gpt-5.4',
                        'messages' => $messages,
                        'temperature'           => 0.1,
                        'max_completion_tokens' => 10000,
                    ];

                    return $this->postAndExtract($url, $headers, $body, 'gpt');
                }

                $url = 'https://api.openai.com/v1/responses';
                $headers['Authorization'] = "Bearer {$key}";

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

                if (empty($imageBlocks)) {
                    return $this->callWithHistory($message, $model, $systemPrompt, $history, []);
                }

                $input = [];

                $input[] = [
                    'role'    => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $systemPrompt],
                    ],
                ];

                foreach ($history as $m) {
                    $input[] = [
                        'role'    => $m['role'],
                        'content' => [
                            ['type' => 'input_text', 'text' => $m['content']],
                        ],
                    ];
                }

                $userContent = [
                    ['type' => 'input_text', 'text' => $message],
                ];
                $userContent = array_merge($userContent, $imageBlocks);

                $input[] = [
                    'role'    => 'user',
                    'content' => $userContent,
                ];

                $body = [
                    'model' => 'gpt-5.4',
                    'input' => $input,
                    'max_output_tokens' => 4000,
                    'temperature'       => 0.1,
                ];

                return $this->postAndExtract($url, $headers, $body, 'gpt');

            case 'claude':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers['x-api-key']         = $key;
                $headers['anthropic-version'] = '2023-06-01';

                $messages = [];
                foreach ($history as $m) {
                    $messages[] = [
                        'role'    => $m['role'],
                        'content' => $m['content'],
                    ];
                }
                $messages[] = ['role' => 'user', 'content' => $message];

                $body = [
                    'model'    => 'claude-sonnet-4-5',
                    'system'   => $systemPrompt,
                    'messages' => $messages,
                    'temperature' => 0.1,
                    'max_tokens'  => 4000,
                ];

                return $this->postAndExtract($url, $headers, $body, 'claude');

            case 'grok':
                $url = 'https://api.x.ai/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $messages = [];
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];

                foreach ($history as $m) {
                    $messages[] = [
                        'role'    => $m['role'],
                        'content' => $m['content'],
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $message];

                $body = [
                    'model'       => 'grok-3',
                    'messages'    => $messages,
                    'temperature' => 0.1,
                    'max_tokens'  => 2500,
                ];

                return $this->postAndExtract($url, $headers, $body, 'default');

            case 'deepseek':
                $url = 'https://api.deepseek.com/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $messages = [];
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];

                foreach ($history as $m) {
                    $messages[] = [
                        'role'    => $m['role'],
                        'content' => $m['content'],
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $message];

                $body = [
                    'model'       => 'deepseek-chat',
                    'messages'    => $messages,
                    'temperature' => 0.1,
                    'max_tokens'  => 2500,
                ];

                return $this->postAndExtract($url, $headers, $body, 'default');

            default:
                return "مدل ناشناخته است.";
        }
    }

    public function prepareStreamRequest(
        string $question,
        string $model,
        string $systemPrompt,
        array $previousChatMessages = []
    ): array {
        $key = config("services.{$model}.key");

        if (!$key) {
            throw new RuntimeException("کلید API تنظیم نشده است.");
        }

        $finalPrompt = "سؤال کاربر:\n{$question}\n\nپاسخ دقیق:";

        $url     = '';
        $headers = ['Content-Type' => 'application/json'];
        $body    = [];

        $history = $this->normalizeHistory($previousChatMessages);

        switch ($model) {
            case 'gpt':
                $url = 'https://api.openai.com/v1/chat/completions';
                $headers['Authorization'] = "Bearer {$key}";

                $messages = [];
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];

                foreach ($history as $m) {
                    $messages[] = [
                        'role'    => $m['role'],
                        'content' => $m['content'],
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $finalPrompt];

                $body = [
                    'model'    => 'gpt-5.4',
                    'messages' => $messages,
                    'temperature'           => 0.1,
                    'max_completion_tokens' => 10000,
                    'stream'                => true,
                ];
                break;

            case 'claude':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers['x-api-key']         = $key;
                $headers['anthropic-version'] = '2023-06-01';

                $messages = [];
                foreach ($history as $m) {
                    $messages[] = [
                        'role'    => $m['role'],
                        'content' => $m['content'],
                    ];
                }
                $messages[] = ['role' => 'user', 'content' => $finalPrompt];

                $body = [
                    'model'    => 'claude-sonnet-4-5',
                    'system'   => $systemPrompt,
                    'messages' => $messages,
                    'temperature' => 0.1,
                    'max_tokens'  => 4000,
                    'stream'      => true,
                ];
                break;

            default:
                throw new RuntimeException("استریم برای این مدل پشتیبانی نمی‌شود.");
        }

        return [$url, $headers, $body];
    }

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

    private function normalizeHistory(array $previousChatMessages): array
    {
        $out = [];

        foreach ($previousChatMessages as $m) {
            if (!is_array($m)) continue;

            $role = $m['role'] ?? null;
            $content = isset($m['content']) ? (string)$m['content'] : '';

            $content = trim($content);
            if ($content === '') continue;

            if ($role !== 'user' && $role !== 'assistant') continue;

            $out[] = [
                'role'    => $role,
                'content' => $content,
            ];
        }

        return $out;
    }

    private function postAndExtract(string $url, array $headers, array $body, string $extractMode): string
    {
        try {
            $client = $this->createClient($url);
            
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $body,
                'timeout' => 150,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return match ($extractMode) {
                'claude' => $data['content'][0]['text'] ?? 'پاسخی دریافت نشد.',
                'gpt'    => $this->extractGptText($data),
                default  => $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.',
            };
        } catch (\Exception $e) {
            return "خطا در ارتباط: " . $e->getMessage();
        }
    }

    private function extractGptText(array $data): string
    {
        if (isset($data['output_text'])) {
            return $data['output_text'] ?: 'پاسخی دریافت نشد.';
        }

        if (isset($data['output'][0]['content'][0]['text'])) {
            return $data['output'][0]['content'][0]['text'] ?: 'پاسخی دریافت نشد.';
        }

        return $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';
    }

    /**
     * ساخت Guzzle Client با پراکسی SOCKS5 برای APIهای خارجی
     */
   /* private function createClient(string $url): Client
{
    return new Client(['timeout' => 150]);
}
    */

private function createClient(string $url): Client
    {
        $needsProxy = str_contains($url, 'openai.com') ||
                      str_contains($url, 'x.ai') ||
                      str_contains($url, 'deepseek.com') ||
                      str_contains($url, 'anthropic.com');

        if (!$needsProxy) {
            return new Client(['timeout' => 150]);
        }

        return new Client([
            'timeout' => 150,
            'handler' => HandlerStack::create(new CurlHandler()),
            'curl' => [
                CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
                CURLOPT_PROXY     => '127.0.0.1:1080',
            ],
        ]);
    }
}
