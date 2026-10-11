<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

class AssignReturnRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via OrderReturnPolicy::assignRider.
        return true;
    }

    public function rules(): array
    {
        return [
            // null = unassign and push the return back to the rider pool
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
