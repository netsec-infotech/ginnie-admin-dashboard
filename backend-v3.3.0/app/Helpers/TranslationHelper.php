<?php

namespace App\Helpers;

use App\Services\CatalogLanguageService;
use Illuminate\Support\Collection;

class TranslationHelper
{
    /**
     * Get unique active languages configured for catalog translation.
     */
    public static function getClientLanguages(): Collection
    {
        return app(CatalogLanguageService::class)->active();
    }

    /**
     * Get the default canonical catalog locale code.
     */
    public static function getDefaultLocale(): string
    {
        return app(CatalogLanguageService::class)->defaultLocale();
    }

    /**
     * Normalize translatable attribute from request payload (handling strings, arrays, and JSON strings).
     *
     * @param mixed $value
     * @param string|null $defaultLocale
     * @return array|null
     */
    public static function normalize(mixed $value, ?string $defaultLocale = null): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $defaultLocale = $defaultLocale ?: self::getDefaultLocale();

        if (is_array($value)) {
            $filtered = [];
            foreach ($value as $locale => $val) {
                if ($val !== null && trim((string) $val) !== '') {
                    $filtered[$locale] = trim((string) $val);
                }
            }

            return ! empty($filtered) ? $filtered : null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return self::normalize($decoded, $defaultLocale);
                }
            }

            return [$defaultLocale => $trimmed];
        }

        return null;
    }

    /**
     * Extract string for specific locale from translated array or JSON with fallback.
     */
    public static function resolveText(mixed $value, ?string $locale = null, ?string $fallbackLocale = null): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value)) {
            if (! str_starts_with(trim($value), '{')) {
                return $value;
            }
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return $value;
            }
            $value = $decoded;
        }

        if (is_array($value)) {
            $locale = $locale ?: app()->getLocale();
            $fallback = $fallbackLocale ?: self::getDefaultLocale();

            if (! empty($value[$locale])) {
                return (string) $value[$locale];
            }

            if (! empty($value[$fallback])) {
                return (string) $value[$fallback];
            }

        }

        return '';
    }
}
