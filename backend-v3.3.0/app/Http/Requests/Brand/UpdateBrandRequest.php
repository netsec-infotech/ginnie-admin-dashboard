<?php

namespace App\Http\Requests\Brand;

use App\Enums\HomePageScopeEnum;
use App\Models\Brand;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return self::baseRules($this->route('id'));
    }

    public static function baseRules(int|string|null $ignoreId = null): array
    {
        $locales = app(CatalogLanguageService::class)->active()->pluck('code')->all();

        return [
            'scope_type' => ['required', Rule::in(HomePageScopeEnum::forBrands())],
            'scope_id' => [
                'required_if:scope_type,' . HomePageScopeEnum::CATEGORY(),
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->whereNull('parent_id');
                }),
            ],
            'title' => ['required', 'array:'.implode(',', $locales)],
            'title.en' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    $query = Brand::query()->whereEnglishTitle((string) $value);

                    if ($ignoreId) {
                        $query->where('id', '!=', $ignoreId);
                    }

                    if ($query->exists()) {
                        $fail(__('labels.brand_title_already_exists', ['title' => (string) $value]));
                    }
                },
            ],
            'title.*' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'array:'.implode(',', $locales)],
            'description.en' => ['required', 'string', 'max:255'],
            'description.*' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.seo_title' => ['nullable', 'string', 'max:255'],
            'metadata.seo_keywords' => ['nullable', 'array'],
            'metadata.seo_keywords.*' => ['nullable', 'string', 'max:60'],
            'metadata.seo_description' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $titleInput = $this->normalizedTranslations($this->input('title'));
        if (is_string($titleInput)) {
            $titleInput = ['en' => trim($titleInput)];
        }

        $descInput = $this->normalizedTranslations($this->input('description'));
        if (is_string($descInput)) {
            $descInput = ['en' => trim($descInput)];
        }

        $this->merge([
            'status' => $this->status ?? 'inactive',
            'title' => is_array($titleInput) ? $titleInput : ['en' => ''],
            'description' => is_array($descInput) ? $descInput : ['en' => ''],
        ]);
    }

    private function normalizedTranslations(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)->mapWithKeys(function (mixed $translation, mixed $locale): array {
            $normalized = app(LanguageService::class)->normalizeCode((string) $locale);

            return [$normalized => is_string($translation) ? trim($translation) : $translation];
        })->all();
    }
}
