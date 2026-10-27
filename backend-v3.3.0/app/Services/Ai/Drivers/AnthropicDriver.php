<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\BaseAiDriver;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicDriver extends BaseAiDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'claude-3-5-haiku-latest',
        protected float $temperature = 0.7
    ) {}

    public function getCuratedModels(): array
    {
        return [
            'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku (Recommended - Fastest & Most Economical)',
            'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet (State-of-the-Art Copywriting Intelligence)',
            'claude-3-opus-latest' => 'Claude 3 Opus (Deep Reasoning)',
        ];
    }

    public function generateProductDescription(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Anthropic Claude API Key is missing. Please configure it in AI Settings.');
        }

        $title = trim($params['title'] ?? '');
        if (empty($title)) {
            throw new Exception('Product title is required to generate description.');
        }

        $prompt = $this->buildProductPrompt($params);
        $model = ! empty($this->model) ? $this->model : 'claude-3-5-haiku-latest';

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 2048,
                'system' => 'You are an expert e-commerce copywriter. Output strictly in valid JSON format.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $this->temperature,
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('Anthropic API Error: '.json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Anthropic API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['content'][0]['text'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('Anthropic Claude API returned an empty response.');
        }

        return $this->parseAndSanitizeResponse($generatedText);
    }

    public function generateBlogContent(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Anthropic Claude API Key is missing. Please configure it in AI Settings.');
        }

        $topic = trim($params['topic'] ?? ($params['title'] ?? ''));
        if (empty($topic)) {
            throw new Exception('Blog topic or title is required to generate article content.');
        }

        $prompt = $this->buildBlogPrompt($params);
        $model = ! empty($this->model) ? $this->model : 'claude-3-5-haiku-latest';

        $response = Http::timeout(45)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 4096,
                'system' => 'You are a senior content strategist and SEO specialist. Output strictly in valid JSON format.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $this->temperature,
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('Anthropic API Error: '.json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Anthropic API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['content'][0]['text'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('Anthropic Claude API returned an empty response.');
        }

        return $this->parseAndSanitizeBlogResponse($generatedText);
    }

    public function translateStructured(array $fields, string $sourceLocale, array $targetLocales, array $languageNames = [], array $fieldLimits = []): array
    {
        if ($this->apiKey === '') {
            throw new Exception('Anthropic API key is missing.');
        }

        $response = Http::timeout(35)
            ->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->translationMaxOutputTokens($fields, $targetLocales, $fieldLimits),
                'temperature' => 0.2,
                'system' => 'Return valid JSON only.',
                'messages' => [['role' => 'user', 'content' => $this->buildTranslationPrompt($fields, $sourceLocale, $targetLocales, $languageNames, $fieldLimits)]],
            ]);
        if (! $response->successful()) {
            Log::error('Anthropic translation failed', ['status' => $response->status(), 'body' => $response->body()]);
            $errBody = $response->json();
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Anthropic API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        return $this->parseTranslationResponse(
            (string) $response->json('content.0.text'),
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
        $model = ! empty($this->model) ? $this->model : 'claude-3-5-haiku-latest';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 30,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Respond strictly with JSON: {"status":"connected"}'],
                    ],
                    'temperature' => 0.1,
                ]);

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Successfully connected to Anthropic Claude ({$model}).",
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
