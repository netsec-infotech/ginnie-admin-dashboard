<?php

namespace App\Traits;

trait HasSeoMetadata
{
    public function getSeoTitleAttribute(): ?string
    {
        $stored = $this->metadataValue('seo_title');
        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        return $this->seoFallbackTitle();
    }

    public function getSeoKeywordsAttribute(): array
    {
        $stored = $this->metadataValue('seo_keywords');

        if (is_array($stored)) {
            $stored = array_values(array_filter(array_map('trim', $stored), fn ($v) => $v !== ''));
            if (! empty($stored)) {
                return $stored;
            }
        }

        if (is_string($stored) && trim($stored) !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $stored)), fn ($v) => $v !== ''));
            if (! empty($parts)) {
                return $parts;
            }
        }

        return $this->seoFallbackKeywords();
    }

    public function getSeoDescriptionAttribute(): ?string
    {
        $stored = $this->metadataValue('seo_description');
        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        return $this->seoFallbackDescription();
    }

    /**
     * Merge resolved (never-empty) SEO fields into the raw metadata payload.
     * Resources should use this when exposing metadata to API consumers.
     */
    public function metadataWithSeo(): array
    {
        $base = is_array($this->metadata) ? $this->metadata : [];

        return array_merge($base, [
            'seo_title' => $this->seo_title,
            'seo_keywords' => $this->seo_keywords,
            'seo_description' => $this->seo_description,
        ]);
    }

    protected function metadataValue(string $key): mixed
    {
        $meta = $this->metadata;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        return is_array($meta) ? ($meta[$key] ?? null) : null;
    }

    protected function seoFallbackTitle(): ?string
    {
        return $this->title ?? $this->name ?? null;
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->short_description ?? $this->description ?? $this->seoFallbackTitle();
    }

    protected function seoFallbackKeywords(): array
    {
        return $this->seoKeywordsFromTitle();
    }

    protected function seoKeywordsFromTitle(): array
    {
        $source = $this->seoFallbackTitle() ?? '';
        $words = preg_split('/[\s,;\/\|\-]+/u', $source) ?: [];
        $words = array_map('trim', $words);
        $words = array_filter($words, fn ($w) => mb_strlen($w) > 2);
        $words = array_values(array_unique($words));

        return array_slice($words, 0, 10);
    }
}
