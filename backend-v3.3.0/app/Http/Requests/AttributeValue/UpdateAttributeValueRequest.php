<?php

namespace App\Http\Requests\AttributeValue;

use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeValueRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('values')) {
            $this->merge([
                'values' => collect($this->input('values', []))
                    ->map(fn (mixed $value): array => $this->normalizeTranslationInput($value))
                    ->all(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = app(CatalogLanguageService::class)->active()->pluck('code')->all();
        $rules = [
            'attribute_id' => [
                'required',
                Rule::exists('global_product_attributes', 'id')
                    ->withoutTrashed(),
            ],
            'values' => 'nullable|array',
            'values.*' => ['nullable', 'array:'.implode(',', $locales)],
            'values.*.en' => ['nullable', 'string', 'max:255'],
            'values.*.*' => ['nullable', 'string', 'max:255'],
            'swatche_value' => 'nullable|array',
        ];

        $attribute = GlobalProductAttribute::find($this->attribute_id);
        if ($attribute && $attribute->swatche_type === 'text') {
            $rules['swatche_value'] = 'required|array';
            $rules['swatche_value.*'] = 'required';
        } else {
            $rules['swatche_value.*'] = 'nullable';
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $normalizedValues = [];
            $id = $this->route('id');

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
                    ->where('id', '!=', $id)
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
