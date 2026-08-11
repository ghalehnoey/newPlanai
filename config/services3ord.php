<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
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

    // IranGPU - سرویس اصلی برای مدل‌های پیشرفته (GPT-4.1, Gemini, Claude, etc.)
    'irangpu' => [
        'key' => env('IRANGPU_API_KEY'),
        'base_url' => env('IRANGPU_BASE_URL', 'https://services.irangpu.com'),
        'models' => [
            'gpt4' => 'openai/gpt-4.1-2025-04-14',     // پشتیبانی از تصویر ✅
            'gemini' => 'google/gemini-2.5-flash',      // پشتیبانی از تصویر ✅
            'claude' => 'anthropic/claude-4.5-haiku-20251001',
            'deepseek' => 'deepseek/deepseek-v3.2-20251201',
        ],
    ],

    // GapGPT - سرویس جایگزین برای مدل‌های پایه
    'gapgpt' => [
        'key' => env('GAPGPT_API_KEY'),
        'base_url' => env('GAPGPT_BASE_URL', 'https://api.gapgpt.app/v1'),
        'models' => [
            'qwen' => 'gapgpt-qwen-3.5',
            'claude' => 'claude-sonnet-4-6',
        ],
    ],

    // DeepSeek - مدل اقتصادی و قدرتمند
    'deepseek' => [
        'key' => env('DEEPSEEK_KEY'),
        'model' => 'deepseek-chat',
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
    ],

    // OpenAI (در صورت نیاز مستقیم)
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => 'gpt-4.1',
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    // Claude (Anthropic) - در صورت نیاز مستقیم
    'claude' => [
        'key' => env('CLAUDE_API_KEY'),
        'model' => 'claude-sonnet-4-5',
        'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
    ],

    // Grok (xAI)
    'grok' => [
        'key' => env('GROK_KEY'),
        'model' => 'grok-3',
        'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
    ],

    // Qwen (Alibaba) - در صورت نیاز مستقیم
    'qwen' => [
        'key' => env('QWEN_API_KEY'),
        'model' => 'qwen-turbo',
        'base_url' => env('QWEN_BASE_URL', 'https://dashscope.aliyuncs.com/compatible-mode/v1'),
    ],

    // Gemini (Google) - در صورت نیاز مستقیم
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => 'gemini-2.0-flash-exp',
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

];