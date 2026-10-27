<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminForceCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => __('labels.reason'),
        ];
    }
}
