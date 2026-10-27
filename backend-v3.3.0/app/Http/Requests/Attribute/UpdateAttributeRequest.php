<?php

namespace App\Http\Requests\Attribute;

use App\Enums\Attribute\AttributeTypesEnum;
use App\Models\GlobalProductAttribute;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAttributeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('title')) {
            $this->merge([
                'title' => $this->normalizeTranslationInput($this->input('title')),
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

        return [
            'title' => ['sometimes', 'required', 'array:'.implode(',', $locales)],
            'title.en' => ['required_with:title', 'string', 'max:255'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'label' => 'required|string',
            'swatche_type' => ['nullable', new Enum(AttributeTypesEnum::class)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('title.en')) {
                return;
            }

            $sellerId = optional(auth()->user())->seller()->id;
            $title = trim((string) $this->input('title.en'));
            $exists = GlobalProductAttribute::query()
                ->whereEnglishTitle($title)
                ->when($sellerId, fn ($query) => $query->where('seller_id', $sellerId))
                ->whereKeyNot($this->route('id'))
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $validator->errors()->add('title.en', __('labels.title_already_exists'));
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
