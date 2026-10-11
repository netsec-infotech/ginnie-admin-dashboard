<?php

namespace App\Http\Requests\DeliveryBoy;

use Illuminate\Foundation\Http\FormRequest;

class BulkCollectOrderRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => 'required|integer|exists:stores,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required' => __('validation.required', ['attribute' => 'store ID']),
            'store_id.integer' => __('validation.integer', ['attribute' => 'store ID']),
            'store_id.exists' => __('validation.exists', ['attribute' => 'store ID']),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_id' => 'store ID',
        ];
    }
}
