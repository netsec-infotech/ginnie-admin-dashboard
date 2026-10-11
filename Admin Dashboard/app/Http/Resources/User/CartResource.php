<?php

namespace App\Http\Resources\User;

use App\Models\Setting;
use App\Services\DeliveryScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cart = $this['cart'] ?? $this;

        return [
            'id' => $cart->id,
            'uuid' => $cart->uuid,
            'user_id' => $cart->user_id,
            'use_loyalty_points' => (bool) ($cart->use_loyalty_points ?? false),
            'items_count' => $cart->items->count(),
            'total_quantity' => $cart->items->sum('quantity'),
            'items' => CartItemResource::collection($cart->items),
            'payment_summary' => $this->when(isset($cart->payment_summary), function () use ($cart) {
                return new PaymentSummaryResource($cart->payment_summary);
            }),
            'removed_items' => $this['removed_items'] ?? [],
            'removed_count' => $this['removed_count'],
            'delivery_zone' => $this['delivery_zone'],
            'stores' => $cart->items->pluck('store')->filter()->unique('id')->values()->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'logo' => $store->store_logo,
                    'is_active' => (bool) $store->is_active,
                    'status' => optional($store)->checkStoreStatus() ?? [],
                    'is_scheduled_delivery_enabled' => (bool) $store->is_scheduled_delivery_enabled && Setting::isScheduledDeliveryEnabled(),
                    'scheduled_delivery_info' => app(DeliveryScheduleService::class)->getAvailableDatesForStore($store),
                ];
            })->all(),

            'created_at' => $cart->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $cart->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
