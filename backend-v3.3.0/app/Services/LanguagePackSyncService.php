<?php

namespace App\Services;

use App\Enums\LanguageSurfaceEnum;
use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

class LanguagePackSyncService
{
    public const LOCK_KEY = 'language-packs.sync';

    public function __construct(protected LanguageService $panel, protected ClientLanguageService $clients) {}

    /** @return array{results: array, totals: array<string, int>} */
    public function sync(string $surface = 'all'): array
    {
        if ($surface !== 'all' && LanguageSurfaceEnum::tryFrom($surface) === null) {
            throw new InvalidArgumentException(__('labels.language_sync_invalid_surface'));
        }

        return $this->locked(function () use ($surface): array {
            $results = [];
            $languages = Language::query()->when($surface !== 'all', fn ($query) => $query->where('surface', $surface))
                ->orderBy('surface')->orderBy('id')->get();
            foreach ($languages as $language) {
                $results[] = $this->syncUnlocked($language);
            }

            return $this->summarize($results);
        });
    }

    /** @return array<string, mixed> */
    public function syncLanguage(LanguageSurfaceEnum $surface, Language $language): array
    {
        if (! $language->exists || $language->surface !== $surface->value) {
            throw new InvalidArgumentException(__('labels.language_sync_invalid_surface'));
        }

        return $this->locked(fn (): array => $this->syncUnlocked($language));
    }

