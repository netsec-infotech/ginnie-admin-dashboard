<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $surfaceLanguages = [
        'customer_app' => ['en', 'ar', 'fr', 'gu', 'hi', 'te'],
        'seller_app' => ['en', 'ar', 'hi'],
        'rider_app' => ['en', 'ar', 'fr', 'gu', 'hi', 'te'],
    ];

    /**
     * @var array<string, array{name: string, native_name: string, direction: string, sort_order: int}>
     */
    private array $languages = [
        'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'sort_order' => 10],
        'fr' => ['name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'sort_order' => 30],
        'hi' => ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'direction' => 'ltr', 'sort_order' => 60],
        'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'sort_order' => 70],
        'gu' => ['name' => 'Gujarati', 'native_name' => 'ગુજરાતી', 'direction' => 'ltr', 'sort_order' => 80],
        'te' => ['name' => 'Telugu', 'native_name' => 'తెలుగు', 'direction' => 'ltr', 'sort_order' => 90],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->surfaceLanguages as $surface => $codes) {
            $hasDefault = DB::table('languages')
                ->where('surface', $surface)
                ->where('is_default', true)
                ->exists();

            foreach ($codes as $code) {
                DB::table('languages')->insertOrIgnore([
                    ...$this->languages[$code],
                    'surface' => $surface,
                    'code' => $code,
                    'is_active' => true,
                    'is_default' => ! $hasDefault && $code === 'en',
                    'is_recommended' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (!DB::table('languages')->where('surface', $surface)->where('is_default', true)->exists()) {
                DB::table('languages')
                    ->where('surface', $surface)
                    ->where('code', 'en')
                    ->update(['is_active' => true, 'is_default' => true, 'updated_at' => $now]);
            }

            foreach (array_diff($codes, ['en']) as $code) {
                $this->importTranslations($surface, $code, $now);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->surfaceLanguages as $surface => $codes) {
            DB::table('languages')
                ->where('surface', $surface)
                ->whereIn('code', $codes)
                ->delete();
        }
    }

    private function importTranslations(string $surface, string $code, mixed $now): void
    {
        $source = $this->labels(resource_path("language-sources/{$surface}/en.json"));
        $translations = $this->labels(resource_path("language-packs/{$surface}/{$code}.json"));
        $languageId = DB::table('languages')
            ->where('surface', $surface)
            ->where('code', $code)
            ->value('id');

        if (! $languageId) {
            return;
        }

        $rows = collect($translations)
            ->filter(fn(mixed $value, string $key): bool => array_key_exists($key, $source)
                && is_string($value)
                && trim($value) !== '')
            ->map(function (string $value, string $key) use ($source, $languageId, $now): array {
                if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                    throw new RuntimeException("The placeholders do not match the English mobile source for: {$key}.");
                }

                return [
                    'language_id' => $languageId,
                    'key' => $key,
                    'value' => trim($value),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values();

        $rows->chunk(500)->each(
            fn($chunk) => DB::table('language_translations')->insertOrIgnore($chunk->all()),
        );
    }

    private function labels(string $path): array
    {
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw new RuntimeException("Invalid mobile language catalog: {$path}.");
        }

        $wrapped = is_array($payload['labels'] ?? null)
            && array_diff(array_keys($payload), ['labels', 'source_labels']) === [];
        $labels = $wrapped ? $payload['labels'] : $payload;
        $flat = Arr::dot($labels);
        foreach ($flat as $key => $value) {
            if (is_array($value) && $value === []) {
                unset($flat[$key]);

                continue;
            }

            if ($key === '' || mb_strlen($key) > 191 || ! is_string($value)) {
                throw new RuntimeException("Invalid mobile language value for: {$key}.");
            }
        }

        return $flat;
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);
        $placeholders = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
        sort($placeholders);

        return $placeholders;
    }
};
