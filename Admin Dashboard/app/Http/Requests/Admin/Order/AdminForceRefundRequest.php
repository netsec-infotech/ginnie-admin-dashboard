<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;

class AdminForceRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'reason' => 'required|string|min:3|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => __('labels.amount'),
            'reason' => __('labels.reason'),
        ];
    }
}
