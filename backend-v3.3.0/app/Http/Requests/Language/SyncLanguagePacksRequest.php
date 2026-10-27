<?php

namespace App\Http\Requests\Language;

use App\Enums\LanguageSurfaceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncLanguagePacksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('language.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array{surface: array<mixed>}
     */
    public function rules(): array
    {
        return [
            'surface' => ['required', 'string', Rule::in(['all', ...LanguageSurfaceEnum::values()])],
        ];
    }
}
