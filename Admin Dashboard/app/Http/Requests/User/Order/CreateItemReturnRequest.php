<?php

namespace App\Http\Requests\User\Order;

use Illuminate\Foundation\Http\FormRequest;

class CreateItemReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        // Return Flow Fix: Support custom "Other" return reason text when no predefined reason ID is provided.
        return [
            'return_reason_id' => ['nullable', 'integer', 'exists:return_reasons,id', 'required_without:reason'],
            'reason' => ['nullable', 'string', 'max:1000', 'required_without:return_reason_id'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,png,webp,jpeg', 'max:5120'],
        ];
    }

    /**
     * Custom validation messages (optional).
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'exists' => 'The selected :attribute is invalid.',
            'string' => 'The :attribute must be a valid string.',
            'array' => 'The :attribute must be an array.',
        ];
    }

    /**
     * Human-friendly field labels.
     */
    public function attributes(): array
    {
        return [
            'return_reason_id' => 'Return Reason',
            'reason' => 'Comment / Additional Details',
            'images' => 'Return Proof Images',
        ];
    }
}
