<?php

namespace App\Http\Requests\DeliveryBoy;

use Illuminate\Foundation\Http\FormRequest;

class BlockDeliveryBoyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced in the controller via ChecksPermissions; the
        // route also gates the admin guard.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('labels.reason'),
        ];
    }
}
