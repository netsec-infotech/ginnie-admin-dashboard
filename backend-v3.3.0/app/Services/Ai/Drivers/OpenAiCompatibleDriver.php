<?php

namespace App\Services\Ai\Drivers;

class OpenAiCompatibleDriver extends OpenAiDriver
{
    public function __construct(
        string $apiKey,
        string $model = 'custom-model',
        float $temperature = 0.7,
        ?string $baseUrl = 'https://openrouter.ai/api/v1'
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model ?: 'custom-model',
            temperature: $temperature,
            baseUrl: $baseUrl ?: 'https://openrouter.ai/api/v1'
        );
    }

    public function getCuratedModels(): array
    {
        return [];
    }
}
