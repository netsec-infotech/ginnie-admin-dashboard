<?php

namespace App\Services;

use App\Enums\LanguageSurfaceEnum;
use App\Models\Language;
use App\Models\LanguageTranslation;
use App\Support\DatabaseSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;
use stdClass;

class ClientLanguageService
{
    public function languages(LanguageSurfaceEnum $surface): Collection
    {
        if (! $this->languagesAvailable()) {
            return collect();
        }

        $load = fn () => Language::query()
            ->where('surface', $surface->value)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        try {
            return Cache::rememberForever("client-languages.{$surface->value}", $load);
        } catch (\Throwable) {
            try {
                return $load();
            } catch (\Throwable) {
                return collect();
            }
        }
    }

    public function defaultLanguage(LanguageSurfaceEnum $surface): ?Language
    {
        return $this->languages($surface)->firstWhere('is_default', true)
            ?? $this->languages($surface)->first();
    }

    public function resolveLanguage(LanguageSurfaceEnum $surface, ?string $code): ?Language
    {
        $normalized = app(LanguageService::class)->normalizeCode((string) $code);

        return $this->languages($surface)
            ->first(fn (Language $language): bool => $language->code === $normalized)
            ?? $this->defaultLanguage($surface);
    }

    public function source(LanguageSurfaceEnum $surface): array
    {
        $contents = $this->sourceContents($surface);
        if ($contents === null) {
            return [];
        }

        $load = function () use ($contents): array {
            $document = $this->decodeSourceDocument($contents);
            if ($document === null) {
                return [];
            }

            $flat = [];
            if (! $this->flattenSourceDocument($document, '', $flat)) {
                return [];
            }
            ksort($flat);

            return $flat;
        };

        try {
            return Cache::rememberForever(
                "client-language-source.{$surface->value}.".hash('sha256', $contents),
                $load,
            );
        } catch (\Throwable) {
            return $load();
        }
    }

    public function translations(LanguageSurfaceEnum $surface, Language $language): Collection
    {
        if (! $language->exists || $language->getKey() === null || ! $this->translationsAvailable()) {
            return collect();
        }

        $load = fn () => LanguageTranslation::query()
            ->where('language_id', $language->id)
            ->pluck('value', 'key');

        try {
            return Cache::rememberForever(
                "client-language-labels.{$surface->value}.{$language->id}",
                $load,
            );
        } catch (\Throwable) {
            try {
                return $load();
            } catch (\Throwable) {
                return collect();
            }
        }
    }

