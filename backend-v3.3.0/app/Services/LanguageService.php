<?php

namespace App\Services;

use App\Enums\LanguageSurfaceEnum;
use App\Models\Language;
use App\Support\DatabaseSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

class LanguageService
{
    private const ALIASED_GROUPS = ['auth', 'installer_messages', 'messages', 'pagination', 'passwords', 'validation'];

    private ?Collection $activeLanguages = null;

    public function active(): Collection
    {
        if ($this->activeLanguages instanceof Collection) {
            return $this->activeLanguages;
        }

        if (! $this->available()) {
            return $this->activeLanguages = $this->fallbackLanguages();
        }

        try {
            $languages = Cache::remember('languages.active', now()->addHour(), fn() => Language::query()
                ->where('surface', LanguageSurfaceEnum::PANEL->value)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get());
        } catch (\Throwable) {
            try {
                $languages = Language::query()
                    ->where('surface', LanguageSurfaceEnum::PANEL->value)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
            } catch (\Throwable) {
                return $this->activeLanguages = $this->fallbackLanguages();
            }
        }

        if ($languages->isEmpty()) {
            try {
                Cache::forget('languages.active');
                $hasConfiguredPanelLanguages = Language::query()
                    ->where('surface', LanguageSurfaceEnum::PANEL->value)
                    ->exists();
            } catch (\Throwable) {
                return $this->activeLanguages = $this->fallbackLanguages();
            }

            return $this->activeLanguages = $hasConfiguredPanelLanguages
                ? collect()
                : $this->fallbackLanguages();
        }

        return $this->activeLanguages = $languages;
    }

    public function findActive(?string $code): ?Language
    {
        $normalized = $this->normalizeCode((string) $code);

        return $this->active()->first(fn(Language $language) => $language->code === $normalized);
    }

    public function resolveForRequest(Request $request): Language
    {
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;
        $matched = $this->findActive($locale);
        if ($matched) {
            return $matched;
        }

        return $this->active()->firstWhere('is_default', true)
            ?? $this->findActive(config('app.locale'))
            ?? $this->active()->first()
            ?? $this->fallbackLanguages()->first();
    }

    public function apply(Language $language): void
    {
        app()->setLocale($language->code);

        $useBundledFiles = $language->code === 'en' || (bool) config('languages.legacy_panel_file_fallback', false);
        $fileLines = $useBundledFiles
            ? $this->bundledTree($language->code)
            : [];
        if ($fileLines !== []) {
            $fileLines = collect(Arr::dot($fileLines));
            Lang::addLines($fileLines->mapWithKeys(fn($value, string $key) => ["labels.{$key}" => $value])->all(), $language->code);
            $this->addGroupAliases($fileLines, $language->code);
        } elseif ($language->code !== 'en') {
            $this->addGroupAliases(collect(Arr::dot($this->sourceTree())), $language->code);
        }

        if (! $language->exists) {
            return;
        }

        $translations = $this->translations($language);
        $lines = $translations->mapWithKeys(fn(string $value, string $key) => ["labels.{$key}" => $value])->all();

        if ($lines !== []) {
            Lang::addLines($lines, $language->code);
            $this->addGroupAliases($translations, $language->code);
        }
    }

    public function registerEnglishGroupAliases(): void
    {
        $this->addGroupAliases(collect(Arr::dot($this->sourceTree())), 'en');
    }

    public function translations(Language $language): Collection
    {
        if (! $language->exists || $language->getKey() === null || ! $this->translationsAvailable()) {
            return collect();
        }

        $load = fn() => $language->translations()->pluck('value', 'key');
        $key = "language.labels.{$language->id}";

        try {
            return Cache::rememberForever($key, $load);
        } catch (\Throwable) {
            try {
                return $load();
            } catch (\Throwable) {
                return collect();
            }
        }
    }

    public function source(): array
    {
        return collect(Arr::dot($this->sourceTree()))
            ->filter(fn($value) => is_scalar($value) || $value === null)
            ->map(fn($value) => (string) $value)
            ->all();
    }

    public function exportTree(Language $language): array
    {
        return $this->translateTree(
            $this->sourceTree(),
            $this->translations($language),
            $language->code === 'en',
        );
    }

