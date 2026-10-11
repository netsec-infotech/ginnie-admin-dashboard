<?php

namespace App\Http\Requests\DeliveryBoy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1C — rider drops an order. No required body fields; an optional
 * free-text note is accepted for future audit-log usage.
 */
class DropOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced in the service via order.delivery_boy_id match.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => 'nullable|string|max:500',
        ];
    }
}
