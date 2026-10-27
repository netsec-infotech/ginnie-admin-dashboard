<?php

namespace App\Http\Requests\Category;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $category = Category::find($this->route('id'));
        $hasImage = $category && ($category->getFirstMedia(SpatieMediaCollectionName::CATEGORY_IMAGE()) !== null || !empty($category->getFirstMediaUrl('image')));

        return self::baseRules($this->route('id'), $hasImage);
    }

    /**
     * Shared category rules for HTTP forms and non-HTTP importers.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public static function baseRules(int|string|null $ignoreId = null, bool $hasImage = true): array
    {
        $locales = app(CatalogLanguageService::class)->active()->pluck('code')->all();

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function (string $attribute, $value, \Closure $fail) use ($ignoreId): void {
                    if ($ignoreId !== null && $value !== null && $value !== '' && (int) $value === (int) $ignoreId) {
                        $fail(__('labels.parent_cannot_be_self'));
                    }
                },
            ],
            'title' => ['required', 'array:'.implode(',', $locales)],
            'title.en' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId): void {
                    $query = Category::withTrashed()->whereEnglishTitle((string) $value);
                    if ($ignoreId !== null && $ignoreId !== '') {
                        $query->whereKeyNot($ignoreId);
                    }
                    if ($query->exists()) {
                        $fail(__('labels.title_already_exists'));
                    }
                },
            ],
            'title.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array:'.implode(',', $locales)],
            'description.*' => ['nullable', 'string', 'max:10000'],
            'image' => ($hasImage ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg,webp|max:2048',
            'icon' => 'nullable|mimes:jpeg,png,jpg,webp,svg',
            'active_icon' => 'nullable|mimes:jpeg,png,jpg,webp,svg',
            'background_type' => ['nullable', new Enum(CategoryBackgroundTypeEnum::class)],
            'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color_web' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color_app' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_image_web' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_image_web_mobile' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_image_app' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_animation' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:10240',
            'background_animation_web' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:10240',
            'background_animation_app' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:10240',
            'status' => ['nullable', new Enum(CategoryStatusEnum::class)],
            'requires_approval' => 'boolean',
            'commission' => 'nullable|numeric|min:0|max:100',
            'meta_title' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'searchLabels' => 'nullable|array',
            'searchLabels.*' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'metadata.seo_title' => 'nullable|string|max:255',
            'metadata.seo_keywords' => 'nullable|array',
            'metadata.seo_keywords.*' => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $title = $this->normalizeTranslationInput($this->input('title'));
        $normalized = [
            'status' => $this->status ?? CategoryStatusEnum::INACTIVE->value,
            'requires_approval' => $this->requires_approval ?? false,
            'title' => is_array($title) ? $title : ['en' => ''],
        ];

        if ($this->exists('description')) {
            $description = $this->normalizeTranslationInput($this->input('description'));
            $normalized['description'] = is_array($description) ? $description : null;
        }

        $this->merge($normalized);
    }

    private function normalizeTranslationInput(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return ['en' => trim($value)];
        }

        if (! is_array($value)) {
            return null;
        }

        return collect($value)->mapWithKeys(function (mixed $translation, mixed $locale): array {
            $normalized = app(LanguageService::class)->normalizeCode((string) $locale);

            return [$normalized => is_string($translation) ? trim($translation) : $translation];
        })->all();
    }
}
