<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StorePosPaymentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'                => ['required', 'integer'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.store_product_variant_id' => ['required', 'integer'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.addons'                  => ['sometimes', 'array'],
            'items.*.addons.*.addon_group_id' => ['required_with:items.*.addons', 'integer'],
            'items.*.addons.*.addon_item_id'  => ['required_with:items.*.addons', 'integer'],
            'customer_id'             => ['nullable', 'integer'],
            'walkin_customer_name'    => ['nullable', 'string', 'max:255'],
            'walkin_customer_mobile'  => ['nullable', 'string', 'max:32'],
            'order_note'              => ['nullable', 'string', 'max:500'],
            'amount'                  => ['required', 'numeric', 'min:1'],
            'cash_portion'            => ['nullable', 'numeric', 'min:0'],
            'discount_type'           => ['nullable', 'in:percent,fixed'],
            'discount_value'          => ['nullable', 'numeric', 'min:0'],
            'promo_code'              => ['nullable', 'string', 'max:64'],
            'wallet_amount'           => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id'                           => 'store',
            'items'                              => 'order items',
            'items.*.store_product_variant_id'   => 'product variant',
            'items.*.quantity'                   => 'item quantity',
            'items.*.addons'                     => 'item add-ons',
            'items.*.addons.*.addon_group_id'    => 'add-on group',
            'items.*.addons.*.addon_item_id'     => 'add-on item',
            'customer_id'                        => 'customer',
            'walkin_customer_name'               => 'walk-in customer name',
            'walkin_customer_mobile'             => 'walk-in customer mobile',
            'order_note'                         => 'order note',
            'amount'                             => 'session amount',
            'cash_portion'                       => 'cash portion',
            'discount_type'                      => 'discount type',
            'discount_value'                     => 'discount value',
            'promo_code'                         => 'promo code',
            'wallet_amount'                      => 'wallet amount',
        ];
    }
}
