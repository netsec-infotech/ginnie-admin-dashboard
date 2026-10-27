<?php

namespace App\Http\Resources\Seller\Returns;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Support\Currency\CurrencySnapshotPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerOrderReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array for seller returns listing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orderItem = $this->orderItem;
        $order = $this->order;
        $user = $this->user;
        $store = $this->store;

        $product = $orderItem?->product;
        $variant = $orderItem?->variant;

        $returnStatus = $this->return_status instanceof OrderItemReturnStatusEnum
            ? $this->return_status->value
            : (string) $this->return_status;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $order?->order_id ?? (string) $this->order_id,
            'order_item_id' => $this->order_item_id,
            'return_status' => $returnStatus,
            'return_status_label' => ucfirst(str_replace('_', ' ', strtolower($returnStatus))),
            'pickup_status' => $this->pickup_status,
            'is_disputed' => $returnStatus === OrderItemReturnStatusEnum::DISPUTED() || ! empty($this->disputed_at),
            'dispute_reason' => $this->dispute_reason,
            'refund_amount' => (float) ($this->refund_amount ?? 0),
            'currency_code' => $order?->currency_code,
            'currency_snapshot' => CurrencySnapshotPayload::order($order),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'image' => $user->image_url ?? $user->image,
            ] : null,
            'product' => [
                'id' => $product?->id,
                'title' => $orderItem?->title ?? 'Product',
                'variant_title' => $variant?->title ?? $orderItem?->variant_name,
                'image' => $product?->main_image ?? $orderItem?->image,
                'quantity' => (int) ($orderItem?->quantity ?? 1),
            ],
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
            ] : null,
        ];
    }
}
