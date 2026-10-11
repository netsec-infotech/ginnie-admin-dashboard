<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'seller_id' => $this->seller_id,
            'store_id' => $this->store_id,
            'delivery_boy_id' => $this->delivery_boy_id,

            'reason' => $this->reason,
            'return_reason_id' => $this->return_reason_id,
            'return_reason_name' => $this->return_reason_name,
            'snapshot_otp_required' => (bool) $this->snapshot_otp_required,
            'snapshot_proof_required' => (bool) $this->snapshot_proof_required,
            'seller_comment' => $this->seller_comment,
            'images' => $this->images,
            'refund_amount' => (float) $this->refund_amount,

            'pickup_status' => $this->pickup_status,
            'pickup_otp' => $this->pickup_otp,
            'pickup_otp_verified' => (bool) $this->pickup_otp_verified,
            'return_status' => $this->return_status,
            'store_rejection_reason' => $this->store_rejection_reason,
            'action_on_rejection' => $this->action_on_rejection,
            'rejection_reason_id' => $this->rejection_reason_id,
            'rejection_reason_name' => $this->rejection_reason_name,
            'responsible_party' => $this->responsible_party instanceof \BackedEnum ? $this->responsible_party->value : $this->responsible_party,
            'deduct_from_rider' => (bool) $this->deduct_from_rider,
            'deduction_amount' => $this->deduction_amount ? (float) $this->deduction_amount : null,
            'pickup_images' => $this->pickup_images,
            'rejection_photos' => $this->rejection_photos,
            'rejection_video' => $this->rejection_video,

            'seller_approved_at' => optional($this->seller_approved_at)->toDateTimeString(),
            'picked_up_at' => optional($this->picked_up_at)->toDateTimeString(),
            'received_at' => optional($this->received_at)->toDateTimeString(),
            'store_confirmed_at' => optional($this->store_confirmed_at)->toDateTimeString(),
            'store_rejected_at' => optional($this->store_rejected_at)->toDateTimeString(),
            'refund_processed_at' => optional($this->refund_processed_at)->toDateTimeString(),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
