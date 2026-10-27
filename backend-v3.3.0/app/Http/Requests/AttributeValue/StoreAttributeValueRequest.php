<?php

namespace App\Http\Requests\AttributeValue;

use App\Models\GlobalProductAttributeValue;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeValueRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'values' => collect($this->input('values', []))
                ->map(fn (mixed $value): array => $this->normalizeTranslationInput($value))
                ->all(),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = app(CatalogLanguageService::class)->active()->pluck('code')->all();

        return [
            'attribute_id' => [
                'required',
                Rule::exists('global_product_attributes', 'id')
                    ->withoutTrashed(),
            ],
            'values' => 'required|array|min:1',
            'values.*' => ['required', 'array:'.implode(',', $locales)],
            'values.*.en' => ['required', 'string', 'max:255'],
            'values.*.*' => ['nullable', 'string', 'max:255'],
            'swatche_value' => 'required|array',
            'swatche_value.*' => 'required',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $normalizedValues = [];

            foreach ($this->input('values', []) as $index => $value) {
                $englishValue = data_get($value, 'en');
                if (! filled($englishValue)) {
                    continue;
                }

                $title = trim((string) $englishValue);
                $normalizedTitle = mb_strtolower($title);

                if (in_array($normalizedTitle, $normalizedValues, true)) {
                    $validator->errors()->add("values.$index", "Attribute value '{$title}' already exists for this attribute.");

                    continue;
                }

                $normalizedValues[] = $normalizedTitle;

                $exists = GlobalProductAttributeValue::query()
                    ->where('global_attribute_id', $this->input('attribute_id'))
                    ->whereEnglishTitle($normalizedTitle)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add("values.$index", "Attribute value '{$title}' already exists for this attribute.");
                }
            }
        });
    }

    private function normalizeTranslationInput(mixed $value): array
    {
        if (is_string($value)) {
            return ['en' => trim($value)];
        }

        if (! is_array($value)) {
            return ['en' => ''];
        }

        return collect($value)->mapWithKeys(fn (mixed $translation, mixed $locale): array => [
            app(LanguageService::class)->normalizeCode((string) $locale) => is_string($translation) ? trim($translation) : $translation,
        ])->all();
    }
}
