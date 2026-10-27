<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;

class AdminReassignRiderRequest extends FormRequest
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
            // null = unassign and push back to the rider pool
            'delivery_boy_id' => 'nullable|integer|exists:delivery_boys,id',
            'reason' => 'required|string|min:3|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'delivery_boy_id' => __('labels.delivery_boy'),
            'reason' => __('labels.reason'),
        ];
    }
}
