<?php

namespace App\Services\Ai\Drivers;

class DeepSeekDriver extends OpenAiDriver
{
    public function __construct(
        string $apiKey,
        string $model = 'deepseek-v4-pro',
        float $temperature = 0.7,
        ?string $baseUrl = 'https://api.deepseek.com/v1'
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model ?: 'deepseek-v4-pro',
            temperature: $temperature,
            baseUrl: $baseUrl ?: 'https://api.deepseek.com/v1'
        );
    }

    public function getCuratedModels(): array
    {
        return [
            'deepseek-v4-pro' => 'DeepSeek-V4 Pro (Recommended - Flagship Quality & Low Cost)',
            'deepseek-v4-flash' => 'DeepSeek-V4 Flash (High Speed Inference)',
        ];
    }
}