    public function saveTranslations(Language $language, array $rows): void
    {
        $source = $this->source();

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            $value = trim((string) ($row['value'] ?? ''));
            if (! array_key_exists($key, $source)) {
                continue;
            }
            if ($value === '') {
                $language->translations()
                    ->where('key', $key)
                    ->delete();
                continue;
            }
            if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
            }
            $language->translations()->updateOrCreate([
                'key' => $key,
            ], ['value' => $value]);
        }

        $this->touch($language);
    }

    public function import(Language $language, array $labels): int
    {
        $flat = Arr::dot($labels);
        $source = $this->source();
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
                $language->translations()
                    ->where('key', $key)
                    ->delete();

                continue;
            }
            if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
            }
            $language->translations()->updateOrCreate([
                'key' => $key,
            ], ['value' => $value]);
            $count++;
        }

        $this->touch($language);

        return $count;
    }

    public function touch(Language $language): void
    {
        try {
            Cache::forget("language.labels.{$language->id}");
        } catch (\Throwable) {
        }
        $this->forgetLanguages();
    }

    public function forgetLanguages(): void
    {
        $this->activeLanguages = null;

        try {
            Cache::forget('languages.active');
        } catch (\Throwable) {
        }

        app(CatalogLanguageService::class)->clearCache();
    }

    public function forget(Language $language): void
    {
        try {
            Cache::forget("language.labels.{$language->id}");
        } catch (\Throwable) {
        }
        $this->forgetLanguages();
    }

    public function normalizeCode(string $code): string
    {
        $parts = explode('-', str_replace('_', '-', trim($code)));
        $parts = array_values(array_filter($parts));
        if ($parts === []) {
            return '';
        }
        $parts[0] = strtolower($parts[0]);
        foreach (array_slice(array_keys($parts), 1) as $index) {
            $parts[$index] = strlen($parts[$index]) === 2 ? strtoupper($parts[$index]) : ucfirst(strtolower($parts[$index]));
        }

        return implode('-', $parts);
    }

    private function available(): bool
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

    private function fallbackLanguages(): Collection
    {
        return collect([
            ['surface' => LanguageSurfaceEnum::PANEL->value, 'code' => 'en', 'name' => 'English', 'native_name' => 'English', 'country_code' => 'us', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 10],
            ['surface' => LanguageSurfaceEnum::PANEL->value, 'code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'country_code' => 'es', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 20],
            ['surface' => LanguageSurfaceEnum::PANEL->value, 'code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'country_code' => 'fr', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 30],
            ['surface' => LanguageSurfaceEnum::PANEL->value, 'code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'country_code' => 'de', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 40],
            ['surface' => LanguageSurfaceEnum::PANEL->value, 'code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'country_code' => 'cn', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 50],
        ])->map(fn(array $attributes) => new Language($attributes));
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);
        $placeholders = array_map('strtolower', $matches[1] ?? []);
        sort($placeholders);

        return $placeholders;
    }

    private function sourceTree(): array
    {
        return require lang_path('en/labels.php');
    }

    private function bundledTree(string $locale): array
    {
        $tree = Lang::getLoader()->load($locale, 'labels');
        if ($locale === 'en') {
            return $tree;
        }

        foreach (self::ALIASED_GROUPS as $group) {
            $groupLines = Lang::getLoader()->load($locale, $group);
            if ($groupLines !== []) {
                $tree[$group] = $groupLines;
            }
        }

        return $tree;
    }

    private function addGroupAliases(Collection $lines, string $locale): void
    {
        foreach (self::ALIASED_GROUPS as $group) {
            $aliases = $lines->filter(
                fn($value, string $key) => str_starts_with($key, "{$group}.") && is_string($value),
            )->all();

            if ($aliases !== []) {
                Lang::addLines($aliases, $locale);
            }
        }
    }

    private function translateTree(array $tree, Collection $translations, bool $useSource, string $prefix = ''): array
    {
        $translated = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            $translated[$key] = is_array($value)
                ? $this->translateTree($value, $translations, $useSource, $path)
                : ($useSource ? (string) $value : (string) $translations->get($path, ''));
        }

        return $translated;
    }
}
