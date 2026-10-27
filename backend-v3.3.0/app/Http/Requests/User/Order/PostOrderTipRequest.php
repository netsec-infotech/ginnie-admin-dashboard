<?php

namespace App\Http\Requests\User\Order;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostOrderTipRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tippingConfig = Setting::getRiderTipSettings();
        $min = (float) ($tippingConfig['min_tip_amount'] ?? 0);
        $max = (float) ($tippingConfig['max_tip_amount'] ?? 99999);

        // Hosted gateways return a payment link before the tip is captured.
        $allowedMethods = [
            'wallet',
            'stripe',
            'razorpay',
            'paystack',
            'flutterwave',
            'xendit',
            'mercadopago',
            'mercado_pago',
            'stripePayment',
            'razorpayPayment',
            'paystackPayment',
            'flutterwavePayment',
            'xenditPayment',
            'mercadoPagoPayment',
        ];

        return [
            'rider_tip_amount' => ['required', 'numeric', "min:{$min}", "max:{$max}"],
            'payment_method' => ['required', 'string', 'in:'.implode(',', $allowedMethods)],
            'transaction_id' => ['nullable', 'string'],
            'payment_id' => ['nullable', 'string'],
            'razorpay_payment_id' => ['nullable', 'string'],
            'razorpay_order_id' => ['nullable', 'string'],
            'razorpay_signature' => ['nullable', 'string'],
        ];
    }
}
