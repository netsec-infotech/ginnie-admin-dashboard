<?php

namespace App\Http\Requests\Seller\Order;

use Illuminate\Foundation\Http\FormRequest;

class SellerBulkUpdateItemStatusRequest extends FormRequest
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
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'required|integer|distinct|exists:order_items,id',
            // Sellers can only drive the seller-side transitions; rider/admin-only
            // actions are deliberately absent from this allowlist.
            'status'     => 'required|string|in:accept,reject,preparing,confirm_return',
        ];
    }

    public function attributes(): array
    {
        return [
            'item_ids'   => __('labels.order_items'),
            'item_ids.*' => __('labels.order_item'),
            'status'     => __('labels.status'),
        ];
    }
}
