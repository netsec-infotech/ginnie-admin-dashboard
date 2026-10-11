<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;

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
        $isUpdate = strtolower((string) $this->input('operation', 'create')) === 'update';

        return [
            'preview_token' => ['required', 'string', 'max:80'],
            'original_filename' => ['required', 'string', 'max:255'],
            'operation' => ['nullable', 'string', 'in:create,update'],
            'match_field' => [$isUpdate ? 'required' : 'nullable', 'string', 'max:60'],
            'notify_on_finish' => ['nullable', 'boolean'],
            'mapping' => ['required', 'array'],
            // title is only required in Create mode — in Update mode the admin
            // may want to update non-title fields only (matched by id, say).
            'mapping.title' => [$isUpdate ? 'nullable' : 'required', 'string'],
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
        ]);
    }
}
