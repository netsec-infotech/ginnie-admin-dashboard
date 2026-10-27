<?php

namespace App\Http\Resources\Seller\Returns;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Services\ReturnRequestService;
use App\Support\Currency\CurrencySnapshotPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerOrderReturnDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array for seller return details.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orderItem = $this->orderItem;
        $order = $this->order;
        $user = $this->user;
        $store = $this->store;
        $deliveryBoy = $this->deliveryBoy;
        $riderUser = $deliveryBoy?->user;

        $product = $orderItem?->product;
        $variant = $orderItem?->variant;

        $returnStatus = $this->return_status instanceof OrderItemReturnStatusEnum
            ? $this->return_status->value
            : (string) $this->return_status;

        $returnService = app(ReturnRequestService::class);
        $refundBreakdown = $returnService->refundBreakdown($this->resource);
        $timelineSteps = $returnService->timelineSteps($this->resource);

        // Allowed actions on mobile
        $isPendingInitialReview = in_array($returnStatus, [
            OrderItemReturnStatusEnum::REQUESTED(),
            'requested',
        ], true);

        $isPendingStoreConfirmation = in_array($returnStatus, [
            OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
            OrderItemReturnStatusEnum::PICKED_UP(),
            OrderItemReturnStatusEnum::SELLER_APPROVED(),
        ], true) && ! in_array($returnStatus, [
            OrderItemReturnStatusEnum::COMPLETED(),
            OrderItemReturnStatusEnum::DISPUTED(),
            OrderItemReturnStatusEnum::SELLER_REJECTED(),
            OrderItemReturnStatusEnum::CANCELLED(),
        ], true);

        $addons = $orderItem && $orderItem->relationLoaded('addons')
            ? $orderItem->addons->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title ?? $a->addonItem?->title ?? 'Addon',
                'price' => (float) ($a->price ?? 0),
            ])->values()->all()
            : [];

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
            'disputed_at' => $this->disputed_at?->toIso8601String(),
            'refund_amount' => (float) ($this->refund_amount ?? 0),
            'currency_code' => $order?->currency_code,
            'currency_snapshot' => CurrencySnapshotPayload::order($order),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'email' => $user->email,
                'image' => $user->image_url ?? $user->image,
            ] : null,
            'product' => [
                'id' => $product?->id,
                'title' => $orderItem?->title ?? 'Product',
                'variant_title' => $variant?->title ?? $orderItem?->variant_name,
                'image' => $product?->main_image ?? $orderItem?->image,
                'quantity' => (int) ($orderItem?->quantity ?? 1),
                'unit_price' => (float) ($orderItem?->discounted_price ?: $orderItem?->price ?: 0),
                'addons' => $addons,
            ],
            'customer_request' => [
                'reason_id' => $this->return_reason_id,
                'reason_name' => $this->return_reason_name ?? $this->returnReason?->title ?? $this->reason,
                'reason_description' => $this->reason,
                'proof_images' => $this->images ?? [],
            ],
            'delivery_pickup' => [
                'rider_id' => $deliveryBoy?->id,
                'rider_name' => $riderUser?->name,
                'rider_mobile' => $riderUser?->mobile,
                'pickup_otp_required' => (bool) $this->snapshot_otp_required,
                'pickup_otp_verified' => (bool) $this->pickup_otp_verified,
                'pickup_condition' => $this->pickup_condition,
                'pickup_remarks' => $this->pickup_remarks,
                'pickup_photos' => $this->pickup_images ?? [],
                'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            ],
            'store_confirmation' => [
                'received_at' => $this->received_at?->toIso8601String(),
                'seller_comment' => $this->seller_comment,
                'inspection_photos' => $this->inspection_photos ?? [],
                'seller_approved_at' => $this->seller_approved_at?->toIso8601String(),
                'refund_processed_at' => $this->refund_processed_at?->toIso8601String(),
            ],
            'refund_breakdown' => $refundBreakdown,
            'timeline' => $timelineSteps,
            'allowed_actions' => [
                'can_decide' => $isPendingInitialReview,
                'can_confirm_receipt' => $isPendingStoreConfirmation,
            ],
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
            ] : null,
        ];
    }
}