    public function resolvedLabels(LanguageSurfaceEnum $surface, Language $language): array
    {
        $labels = $this->sourceDocument($surface);
        $source = $this->source($surface);

        if ($language->code !== 'en') {
            foreach ($this->translations($surface, $language)->only(array_keys($source)) as $key => $value) {
                data_set($labels, $key, $value);
            }
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $installedCodes
     * @return array<string, array{name: string, native_name: string, direction: string, has_translations: bool}>
     */
    public function quickAddOptions(LanguageSurfaceEnum $surface, array $installedCodes): array
    {
        return collect(config('languages.quick_add', []))
            ->reject(fn (array $definition, string $code): bool => in_array($code, $installedCodes, true))
            ->map(function (array $definition, string $code) use ($surface): array {
                return [
                    ...$definition,
                    'has_translations' => $this->quickAddPackHasTranslations($surface, $code),
                ];
            })
            ->all();
    }

    public function saveTranslations(LanguageSurfaceEnum $surface, Language $language, array $rows): void
    {
        $source = $this->source($surface);

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            $value = trim((string) ($row['value'] ?? ''));
            if (! array_key_exists($key, $source)) {
                continue;
            }
            if ($value === '') {
                $language->translations()->where('key', $key)->delete();

                continue;
            }
            if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
            }
            $language->translations()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->forget($language);
    }

    public function import(LanguageSurfaceEnum $surface, Language $language, array $labels): int
    {
        $flat = Arr::dot($labels);
        $source = $this->source($surface);
        $count = 0;

        foreach ($flat as $key => $value) {
            if (! array_key_exists($key, $source)) {
                continue;
            }
            if (! is_string($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_value', ['key' => $key]));
            }
            $value = trim($value);
            if ($value === '') {
                $language->translations()->where('key', $key)->delete();

                continue;
            }
            if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
            }
            $language->translations()->updateOrCreate(['key' => $key], ['value' => $value]);
            $count++;
        }

        $this->forget($language);

        return $count;
    }

    public function forgetLanguages(LanguageSurfaceEnum $surface): void
    {
        try {
            Cache::forget("client-languages.{$surface->value}");
        } catch (\Throwable) {
        }

        app(CatalogLanguageService::class)->clearCache();
    }

    public function forgetAllLanguages(): void
    {
        foreach (LanguageSurfaceEnum::clientValues() as $surface) {
            $this->forgetLanguages(LanguageSurfaceEnum::from($surface));
        }
    }

    public function forget(Language $language): void
    {
        try {
            Cache::forget("client-language-labels.{$language->surface}.{$language->id}");
            Cache::forget("client-languages.{$language->surface}");
        } catch (\Throwable) {
        }

        app(CatalogLanguageService::class)->clearCache();
    }

    private function languagesAvailable(): bool
    {
        try {
            return app(DatabaseSchema::class)->hasTable('languages');
        } catch (\Throwable) {
            return false;
        }
    }

    private function translationsAvailable(): bool
    {
        try {
            return app(DatabaseSchema::class)->hasTable('language_translations');
        } catch (\Throwable) {
            return false;
        }
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);
        $placeholders = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
        sort($placeholders);

        return $placeholders;
    }

    private function sourceDocument(LanguageSurfaceEnum $surface): array
    {
        $contents = $this->sourceContents($surface);
        if ($contents === null) {
            return [];
        }

        $load = function () use ($contents): array {
            $document = $this->decodeSourceDocument($contents);
            if ($document === null) {
                return [];
            }

            $flat = [];
            if (! $this->flattenSourceDocument($document, '', $flat)) {
                return [];
            }

            $labels = Arr::undot($flat);
            foreach ($this->emptyObjectPaths($document) as $path) {
                data_set($labels, $path, new stdClass);
            }

            return $labels;
        };

        try {
            return Cache::rememberForever(
                "client-language-source-document.{$surface->value}.".hash('sha256', $contents),
                $load,
            );
        } catch (\Throwable) {
            return $load();
        }
    }

    private function sourceContents(LanguageSurfaceEnum $surface): ?string
    {
        $path = config("languages.sources.{$surface->value}");
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function decodeSourceDocument(string $contents): ?array
    {
        try {
            $document = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! $document instanceof stdClass) {
            return null;
        }

        return get_object_vars($document);
    }

    /**
     * @param  array<string, string>  $flat
     */
    private function flattenSourceDocument(array|stdClass $document, string $prefix, array &$flat): bool
    {
        foreach ((array) $document as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if ($value instanceof stdClass) {
                if (! $this->flattenSourceDocument($value, $path, $flat)) {
                    return false;
                }

                continue;
            }

            if ($path === '' || mb_strlen($path) > 191 || ! is_string($value)) {
                return false;
            }

            $flat[$path] = $value;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function emptyObjectPaths(array|stdClass $document, string $prefix = ''): array
    {
        $paths = [];

        foreach ((array) $document as $key => $value) {
            if (! $value instanceof stdClass) {
                continue;
            }

            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if ((array) $value === []) {
                $paths[] = $path;

                continue;
            }

            array_push($paths, ...$this->emptyObjectPaths($value, $path));
        }

        return $paths;
    }

    private function quickAddPackHasTranslations(LanguageSurfaceEnum $surface, string $code): bool
    {
        $path = resource_path("language-packs/{$surface->value}/{$code}.json");
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($payload)) {
            return false;
        }

        $labels = is_array($payload['labels'] ?? null) ? $payload['labels'] : $payload;

        return collect(Arr::dot($labels))
            ->contains(fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }
}
