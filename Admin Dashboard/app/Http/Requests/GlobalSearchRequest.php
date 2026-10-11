<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class GlobalSearchRequest
 * Validates global search inputs for Admin and Seller panel search queries.
 */
class GlobalSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * Get sanitized search query.
     */
    public function getSearchQuery(): string
    {
        return trim((string) $this->input('q'));
    }
}
