<?php

namespace App\Services\Ai;

use App\Services\CatalogLanguageService;
use RuntimeException;

class AiTranslationService
{
    public function __construct(
        protected AiManager $aiManager,
        protected CatalogLanguageService $catalogLanguages,
    ) {}

    /**
     * @param  array{title?: string, short_description?: string, description?: string}  $fields
     * @param  array<string, int>  $fieldLimits
     * @return array<string, array<string, string>>
     */
    public function translateEnglishFields(array $fields, array $fieldLimits = [], ?array $requestedTargetLocales = null): array
    {
        $settings = $this->aiManager->getAiSettings();
        if (empty($settings['enableAiDescription'])) {
            throw new RuntimeException('AI translation is disabled.');
        }

        $customFieldLimits = $fieldLimits;
        $fieldLimits = array_merge(['title' => 255, 'short_description' => 255, 'description' => 255], $fieldLimits);
        $fields = collect($fields)
            ->only(['title', 'short_description', 'description'])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->map(function (string $value, string $field) use ($fieldLimits): string {
                $limit = (int) ($fieldLimits[$field] ?? 255);
                if ($limit < 1 || mb_strlen($value) > $limit) {
                    throw new RuntimeException('AI translation source field exceeds its allowed length.');
                }

                return $value;
            })
            ->all();
        if ($fields === []) {
            return [];
        }

        $languages = $this->catalogLanguages->active()->reject(fn ($language): bool => $language->code === 'en');
        if ($requestedTargetLocales !== null) {
            $languages = $languages->whereIn('code', array_values(array_unique($requestedTargetLocales)));
        }
        $targetLocales = $languages->pluck('code')->values()->all();
        if ($targetLocales === []) {
            return [];
        }

        $driver = $this->aiManager->driver();
        $languageNames = $languages->pluck('name', 'code')->all();

        if ($customFieldLimits === []) {
            return $driver->translateStructured($fields, 'en', $targetLocales, $languageNames);
        }

        return $driver->translateStructured($fields, 'en', $targetLocales, $languageNames, $customFieldLimits);
    }

    /** @return array<int, string> */
    public function activeTargetLocales(): array
    {
        return $this->catalogLanguages->active()
            ->reject(fn ($language): bool => $language->code === 'en')
            ->pluck('code')
            ->values()
            ->all();
    }
}
