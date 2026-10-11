<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiService
{
    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Generate product description using Gemini API
     *
     * @param array $params
     * @return array{short_description: string, description: string}
     * @throws Exception
     */
    public function generateProductDescription(array $params): array
    {
        $settingResource = $this->settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
        $settings = $settingResource ? ($settingResource->toArray(request())['value'] ?? []) : [];

        $enableAi = ! empty($settings['enableAiDescription']);
        if (! $enableAi) {
            throw new Exception(__('labels.ai_description_disabled'));
        }

        $apiKey = trim($settings['geminiApiKey'] ?? '');
        if (empty($apiKey)) {
            throw new Exception(__('labels.gemini_key_missing'));
        }

        $model = $settings['aiModel'] ?? 'gemini-3.6-flash';
        $title = trim($params['title'] ?? '');
        $categoryName = trim($params['category_name'] ?? '');
        $tone = trim($params['tone'] ?? 'professional');
        $keywords = trim($params['keywords'] ?? '');

        if (empty($title)) {
            throw new Exception('Product title is required to generate description.');
        }

        $prompt = "You are an expert e-commerce copywriter. Write an attractive, engaging, and high-converting product description for an online store product.\n\n";
        $prompt .= "Product Name: {$title}\n";
        if (! empty($categoryName)) {
            $prompt .= "Category: {$categoryName}\n";
        }
        if (! empty($keywords)) {
            $prompt .= "Key Features/Highlights: {$keywords}\n";
        }
        $prompt .= "Tone: {$tone}\n\n";
        $prompt .= "Please provide your output in valid JSON format with exactly two keys:\n";
        $prompt .= "1. \"short_description\": A concise 1-2 sentence summary in plain text.\n";
        $prompt .= "2. \"description\": A detailed plain text product description formatted with clear paragraphs and clean bullet points (using bullet character • or hyphen -). DO NOT use any HTML tags like <p>, <ul>, <li>, <b>, <br>, or markdown formatting. Do not include markdown code block backticks like ```json.\n";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ]
            ]);

        if (! $response->successful()) {
            $errBody = $response->json();
            Log::error('Gemini API Error: ' . json_encode($errBody));
            $errMsg = $errBody['error']['message'] ?? 'Failed to communicate with Google Gemini API.';
            throw new Exception($errMsg);
        }

        $body = $response->json();
        $generatedText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($generatedText)) {
            throw new Exception('Gemini API returned an empty response.');
        }

        return $this->parseAndSanitizeResponse($generatedText);
    }

    /**
     * Parse and sanitize generated text into clean short and full descriptions.
     */
    private function parseAndSanitizeResponse(string $rawText): array
    {
        $shortDesc = '';
        $fullDesc = '';

        // 1. Clean codeblock backticks
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $cleaned = trim($cleaned);

        // 2. Extract JSON object substring if wrapped
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
            $jsonStr = $matches[0];
            $parsed = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try removing unescaped control chars
                $jsonStrCleaned = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $jsonStr);
                $parsed = json_decode($jsonStrCleaned, true);
            }

            if (is_array($parsed)) {
                $shortDesc = $parsed['short_description'] ?? '';
                $fullDesc = $parsed['description'] ?? '';
            } else {
                // Regex fallback for keys
                if (preg_match('/"short_description"\s*:\s*"(.*?)"\s*,\s*"description"/s', $jsonStr, $mShort)) {
                    $shortDesc = stripcslashes($mShort[1]);
                }
                if (preg_match('/"description"\s*:\s*"(.*?)"\s*\}/s', $jsonStr, $mFull)) {
                    $fullDesc = stripcslashes($mFull[1]);
                }
            }
        }

        // If JSON extraction did not find description, handle raw text safely
        if (empty($shortDesc) && empty($fullDesc)) {
            // Strip JSON noise if present in raw text
            $cleanRaw = preg_replace('/^\{\s*"short_description"\s*:\s*"/i', '', $cleaned);
            $cleanRaw = preg_replace('/"\s*,\s*"description"\s*:\s*"/i', "\n\n", $cleanRaw);
            $cleanRaw = preg_replace('/"\s*\}$/i', '', $cleanRaw);

            $parts = preg_split('/\n\s*\n/', trim($cleanRaw), 2);
            $shortDesc = $parts[0] ?? $cleanRaw;
            $fullDesc = $parts[1] ?? $cleanRaw;
        }

        return [
            'short_description' => $this->sanitizeShortDescription($shortDesc),
            'description' => $this->sanitizeFullDescription($fullDesc),
        ];
    }

    /**
     * Sanitize short description into clean, plain text without HTML or JSON noise.
     */
    private function sanitizeShortDescription(string $text): string
    {
        // Remove JSON key noise if leftover
        $text = preg_replace('/^\{\s*"short_description"\s*:\s*"?/i', '', $text);
        $text = preg_replace('/"?\s*,\s*"description"[\s\S]*/i', '', $text);

        // Strip HTML and decode entities
        $text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Strip markdown backticks, bold, italic
        $text = preg_replace('/[\*\_`#]/', '', $text);

        // Trim quotes and whitespace
        return trim($text, " \t\n\r\0\x0B\"'");
    }

    /**
     * Sanitize full description into clean, well-formatted HTML.
     */
    private function sanitizeFullDescription(string $text): string
    {
        // Remove JSON key noise if leftover
        $text = preg_replace('/^[\{\s]*"description"\s*:\s*"?/i', '', $text);
        $text = preg_replace('/"?\s*\}$/i', '', $text);

        $text = trim($text);

        // Check if text already contains HTML tags like <p>, <ul>, <li>, <strong>, <br>
        if (preg_match('/<[a-z][\s\S]*>/i', $text)) {
            // Clean up unclosed tags or backticks if any
            $text = preg_replace('/^```(?:html)?\s*|\s*```$/i', '', $text);
            return trim($text);
        }

        // Convert raw markdown / text to HTML
        // Bold: **text** -> <strong>text</strong>
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
        // Italic: *text* -> <em>text</em>
        $text = preg_replace('/\*([^\*]+)\*/s', '<em>$1</em>', $text);

        // Bullet lists: - item -> <li>item</li>
        if (preg_match('/^\s*[\-\*]\s+/m', $text)) {
            $lines = explode("\n", $text);
            $inList = false;
            $htmlLines = [];
            foreach ($lines as $line) {
                if (preg_match('/^\s*[\-\*]\s+(.*)/', $line, $m)) {
                    if (! $inList) {
                        $htmlLines[] = '<ul>';
                        $inList = true;
                    }
                    $htmlLines[] = '  <li>' . trim($m[1]) . '</li>';
                } else {
                    if ($inList) {
                        $htmlLines[] = '</ul>';
                        $inList = false;
                    }
                    if (trim($line) !== '') {
                        $htmlLines[] = '<p>' . trim($line) . '</p>';
                    }
                }
            }
            if ($inList) {
                $htmlLines[] = '</ul>';
            }
            return implode("\n", $htmlLines);
        }

        // Paragraphs split by double newlines
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $htmlParagraphs = array_map(function ($p) {
            $p = trim($p);
            return $p !== '' ? '<p>' . nl2br($p) . '</p>' : '';
        }, $paragraphs);

        return implode("\n", array_filter($htmlParagraphs));
    }
}
