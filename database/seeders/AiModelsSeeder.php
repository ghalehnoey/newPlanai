<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiModel;

class AiModelsSeeder extends Seeder
{
    public function run()
    {
        $models = [
            [
                'key' => 'qwen',
                'name' => 'دستیار 1',
                'title' => 'Qwen 3.5',
                'provider' => 'gapgpt',
                'endpoint' => 'https://api.gapgpt.app/v1/chat/completions',
                'model_mapping' => 'gapgpt-qwen-3.5',
                'supports_files' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'gemini',
                'name' => 'دستیار بینا',
                'title' => 'GPT-4.1 (IranGPU)',
                'provider' => 'irangpu',
                'endpoint' => config('services.irangpu.base_url') . '/v1/chat/completions',
                'model_mapping' => 'openai/gpt-4.1-2025-04-14',
                'supports_files' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'gemini_flash',
                'name' => 'دستیار سریع',
                'title' => 'Gemini 2.5 Flash',
                'provider' => 'gapgpt',
                'endpoint' => 'https://api.gapgpt.app/v1/chat/completions',
                'model_mapping' => 'gemini-2.5-flash',
                'supports_files' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'claude',
                'name' => 'دستیار خلاق',
                'title' => 'Claude Sonnet',
                'provider' => 'gapgpt',
                'endpoint' => 'https://api.gapgpt.app/v1/chat/completions',
                'model_mapping' => 'claude-sonnet-4-6',
                'supports_files' => false,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'deepseek',
                'name' => 'دستیار هوشیار',
                'title' => 'DeepSeek Chat',
                'provider' => 'deepseek',
                'endpoint' => config('services.deepseek.base_url', 'https://api.deepseek.com') . '/v1/chat/completions',
                'model_mapping' => 'deepseek-chat',
                'supports_files' => false,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($models as $model) {
            AiModel::updateOrCreate(
                ['key' => $model['key']],
                $model
            );
        }
    }
}