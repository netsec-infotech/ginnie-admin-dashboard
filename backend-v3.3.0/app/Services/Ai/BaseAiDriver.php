<?php

namespace App\Services\Ai;

use App\Contracts\AiDriverInterface;
use Exception;

abstract class BaseAiDriver implements AiDriverInterface
{
    protected function translationMaxOutputTokens(array $fields, array $targetLocales, array $fieldLimits = []): int
    {
        $estimatedCharacters = collect(array_keys($fields))->sum(
            fn (string $field): int => (int) ($fieldLimits[$field] ?? 255),
        ) * max(count($targetLocales), 1);

        return max(2048, min(16384, (int) ceil($estimatedCharacters / 2) + 1024));
    }

    protected function buildTranslationPrompt(array $fields, string $sourceLocale, array $targetLocales, array $languageNames, array $fieldLimits = []): string
    {
        $targets = collect($targetLocales)->mapWithKeys(
            fn (string $locale): array => [$locale => $languageNames[$locale] ?? $locale],
        )->all();

        return 'Translate the following e-commerce catalog fields from English ('.$sourceLocale.') into every target language. '
            .'Preserve brand names and technical terms. Return only valid JSON keyed by locale, with exactly the same field keys. '
            .'Do not exceed these character limits per field: '.json_encode($fieldLimits, JSON_THROW_ON_ERROR).'. '
            .'Targets: '.json_encode($targets, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).'. '
            .'Fields: '.json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array<string, string>> */
    protected function parseTranslationResponse(string $rawText, array $targetLocales, array $fieldKeys, array $fieldLimits = []): array
    {
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $decoded = json_decode(trim((string) $cleaned), true);
        if (! is_array($decoded)) {
            throw new Exception('The AI provider returned invalid translation data.');
        }

        $result = [];
        foreach ($targetLocales as $locale) {
            if (! is_array($decoded[$locale] ?? null)) {
                continue;
            }
            foreach ($fieldKeys as $field) {
                $value = $decoded[$locale][$field] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $value = trim($value);
                    $limit = (int) ($fieldLimits[$field] ?? 255);
                    if ($limit < 1 || mb_strlen($value) > $limit) {
                        throw new Exception('The AI provider returned an overlong translation value.');
                    }

                    $result[$locale][$field] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Build the structured prompt for product description generation.
     *
     * @param array{title: string, category_name?: string, tone?: string, length?: string, keywords?: string, instructions?: string, language?: string} $params
     */
    protected function buildProductPrompt(array $params): string
    {
        $title = trim($params['title'] ?? '');
        $categoryName = trim($params['category_name'] ?? '');
        $tone = trim($params['tone'] ?? 'persuasive');
        $length = trim($params['length'] ?? 'standard');
        $keywords = trim($params['keywords'] ?? '');
        $instructions = trim($params['instructions'] ?? '');
        $language = trim($params['language'] ?? 'English');

        $toneGuidelines = match ($tone) {
            'persuasive' => 'high-converting, enthusiastic, benefit-focused, and emotionally appealing',
            'luxury' => 'sophisticated, elegant, prestigious, premium, and refined',
            'professional' => 'clear, trustworthy, authoritative, polished, and informative',
            'casual' => 'warm, friendly, approachable, conversational, and relatable',
            'technical' => 'specification-focused, precise, engineering-driven, and detailed',
            'punchy' => 'concise, direct, punchy, bullet-oriented, and high-impact',
            'seo' => 'search-intent optimized, rich in product keywords and commercial search terms',
            default => 'engaging, clear, customer-centric, and attractive',
        };

        $lengthGuidelines = match ($length) {
            'concise' => 'concise and punchy (150-250 words total)',
            'comprehensive' => 'extensive and in-depth (400-600+ words total) with thorough specs and usage advice',
            default => 'standard e-commerce length (250-400 words total)',
        };

        $prompt = "You are a world-class e-commerce copywriter and merchandising expert. Write an attractive, high-converting product page copy in {$language}.\n\n";
        $prompt .= "Product Name / Title: {$title}\n";
        if (! empty($categoryName)) {
            $prompt .= "Category: {$categoryName}\n";
        }
        $prompt .= "Tone of Voice: {$toneGuidelines}\n";
        $prompt .= "Copy Depth: {$lengthGuidelines}\n";
        if (! empty($keywords)) {
            $prompt .= "Key Highlights, Features & Materials: {$keywords}\n";
        }
        if (! empty($instructions)) {
            $prompt .= "Special Instructions & Guidelines: {$instructions}\n";
        }

        $prompt .= "\nFormatting & Output Rules:\n";
        $prompt .= "1. \"title\": An optimized, catchy product title/headline.\n";
        $prompt .= "2. \"short_description\": A concise 1-2 sentence compelling product summary or hook (120-180 chars) in plain text.\n";
        $prompt .= "3. \"description\": A structured e-commerce product description formatted with clean, semantic HTML containing:\n";
        $prompt .= "   - An engaging Overview paragraph (<p>)\n";
        $prompt .= "   - Key Features & Benefits (<h3>Key Features</h3> followed by <ul><li><strong>Benefit:</strong> explanation</li></ul>)\n";
        $prompt .= "   - Specifications, Care or Usage guidelines if applicable (<h3>Specifications</h3> or <h3>Usage & Care</h3>)\n";
        $prompt .= "   - A reassuring Why Choose Us / Quality Guarantee highlight (<p><strong>Why Choose Us:</strong> ...</p>)\n";
        $prompt .= "   - DO NOT include <html>, <head>, <body>, or markdown backticks in HTML.\n";
        $prompt .= "4. \"tags\": An array of 4 to 8 relevant e-commerce search tags and keywords.\n";
        $prompt .= "5. Complete SEO metadata:\n";
        $prompt .= "   - \"seo_title\": Search-optimized meta title (max 60 chars)\n";
        $prompt .= "   - \"seo_keywords\": An array of 5 to 10 targeted search keywords\n";
        $prompt .= "   - \"seo_description\": High-CTR search meta description (120-155 chars)\n\n";

        $prompt .= "Please output strictly in valid JSON format with no markdown code fence:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"Optimized Product Name\",\n";
        $prompt .= "  \"short_description\": \"Compelling 1-2 sentence hook summary\",\n";
        $prompt .= "  \"description\": \"<p>Overview...</p><h3>Key Features</h3><ul><li><strong>Highlight:</strong> Details</li></ul><p><strong>Why Choose Us:</strong> ...</p>\",\n";
        $prompt .= "  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\"],\n";
        $prompt .= "  \"seo_title\": \"SEO Title | Brand\",\n";
        $prompt .= "  \"seo_keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],\n";
        $prompt .= "  \"seo_description\": \"High-converting search engine description...\"\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Parse and sanitize raw generated text into structured product content and SEO data.
     *
     * @return array{title: string, short_description: string, description: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    protected function parseAndSanitizeProductResponse(string $rawText, string $fallbackTitle = ''): array
    {
        $title = $fallbackTitle;
        $shortDesc = '';
        $description = '';
        $tags = [];
        $seoTitle = '';
        $seoKeywords = [];
        $seoDesc = '';

        // 1. Clean codeblock backticks if present
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $cleaned = trim((string) $cleaned);

        // 2. Extract JSON object substring if wrapped
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
            $jsonStr = $matches[0];
            $parsed = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try removing unescaped control characters except newlines
                $jsonStrCleaned = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $jsonStr);
                $parsed = json_decode((string) $jsonStrCleaned, true);
            }

            if (is_array($parsed)) {
                $title = (string) ($parsed['title'] ?? $fallbackTitle);
                $shortDesc = (string) ($parsed['short_description'] ?? '');
                $description = (string) ($parsed['description'] ?? '');
                $rawTags = $parsed['tags'] ?? null;
                $tags = is_array($rawTags)
                    ? array_values(array_filter(array_map('strval', $rawTags)))
                    : (is_string($rawTags) ? array_map('trim', explode(',', $rawTags)) : []);
                $seoTitle = (string) ($parsed['seo_title'] ?? $title);
                $rawSeoKw = $parsed['seo_keywords'] ?? null;
                $seoKeywords = is_array($rawSeoKw)
                    ? array_values(array_filter(array_map('strval', $rawSeoKw)))
                    : (is_string($rawSeoKw) ? array_map('trim', explode(',', $rawSeoKw)) : $tags);
                $seoDesc = (string) ($parsed['seo_description'] ?? $shortDesc);
            }
        }

        if ($shortDesc === '' && preg_match('/"short_description"\s*:\s*"([^"]*)/u', $cleaned, $matches)) {
            $shortDesc = (string) ($matches[1] ?? '');
        }

        // If description is empty, use cleaned raw text
        if (empty($description)) {
            $description = $cleaned;
        }

        return [
            'title' => trim($title),
            'short_description' => trim($shortDesc),
            'description' => trim($description),
            'tags' => array_values(array_filter($tags)),
            'seo_title' => trim($seoTitle ?: $title),
            'seo_keywords' => array_values(array_filter($seoKeywords)),
            'seo_description' => trim($seoDesc ?: $shortDesc),
        ];
    }

    /**
     * Alias for backward-compatibility with drivers.
     *
     * @return array{title: string, short_description: string, description: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    protected function parseAndSanitizeResponse(string $rawText): array
    {
        return $this->parseAndSanitizeProductResponse($rawText);
    }

    /**
     * Build structured prompt for full blog post and SEO generation.
     *
     * @param array{topic: string, title?: string, category_name?: string, tone?: string, length?: string, keywords?: string, instructions?: string, language?: string} $params
     */
    protected function buildBlogPrompt(array $params): string
    {
        $topic = trim($params['topic'] ?? ($params['title'] ?? ''));
        $categoryName = trim($params['category_name'] ?? '');
        $tone = trim($params['tone'] ?? 'informative');
        $length = trim($params['length'] ?? 'standard');
        $keywords = trim($params['keywords'] ?? '');
        $instructions = trim($params['instructions'] ?? '');
        $language = trim($params['language'] ?? 'English');

        $lengthGuidelines = match ($length) {
            'short' => 'around 300 to 500 words, focused and concise',
            'comprehensive' => 'in-depth around 1000 to 1500+ words, deep dive with detailed subheadings and takeaways',
            default => 'standard depth around 600 to 900 words with thorough sections',
        };

        $toneGuidelines = match ($tone) {
            'engaging' => 'lively, conversational, relatable, and storytelling-driven',
            'professional' => 'authoritative, analytical, objective, and insightful',
            'storytelling' => 'narrative, emotional, journey-based, and captivating',
            'seo_guide' => 'step-by-step, actionable, keyword-rich, and structured with clear takeaways',
            default => 'informative, clear, balanced, and reader-friendly',
        };

        $prompt = "You are a senior content strategist and SEO specialist for an e-commerce & lifestyle platform. Write a comprehensive, high-quality, and SEO-optimized blog article in {$language}.\n\n";
        $prompt .= "Topic/Working Title: {$topic}\n";
        if (! empty($categoryName)) {
            $prompt .= "Category Context: {$categoryName}\n";
        }
        $prompt .= "Tone: {$toneGuidelines}\n";
        $prompt .= "Length: {$lengthGuidelines}\n";
        if (! empty($keywords)) {
            $prompt .= "Target Keywords & Topics to cover: {$keywords}\n";
        }
        if (! empty($instructions)) {
            $prompt .= "Special Instructions / Brand Notes: {$instructions}\n";
        }

        $prompt .= "\nFormatting and Content Rules:\n";
        $prompt .= "1. Format the \"content\" using semantic clean HTML (e.g. <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <blockquote>). DO NOT include <html>, <head>, <body>, or markdown backticks.\n";
        $prompt .= "2. Write an engaging introduction, structured main body sections with descriptive subheadings (<h2>, <h3>), practical bullet points, and an inspiring conclusion.\n";
        $prompt .= "3. Provide a short description (excerpt) of 1-2 compelling sentences (120-160 characters).\n";
        $prompt .= "4. Suggest 3 to 6 relevant single/multi-word tags.\n";
        $prompt .= "5. Provide complete SEO metadata: an attention-grabbing SEO Title (max 60 chars), a focused array of SEO Keywords (3-8 keywords), and a search-intent optimized SEO Meta Description (120-160 chars).\n\n";

        $prompt .= "Please output strictly in valid JSON format with the following schema and no markdown code fence:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"Polished, captivating final blog title\",\n";
        $prompt .= "  \"short_description\": \"1-2 sentence compelling excerpt for cards and summaries\",\n";
        $prompt .= "  \"content\": \"<h2>Introduction</h2><p>...</p><h2>Key Insights</h2><ul><li>...</li></ul><h2>Conclusion</h2><p>...</p>\",\n";
        $prompt .= "  \"tags\": [\"tag1\", \"tag2\", \"tag3\"],\n";
        $prompt .= "  \"seo_title\": \"SEO Optimized Title | Platform\",\n";
        $prompt .= "  \"seo_keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],\n";
        $prompt .= "  \"seo_description\": \"Compelling search engine description with high CTR...\"\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Parse and sanitize raw generated text into structured blog and SEO data.
     *
     * @return array{title: string, short_description: string, content: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    protected function parseAndSanitizeBlogResponse(string $rawText): array
    {
        $title = '';
        $shortDesc = '';
        $content = '';
        $tags = [];
        $seoTitle = '';
        $seoKeywords = [];
        $seoDesc = '';

        // 1. Clean codeblock backticks if present
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $cleaned = trim((string) $cleaned);

        // 2. Extract JSON object substring
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
            $jsonStr = $matches[0];
            $parsed = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Remove raw unescaped control characters except newlines
                $jsonStrCleaned = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $jsonStr);
                $parsed = json_decode((string) $jsonStrCleaned, true);
            }

            if (is_array($parsed)) {
                $title = (string) ($parsed['title'] ?? '');
                $shortDesc = (string) ($parsed['short_description'] ?? '');
                $content = (string) ($parsed['content'] ?? '');
                $tags = is_array($parsed['tags'] ?? null) ? array_values(array_filter(array_map('strval', $parsed['tags']))) : [];
                $seoTitle = (string) ($parsed['seo_title'] ?? $title);
                $seoKeywords = is_array($parsed['seo_keywords'] ?? null) ? array_values(array_filter(array_map('strval', $parsed['seo_keywords']))) : $tags;
                $seoDesc = (string) ($parsed['seo_description'] ?? $shortDesc);
            }
        }

        // Fallback if content was not decoded from JSON
        if (empty($content)) {
            $content = $cleaned;
        }

        return [
            'title' => trim($title),
            'short_description' => trim($shortDesc),
            'content' => trim($content),
            'tags' => $tags,
            'seo_title' => trim($seoTitle),
            'seo_keywords' => $seoKeywords,
            'seo_description' => trim($seoDesc),
        ];
    }
}
