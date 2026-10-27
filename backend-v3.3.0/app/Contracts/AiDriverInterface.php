<?php

namespace App\Contracts;

interface AiDriverInterface
{
    /**
     * Generate product description, optimized title, tags, and SEO metadata.
     *
     * @param array{title: string, category_name?: string, tone?: string, length?: string, keywords?: string, instructions?: string, language?: string} $params
     * @return array{title: string, short_description: string, description: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    public function generateProductDescription(array $params): array;

    /**
     * Generate full blog post content with SEO metadata and tag suggestions.
     *
     * @param array{topic: string, title?: string, category_name?: string, tone?: string, length?: string, keywords?: string, instructions?: string, language?: string} $params
     * @return array{title: string, short_description: string, content: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    public function generateBlogContent(array $params): array;

    /**
     * @param  array<string, string>  $fields
     * @param  array<int, string>  $targetLocales
     * @param  array<string, string>  $languageNames
     * @param  array<string, int>  $fieldLimits
     * @return array<string, array<string, string>>
     */
    public function translateStructured(array $fields, string $sourceLocale, array $targetLocales, array $languageNames = [], array $fieldLimits = []): array;

    /**
     * Test API connection with the configured credentials and model.
     *
     * @return array{success: bool, message: string, latency_ms?: int}
     */
    public function testConnection(): array;

    /**
     * Get curated model choices for this provider.
     *
     * @return array<string, string>
     */
    public function getCuratedModels(): array;
}
