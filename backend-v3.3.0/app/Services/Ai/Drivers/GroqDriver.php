<?php

namespace App\Services\Ai\Drivers;

class GroqDriver extends OpenAiDriver
{
    public function __construct(
        string $apiKey,
        string $model = 'llama-3.3-70b-versatile',
        float $temperature = 0.7,
        ?string $baseUrl = 'https://api.groq.com/openai/v1'
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model ?: 'llama-3.3-70b-versatile',
            temperature: $temperature,
            baseUrl: $baseUrl ?: 'https://api.groq.com/openai/v1'
        );
    }

    public function getCuratedModels(): array
    {
        return [
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile (Recommended - Lightning fast sub-second inference)',
            'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant (Lightweight & instantaneous)',
            'deepseek-r1-distill-llama-70b' => 'DeepSeek R1 Distill Llama 70B (Fast reasoning on Groq LPU)',
            'mixtral-8x7b-32768' => 'Mixtral 8x7B (32k context MoE)',
        ];
    }
}
