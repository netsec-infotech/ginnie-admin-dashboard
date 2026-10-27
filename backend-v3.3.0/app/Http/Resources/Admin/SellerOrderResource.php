<?php

namespace App\Http\Resources\Admin;

use App\Support\Currency\CurrencySnapshotPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sellerPromoDiscount = $this->relationLoaded('items')
            ? (float) $this->items->sum(fn ($item) => $item->orderItem?->promo_discount ?? 0)
            : 0;

        $subtotal = (float) $this->total_price;

        $parent = $this->order;
        $handlingCharges = 0;
        $walletBalance = 0;
        $deliveryCharge = 0;
        $perStoreDropOffFee = 0;

        if ($parent) {
            $sellerOrdersCount = $parent->sellerOrders()->count();
            if ($sellerOrdersCount <= 1) {
                $handlingCharges = (float) $parent->handling_charges;
                $walletBalance = (float) $parent->wallet_balance;
                $deliveryCharge = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->delivery_charge;
                $perStoreDropOffFee = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->per_store_drop_off_fee;
            } else {
                $parentSubtotal = (float) $parent->subtotal;
                if ($parentSubtotal > 0) {
                    $ratio = $subtotal / $parentSubtotal;
                    $handlingCharges = (float) $parent->handling_charges * $ratio;
                    $walletBalance = (float) $parent->wallet_balance * $ratio;
                    $deliveryCharge = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->delivery_charge * $ratio;
                    $perStoreDropOffFee = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->per_store_drop_off_fee * $ratio;
                }
            }
        }

        $finalTotal = max(0.0, $subtotal + $handlingCharges + $deliveryCharge + $perStoreDropOffFee - $sellerPromoDiscount);
        $totalPayable = max(0.0, $finalTotal - $walletBalance);

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'seller_id' => $this->seller_id,
            'total_price' => (int) round($totalPayable),
            'status' => $this->status,
            'currency_snapshot' => CurrencySnapshotPayload::order($parent),

            // Relationships
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'uuid' => $this->order->uuid,
                    'email' => $this->order->email,
                    'payment_method' => $this->order->payment_method,
                    'payment_status' => $this->order->payment_status,
                    'currency_code' => $this->order->currency_code,
                    'currency_rate' => $this->order->currency_rate,
                    'base_currency_code' => $this->order->base_currency_code,
                    'display_currency_code' => $this->order->display_currency_code,
                    'display_exchange_rate' => $this->order->display_exchange_rate,
                    'currency_snapshot' => CurrencySnapshotPayload::order($this->order),
                    'status' => $this->order->status,
                    'billing_name' => $this->order->billing_name,
                    'billing_phone' => $this->order->billing_phone,
                    'shipping_name' => $this->order->shipping_name,
                    'shipping_address_1' => $this->order->shipping_address_1,
                    'shipping_address_2' => $this->order->shipping_address_2,
                    'shipping_landmark' => $this->order->shipping_landmark,
                    'shipping_city' => $this->order->shipping_city,
                    'shipping_state' => $this->order->shipping_state,
                    'shipping_zip' => $this->order->shipping_zip,
                    'shipping_country' => $this->order->shipping_country,
                    'shipping_phone' => $this->order->shipping_phone,
                    'reschedule_status' => $this->order->reschedule_status,
                    'reschedule_reason' => $this->order->reschedule_reason,
                    'reschedule_deadline' => $this->order->reschedule_deadline?->toISOString(),
                    'is_reschedule_pending' => $this->order->reschedule_status === 'needs_reschedule',
                    'created_at' => $this->order->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'seller' => $this->whenLoaded('seller', function () {
                return [
                    'id' => $this->seller->id,
                    'name' => $this->seller->name,
                ];
            }),
            'items' => SellerOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
