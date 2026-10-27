<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateSellerOrderItemStatusRequest extends FormRequest
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
            'status' => 'required|string|in:accept,reject,preparing',
            'order_item_ids' => 'required_without:order_id|array|min:1',
            'order_item_ids.*' => 'integer|exists:order_items,id',
            'order_id' => 'required_without:order_item_ids|integer|exists:orders,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('labels.status') ?? 'status',
            'order_item_ids' => __('labels.order_items') ?? 'order items',
            'order_id' => __('labels.order') ?? 'order',
        ];
    }
}
