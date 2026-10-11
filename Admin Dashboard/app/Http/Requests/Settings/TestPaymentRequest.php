<?php

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inputs for the admin "Test payment gateway" action. The form sends the
 * credentials currently typed into the payment-settings form along with the
 * gateway identifier — the controller proxies a read-only API call so the
 * operator can verify the keys before saving.
 */
class TestPaymentRequest extends FormRequest
{
    public const SUPPORTED_GATEWAYS = ['stripe', 'razorpay', 'paystack', 'flutterwave', 'xendit'];

    public function authorize(): bool
    {
        return $this->user()?->can('updateSetting', [Setting::class, 'payment']) === true;
    }

    public function rules(): array
    {
        return [
            'gateway'    => ['required', 'string', Rule::in(self::SUPPORTED_GATEWAYS)],
            'key_id'     => ['nullable', 'string', 'max:191'],
            'secret_key' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function attributes(): array
    {
        return [
            'gateway'    => __('labels.payment_gateway'),
            'key_id'     => __('labels.razorpay_key_id'),
            'secret_key' => __('labels.stripe_secret_key'),
        ];
    }

    /**
     * Trimmed credential bag passed straight to the test service.
     */
    public function credentials(): array
    {
        return [
            'key_id'     => trim((string) $this->input('key_id', '')),
            'secret_key' => trim((string) $this->input('secret_key', '')),
        ];
    }
}
