<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\BaseAiDriver;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiDriver extends BaseAiDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gemini-3.8-flash',
        protected float $temperature = 0.7
    ) {}

    public function getCuratedModels(): array
    {
        return [
            'gemini-3.8-flash' => 'Gemini 3.8 Flash (Recommended - Fastest & Smartest)',
            'gemini-3.7-flash' => 'Gemini 3.7 Flash',
            'gemini-3.6-flash' => 'Gemini 3.6 Flash (High Efficiency)',
            'gemini-3.5-flash' => 'Gemini 3.5 Flash',
            'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite (Lowest Latency)',
            'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (Complex & Detailed Copy)',
            'gemini-flash-latest' => 'Gemini Flash (Always Latest)',
        ];
    }

    public function generateProductDescription(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new Exception(__('labels.gemini_key_missing') ?? 'Google Gemini API Key is missing. Please configure it in AI Settings.');
        }

        $title = trim($params['title'] ?? '');
        if (empty($title)) {
            throw new Exception('Product title is required to generate description.');
        }

        $prompt = $this->buildProductPrompt($params);
        $model = ! empty($this->model) ? $this->model : 'gemini-3.8-flash';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => $this->temperature,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ],
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('Gemini API Error: '.json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Google Gemini API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('Google Gemini API returned an empty response.');
        }

        return $this->parseAndSanitizeResponse($generatedText);
    }

    public function generateBlogContent(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new Exception(__('labels.gemini_key_missing') ?? 'Google Gemini API Key is missing. Please configure it in AI Settings.');
        }

        $topic = trim($params['topic'] ?? ($params['title'] ?? ''));
        if (empty($topic)) {
            throw new Exception('Blog topic or title is required to generate article content.');
        }

        $prompt = $this->buildBlogPrompt($params);
        $model = ! empty($this->model) ? $this->model : 'gemini-3.8-flash';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(45)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => $this->temperature,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 8192,
                ],
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('Gemini API Error: '.json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Google Gemini API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('Google Gemini API returned an empty response.');
        }

        return $this->parseAndSanitizeBlogResponse($generatedText);
    }

    public function translateStructured(array $fields, string $sourceLocale, array $targetLocales, array $languageNames = [], array $fieldLimits = []): array
    {
        if ($this->apiKey === '') {
            throw new Exception(__('labels.gemini_key_missing'));
        }

        $response = Http::timeout(35)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [['parts' => [['text' => $this->buildTranslationPrompt($fields, $sourceLocale, $targetLocales, $languageNames, $fieldLimits)]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2,
                    'maxOutputTokens' => $this->translationMaxOutputTokens($fields, $targetLocales, $fieldLimits),
                ],
            ],
        );
        if (! $response->successful()) {
            Log::error('Gemini translation failed', ['status' => $response->status(), 'body' => $response->body()]);
            $errBody = $response->json();
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Google Gemini API (Status '.$response->status().').';
            throw new Exception($errMsg);
        }

        return $this->parseTranslationResponse(
            (string) $response->json('candidates.0.content.parts.0.text'),
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
        $model = ! empty($this->model) ? $this->model : 'gemini-3.8-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => 'Respond strictly with JSON: {"status":"connected"}']]],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'temperature' => 0.1,
                        'maxOutputTokens' => 50,
                    ],
                ]);

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Successfully connected to Google Gemini ({$model}).",
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
