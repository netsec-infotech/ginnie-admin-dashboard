<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\BaseAiDriver;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiDriver extends BaseAiDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gpt-4o-mini',
        protected float $temperature = 0.7,
        protected ?string $baseUrl = null
    ) {}

    public function getCuratedModels(): array
    {
        return [
            'gpt-4o-mini' => 'GPT-4o Mini (Recommended - Ultra fast & cost-effective)',
            'gpt-4o' => 'GPT-4o (Flagship omni model - highest quality)',
            'o3-mini' => 'o3-mini (High-speed reasoning model)',
            'gpt-4-turbo' => 'GPT-4 Turbo (High performance)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Legacy standard)',
        ];
    }

    protected function getEndpoint(): string
    {
        $base = ! empty($this->baseUrl) ? rtrim($this->baseUrl, '/') : 'https://api.openai.com/v1';

        return $base.'/chat/completions';
    }

    public function generateProductDescription(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API Key is missing. Please configure it in AI Settings.');
        }

        $title = trim($params['title'] ?? '');
        if (empty($title)) {
            throw new Exception('Product title is required to generate description.');
        }

        $prompt = $this->buildProductPrompt($params);
        $model = ! empty($this->model) ? $this->model : 'gpt-4o-mini';

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->getEndpoint(), [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert e-commerce copywriter. Output strictly in valid JSON format.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 2048,
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('OpenAI API Error: '.json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with OpenAI API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['choices'][0]['message']['content'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('OpenAI API returned an empty response.');
        }

        return $this->parseAndSanitizeResponse($generatedText);
    }

    public function generateBlogContent(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API Key is missing. Please configure it in AI Settings.');
        }

        $topic = trim($params['topic'] ?? ($params['title'] ?? ''));
        if (empty($topic)) {
            throw new Exception('Blog topic or title is required to generate article content.');
        }

        $prompt = $this->buildBlogPrompt($params);
        $model = ! empty($this->model) ? $this->model : 'gpt-4o-mini';

        $response = Http::timeout(45)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->getEndpoint(), [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a senior content strategist and SEO specialist. Output strictly in valid JSON format.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 4096,
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('OpenAI API Error: '.json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with OpenAI API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['choices'][0]['message']['content'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('OpenAI API returned an empty response.');
        }

        return $this->parseAndSanitizeBlogResponse($generatedText);
    }

    public function translateStructured(array $fields, string $sourceLocale, array $targetLocales, array $languageNames = [], array $fieldLimits = []): array
    {
        if ($this->apiKey === '') {
            throw new Exception('AI API key is missing.');
        }

        $response = Http::timeout(35)
            ->withToken($this->apiKey)
            ->post($this->getEndpoint(), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return valid JSON only.'],
                    ['role' => 'user', 'content' => $this->buildTranslationPrompt($fields, $sourceLocale, $targetLocales, $languageNames, $fieldLimits)],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => $this->translationMaxOutputTokens($fields, $targetLocales, $fieldLimits),
            ]);
        if (! $response->successful()) {
            Log::error('OpenAI-compatible translation failed', ['status' => $response->status(), 'body' => $response->body()]);
            $errBody = $response->json();
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with the AI provider (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        return $this->parseTranslationResponse(
            (string) $response->json('choices.0.message.content'),
            $targetLocales,
            array_keys($fields),
            $fieldLimits,
        );
    }

    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key is missing.'];
        }

        $startTime = microtime(true);
        $model = ! empty($this->model) ? $this->model : 'gpt-4o-mini';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->getEndpoint(), [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Respond strictly with JSON: {"status":"connected"}'],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 30,
                    'temperature' => 0.1,
                ]);

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Successfully connected to OpenAI ({$model}).",
                    'latency_ms' => $latency,
                ];
            }

            $errBody = $response->json();
            $errMsg = $errBody['error']['message'] ?? 'Connection test failed with HTTP status '.$response->status();

            return ['success' => false, 'message' => $errMsg, 'latency_ms' => $latency];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
