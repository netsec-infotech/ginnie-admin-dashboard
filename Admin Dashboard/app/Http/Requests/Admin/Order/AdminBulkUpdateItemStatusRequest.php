<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;

class AdminBulkUpdateItemStatusRequest extends FormRequest
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
            'item_ids'             => 'required|array|min:1',
            'item_ids.*'           => 'required|integer|distinct|exists:order_items,id',
            'status'               => 'required|string|in:accept,reject,preparing,collected,delivered,delivery_failed,confirm_return',
            'remark'               => 'nullable|string|max:1000',
            // Only required when status === delivery_failed. The service rejects
            // missing-reason rows individually so we keep this nullable here.
            'delivery_fail_reason' => 'nullable|string|in:customer_unavailable,customer_refused,wrong_address,unsafe_location',
        ];
    }

    public function attributes(): array
    {
        return [
            'item_ids'             => __('labels.order_items'),
            'item_ids.*'           => __('labels.order_item'),
            'status'               => __('labels.status'),
            'remark'               => __('labels.remark'),
            'delivery_fail_reason' => __('labels.delivery_fail_reason'),
        ];
    }
}
