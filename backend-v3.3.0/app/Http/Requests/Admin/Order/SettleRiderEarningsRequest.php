<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettleRiderEarningsRequest extends FormRequest
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
            'decision' => 'required|string|in:approve,reject',
            'reason'   => 'required|string|min:3|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'decision' => __('labels.decision'),
            'reason'   => __('labels.reason'),
        ];
    }
}
