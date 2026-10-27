<?php

namespace App\Http\Requests\DeliveryBoy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1C — rider marks a collected item's delivery attempt as failed. The
 * reason_code drives downstream UX (customer notification copy, admin
 * escalation rules) and is constrained to a known enum.
 */
class MarkDeliveryFailedRequest extends FormRequest
{
    /** @var string[] */
    public const ALLOWED_REASONS = [
        'customer_unavailable',
        'customer_refused',
        'wrong_address',
        'unsafe_location',
    ];

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
            'reason_code' => 'required|string|in:' . implode(',', self::ALLOWED_REASONS),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason_code.in' => __('labels.invalid_delivery_fail_reason'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason_code' => __('labels.reason'),
        ];
    }
}
