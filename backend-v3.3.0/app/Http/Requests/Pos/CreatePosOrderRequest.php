<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CreatePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'in:cash,upi,custom,razorpay,stripe,paystack,flutterwave'],
            'custom_payment_method_name' => ['nullable', 'required_if:payment_method,custom', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer'],
            'walkin_customer_name' => ['nullable', 'string', 'max:255'],
            'walkin_customer_mobile' => ['nullable', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.addons' => ['sometimes', 'array'],
            'items.*.addons.*.addon_group_id' => ['required_with:items.*.addons', 'integer'],
            'items.*.addons.*.addon_item_id' => ['required_with:items.*.addons', 'integer'],
            'order_note' => ['nullable', 'string', 'max:500'],

            // Bill-level cashier discount — service caps at subtotal.
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                // A percentage discount can never exceed 100%.
                $this->input('discount_type') === 'percent' ? 'max:100' : 'max:99999999.99',
            ],

            // Optional promo code — server re-validates via CartService.
            'promo_code' => ['nullable', 'string', 'max:64'],

            // Optional wallet payment portion (registered customers only).
            'wallet_amount' => ['nullable', 'numeric', 'min:0'],

            // Split tender — cash + a gateway.
            'split' => ['nullable', 'array'],
            'split.cash' => ['nullable', 'numeric', 'min:0'],
            'split.gateway_amount' => ['nullable', 'numeric', 'min:0'],

            // Cash portion when payment_method is split.
            'split_cash_portion' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_value.max' => $this->input('discount_type') === 'percent'
                ? __('labels.pos_discount_percent_max')
                : __('validation.max.numeric', ['attribute' => 'discount value', 'max' => '99999999.99']),
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id' => 'store',
            'payment_method' => 'payment method',
            'customer_id' => 'customer',
            'walkin_customer_name' => 'walk-in customer name',
            'walkin_customer_mobile' => 'walk-in customer mobile',
            'items' => 'order items',
            'items.*.store_product_variant_id' => 'product variant',
            'items.*.quantity' => 'item quantity',
            'items.*.addons' => 'item add-ons',
            'items.*.addons.*.addon_group_id' => 'add-on group',
            'items.*.addons.*.addon_item_id' => 'add-on item',
            'order_note' => 'order note',
            'discount_type' => 'discount type',
            'discount_value' => 'discount value',
            'promo_code' => 'promo code',
            'wallet_amount' => 'wallet amount',
            'split.cash' => 'split cash amount',
            'split.gateway_amount' => 'split gateway amount',
            'split_cash_portion' => 'split cash portion',
            'custom_payment_method_name' => 'custom payment method name',
        ];
    }
}