    private function locked(callable $callback): array
    {
        if (! Schema::hasTable('languages') || ! Schema::hasTable('language_translations')) {
            throw new RuntimeException(__('labels.language_sync_tables_missing'));
        }
        $lock = Cache::lock(self::LOCK_KEY, 300);
        if (! $lock->get()) {
            throw new RuntimeException(__('labels.language_sync_busy'));
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function syncUnlocked(Language $language): array
    {
        $result = ['surface' => $language->surface, 'locale' => $language->code, 'status' => 'skipped',
            'inserted' => 0, 'existing' => 0, 'skipped' => 0, 'failed' => 0, 'missing' => 0, 'message' => ''];
        try {
            $surface = LanguageSurfaceEnum::tryFrom($language->surface);
            if ($surface === null) {
                throw new InvalidArgumentException(__('labels.language_sync_invalid_surface'));
            }
            $code = $this->locale($language->code);
            if ($code === 'en') {
                $result['message'] = __('labels.language_sync_english');

                return $result;
            }
            $path = config('languages.packs_path', resource_path('language-packs'))."/{$surface->value}/{$code}.json";
            if (! is_file($path)) {
                $result['message'] = __('labels.language_sync_missing_pack');

                return $result;
            }
            $audit = $this->inspect($surface, $code, $path);
            $result['skipped'] = $audit['skipped'];
            $result['missing'] = $audit['missing'];
            $values = $audit['values'];
            $result['inserted'] = DB::transaction(function () use ($language, $values): int {
                $now = now();
                $rows = [];
                foreach ($values as $key => $value) {
                    $rows[] = ['language_id' => $language->id, 'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
                }
                $inserted = 0;
                foreach (array_chunk($rows, 100) as $chunk) {
                    $inserted += DB::table('language_translations')->insertOrIgnore($chunk);
                }

                return $inserted;
            });
            $result['existing'] = count($values) - $result['inserted'];
            if ($surface === LanguageSurfaceEnum::PANEL) {
                $this->panel->forget($language);
            } else {
                $this->clients->forget($language);
            }
            $result['status'] = $values === [] ? 'skipped' : 'completed';
            $result['message'] = __($values === [] ? 'labels.language_sync_empty_pack' : 'labels.language_sync_completed');
        } catch (Throwable $exception) {
            report($exception);
            $result['status'] = 'failed';
            $result['failed'] = 1;
            $result['message'] = $exception instanceof InvalidArgumentException ? $exception->getMessage() : __('labels.language_sync_failed');
        }

        return $result;
    }

    /** Audit every bundled pack, including packs for uninstalled languages, without database writes. */
    public function auditPacks(): array
    {
        $results = [];
        foreach (LanguageSurfaceEnum::cases() as $surface) {
            foreach (glob(config('languages.packs_path', resource_path('language-packs'))."/{$surface->value}/*.json") ?: [] as $path) {
                $code = pathinfo($path, PATHINFO_FILENAME);
                if ($code === 'en') {
                    continue;
                }
                try {
                    $audit = $this->inspect($surface, $this->locale($code), $path);
                    $results[] = ['surface' => $surface->value, 'locale' => $code, 'status' => 'valid',
                        'canonical' => $audit['canonical'], 'translated' => count($audit['values']),
                        'missing' => $audit['missing'], 'skipped' => $audit['skipped']];
                } catch (Throwable $exception) {
                    $results[] = ['surface' => $surface->value, 'locale' => $code, 'status' => 'invalid', 'message' => $exception->getMessage()];
                }
            }
        }

        return $results;
    }

    private function locale(string $locale): string
    {
        $code = $this->panel->normalizeCode($locale);
        if (! preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $code)) {
            throw new InvalidArgumentException(__('labels.invalid_language'));
        }

        return $code;
    }

    private function inspect(LanguageSurfaceEnum $surface, string $code, string $path): array
    {
        $source = $surface === LanguageSurfaceEnum::PANEL ? $this->panel->source() : $this->clients->source($surface);
        if ($source === []) {
            throw new InvalidArgumentException(__('labels.language_sync_source_missing'));
        }
        if (! is_readable($path)) {
            throw new InvalidArgumentException(__('labels.language_sync_unreadable'));
        }
        try {
            $document = json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(__('labels.language_sync_invalid_pack'), 0, $exception);
        }
        if (! $document instanceof stdClass) {
            throw new InvalidArgumentException(__('labels.language_sync_invalid_pack'));
        }
        $properties = get_object_vars($document);
        if (($properties['labels'] ?? null) instanceof stdClass && array_diff(array_keys($properties), ['labels', 'source_labels']) === []) {
            $document = $properties['labels'];
        }
        $flat = [];
        $this->flatten($document, '', $flat);
        $values = [];
        $skipped = 0;
        foreach ($flat as $key => $value) {
            if (! array_key_exists($key, $source)) {
                $skipped++;
                continue;
            }
            if (! is_string($value) || $key === '' || mb_strlen($key) > 191) {
                throw new InvalidArgumentException(__('labels.language_pack_invalid_value', ['key' => $key]));
            }
            if (trim($value) === '') {
                $skipped++;
                continue;
            }
            $this->validatePlaceholders($surface, $code, $key, $source[$key], $value);
            $values[$key] = trim($value);
        }

        return ['values' => $values, 'skipped' => $skipped, 'canonical' => count($source), 'missing' => count(array_diff_key($source, $values))];
    }

    private function flatten(stdClass $document, string $prefix, array &$flat): void
    {
        foreach (get_object_vars($document) as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if ($value instanceof stdClass) {
                $this->flatten($value, $path, $flat);
            } else {
                $flat[$path] = $value;
            }
        }
    }

    private function validatePlaceholders(LanguageSurfaceEnum $surface, string $code, string $key, string $source, string $value): void
    {
        $tokens = function (string $text) use ($surface): array {
            preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $matches);
            $tokens = array_map('strtolower', $matches[1]);
            if ($surface !== LanguageSurfaceEnum::PANEL) {
                $tokens = array_values(array_unique($tokens));
            }
            sort($tokens);

            return $tokens;
        };
        $valid = $tokens($source) === $tokens($value);
        if ($surface === LanguageSurfaceEnum::WEB) {
            $interpolations = static function (string $text): array {
                preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/u', $text, $matches);
                $tokens = array_values(array_unique(array_map('trim', $matches[1])));
                sort($tokens);

                return $tokens;
            };
            $expected = $interpolations($source);
            $actual = $interpolations($value);
            if ($code === 'ar' && in_array($key, ['reels.likesCount_one', 'blog.results_one', 'blog.minuteRead_one'], true) && ! in_array('count', $actual, true)) {
                $expected = array_values(array_diff($expected, ['count']));
            }
            $valid = $valid && $expected === $actual;
        }
        if (! $valid) {
            throw new InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
        }
    }

    private function summarize(array $results): array
    {
        $totals = ['inserted' => 0, 'existing' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($results as $result) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $result[$key];
            }
        }

        return compact('results', 'totals');
    }
}
