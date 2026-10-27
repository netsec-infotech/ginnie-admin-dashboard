<?php

namespace App\Http\Requests\Brand;

use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmBulkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller does the policy check.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $operation = strtolower((string) $this->input('operation', 'create'));
        $isUpdateOrTranslation = in_array($operation, ['update', 'translation'], true);

        return [
            'preview_token' => ['required', 'string', 'max:80'],
            'original_filename' => ['required', 'string', 'max:255'],
            'operation' => ['nullable', 'string', 'in:create,update,translation'],
            'target_locale' => [
                'required_if:operation,translation',
                'nullable',
                'string',
                'max:10',
                Rule::notIn(['en']),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ! app(CatalogLanguageService::class)->isValidCatalogLocale($value)) {
                        $fail(__('labels.invalid_language'));
                    }
                },
            ],
            'match_field' => [$isUpdateOrTranslation ? 'required' : 'nullable', 'string', 'max:60'],
            'notify_on_finish' => ['nullable', 'boolean'],
            'mapping' => ['required', 'array'],
            // title is only required in Create mode — in Update/Translation mode the admin
            // may want to update translation fields only (matched by uuid or id).
            'mapping.title' => [$isUpdateOrTranslation ? 'nullable' : 'required', 'string'],
            'mapping.*' => ['nullable', 'string', 'max:255'],
            'defaults' => ['nullable', 'array'],
            'defaults.*' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapping.title.required' => __('labels.bulk_upload_title_mapping_required'),
            'match_field.required' => __('labels.bulk_upload_match_field_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Strip empty-string mapping values so they don't pollute the saved JSON.
        $mapping = $this->input('mapping', []);
        if (is_array($mapping)) {
            $mapping = array_filter($mapping, fn ($v) => $v !== null && $v !== '');
            $this->merge(['mapping' => $mapping]);
        }
        $defaults = $this->input('defaults', []);
        if (is_array($defaults)) {
            $defaults = array_filter($defaults, fn ($v) => $v !== null && $v !== '');
            $this->merge(['defaults' => $defaults]);
        }
        // Unchecked checkboxes are absent from form payloads. Normalise to a
        // strict bool so the validator + service treat the flag consistently.
        $this->merge([
            'notify_on_finish' => filter_var(
                $this->input('notify_on_finish', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'target_locale' => is_string($this->input('target_locale'))
                ? app(LanguageService::class)->normalizeCode($this->input('target_locale'))
                : $this->input('target_locale'),
        ]);
    }
}
