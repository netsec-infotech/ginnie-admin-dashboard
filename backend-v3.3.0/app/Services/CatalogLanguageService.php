<?php

namespace App\Services;

use App\Enums\LanguageSurfaceEnum;
use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CatalogLanguageService
{
    public const CACHE_KEY = 'catalog_languages.active';

    /**
     * Get all active unique languages across all 5 system surfaces,
     * deterministically prioritizing client-facing metadata and ensuring English is always first.
     *
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached instanceof Collection) {
                return $cached;
            }

            if (! Schema::hasTable('languages')) {
                return collect([$this->fallbackEnglish()]);
            }

            $languages = Language::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            return collect([$this->fallbackEnglish()]);
        }

        if ($languages->isEmpty()) {
            return collect([$this->fallbackEnglish()]);
        }

        $surfaceRanks = array_flip([
            LanguageSurfaceEnum::WEB->value,
            LanguageSurfaceEnum::CUSTOMER_APP->value,
            LanguageSurfaceEnum::SELLER_APP->value,
            LanguageSurfaceEnum::RIDER_APP->value,
            LanguageSurfaceEnum::PANEL->value,
        ]);

        $grouped = $languages->groupBy(
            fn (Language $language): string => app(LanguageService::class)->normalizeCode($language->code),
        );

        $uniqueList = $grouped->map(function (Collection $variants) use ($surfaceRanks): Language {
            $language = $variants
                ->sort(fn (Language $left, Language $right): int => [
                    $surfaceRanks[$left->surface] ?? PHP_INT_MAX,
                    (int) $left->sort_order,
                    (int) $left->id,
                ] <=> [
                    $surfaceRanks[$right->surface] ?? PHP_INT_MAX,
                    (int) $right->sort_order,
                    (int) $right->id,
                ])
                ->first();

            $language->code = app(LanguageService::class)->normalizeCode($language->code);

            return $language;
        })->values();

        $hasEnglish = $uniqueList->first(fn (Language $language): bool => $language->code === 'en');
        if (! $hasEnglish) {
            $uniqueList->prepend($this->fallbackEnglish());
        } else {
            $uniqueList = $uniqueList
                ->sortBy(fn (Language $language): int => $language->code === 'en' ? 0 : 1)
                ->values();
        }

        try {
            Cache::forever(self::CACHE_KEY, $uniqueList);
        } catch (\Throwable) {
        }

        return $uniqueList;
    }

    /**
     * The fixed canonical default and fallback locale for the entire catalog.
     */
    public function defaultLocale(): string
    {
        return 'en';
    }

    /**
     * Format active catalog languages payload for API responses.
     *
     * @return array{default: string, languages: array<int, array{code: string, name: string, native_name: string, direction: string}>}
     */
    public function toArray(): array
    {
        return [
            'default' => $this->defaultLocale(),
            'languages' => $this->active()->map(fn (Language $language): array => [
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'direction' => $language->direction,
            ])->values()->all(),
        ];
    }

    /**
     * Check if a given locale code is currently active for catalog translation.
     */
    public function isValidCatalogLocale(?string $locale): bool
    {
        if (empty($locale)) {
            return false;
        }

        $normalized = app(LanguageService::class)->normalizeCode($locale);

        return $this->active()->contains(
            fn (Language $language): bool => app(LanguageService::class)->normalizeCode($language->code) === $normalized,
        );
    }

    /**
     * Invalidate cached active catalog languages.
     */
    public function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
            Cache::forget('client_languages.catalog_active');
        } catch (\Throwable) {
        }
    }

    protected function fallbackEnglish(): Language
    {
        return new Language([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'country_code' => 'gb',
            'direction' => 'ltr',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }
}
