<?php

$bazaarPlanProductIds = [];
$bazaarProductPlanIds = [];
foreach (explode(',', (string) env('BAZAAR_PLAN_PRODUCT_IDS', '')) as $pair) {
    [$planId, $productId] = array_pad(explode(':', trim($pair), 2), 2, null);
    $planId = filter_var($planId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $productId = is_string($productId) ? trim($productId) : '';
    if ($planId && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/', $productId)) {
        $bazaarPlanProductIds[(int) $planId] = $productId;
        $bazaarProductPlanIds[$productId] = (int) $planId;
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services Configuration
    |--------------------------------------------------------------------------
    */

    // IranGPU
    'irangpu' => [
        'key' => env('IRANGPU_API_KEY'),
        'base_url' => env('IRANGPU_BASE_URL', 'https://services.irangpu.com'),
        'models' => [
            'gpt4' => 'openai/gpt-4.1-2025-04-14',
            'gemini' => 'google/gemini-2.5-flash',
            'claude' => 'anthropic/claude-4.5-haiku-20251001',
            'deepseek' => 'deepseek/deepseek-v3.2-20251201',
        ],
    ],

    // GapGPT
    'gapgpt' => [
        'key' => env('GAPGPT_API_KEY'),
        'base_url' => env('GAPGPT_BASE_URL', 'https://api.gapgpt.app/v1'),
        'models' => [
            'qwen' => 'gapgpt-qwen-3.5',
            'claude' => 'claude-sonnet-4-6',
        ],
    ],

    // DeepSeek
    'deepseek' => [
        'key' => env('DEEPSEEK_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Direct
    |--------------------------------------------------------------------------
    |
    | ??? ??? ???? ???? ??????? ??? ?? ??? ??? ????? ?? ????????.
    | AiApiService::askOpenAiSimple ?? ??? ??????? ??????? ??????.
    |
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY'),

        // ?????? xray ??? ????
        'proxy' => env('OPENAI_PROXY', 'socks5h://127.0.0.1:1080'),

        // Base URL ?????
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

        // ???? ?????? Chat Completions
        'chat_url' => env('OPENAI_CHAT_URL', 'https://api.openai.com/v1/chat/completions'),

        // ???? ?? ??? ???? ? ???? ????
        'model' => env('OPENAI_MODEL', 'gpt-5.4'),

        // ??????? ???? ???? ?? ??????
        'timeout' => env('OPENAI_TIMEOUT', 120),
        'connect_timeout' => env('OPENAI_CONNECT_TIMEOUT', 10),
        'max_completion_tokens' => env('OPENAI_MAX_COMPLETION_TOKENS', 600),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
    ],

    // Claude direct
    'claude' => [
        'key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5'),
        'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
    ],

    // Grok / xAI
    'grok' => [
        'key' => env('GROK_KEY'),
        'model' => env('GROK_MODEL', 'grok-3'),
        'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
    ],

    // Qwen direct
    'qwen' => [
        'key' => env('QWEN_API_KEY'),
        'model' => env('QWEN_MODEL', 'qwen-turbo'),
        'base_url' => env('QWEN_BASE_URL', 'https://dashscope.aliyuncs.com/compatible-mode/v1'),
    ],

    // Gemini direct
    'payamak' => [
        'username' => env('PAYAMAK_USERNAME'),
        'password' => env('PAYAMAK_API_KEY'),
        'body_id'  => env('PAYAMAK_BODY_ID'),
        'sender'   => env('PAYAMAK_SENDER_NUMBER'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash-exp'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'bazaar' => [
        // Keep this false until the product mapping and server access token are set.
        'enabled' => env('BAZAAR_ENABLED', false),
        'package_name' => env('BAZAAR_PACKAGE_NAME', 'com.hakimisadr.smartlawyer'),
        'access_token' => env('BAZAAR_ACCESS_TOKEN'),
        'plan_product_ids' => $bazaarPlanProductIds,
        'product_plan_ids' => $bazaarProductPlanIds,
    ],

];
