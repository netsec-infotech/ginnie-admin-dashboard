<?php

namespace App\Services;

use App\Models\GlobalProductAttribute;

class GlobalAttributeService
{
    public static function getAttributesWithValue($sellerId = null)
    {
        return GlobalProductAttribute::with('values')
            ->when(
                $sellerId,
                fn ($query) => $query->where(fn ($builder) => $builder
                    ->whereNull('seller_id')
                    ->orWhere('seller_id', $sellerId)
                ),
                fn ($query) => $query->whereNull('seller_id')
            )
            ->orderByRaw('CASE WHEN seller_id IS NULL THEN 0 ELSE 1 END')
            ->get()
            ->mapWithKeys(function ($attr) {
                $englishTitle = self::englishTitle($attr);

                return [
                    strtolower($englishTitle) => [
                        'id' => $attr->id,
                        'name' => $englishTitle,
                        'translations' => self::titleTranslations($attr),
                        'values' => $attr->values->map(function ($value) {
                            return [
                                'id' => $value->id,
                                'name' => self::englishTitle($value),
                                'translations' => self::titleTranslations($value),
                            ];
                        })->toArray(),
                    ],
                ];
            });
    }

    private static function englishTitle($model): string
    {
        $translation = $model->getTranslation('title', 'en', false);

        return $translation !== '' ? $translation : (string) $model->getRawOriginal('title');
    }

    private static function titleTranslations($model): array
    {
        $translations = $model->getTranslations('title');
        if ($translations !== []) {
            return $translations;
        }

        $title = trim((string) $model->getRawOriginal('title'));

        return $title === '' ? [] : ['en' => $title];
    }
}
