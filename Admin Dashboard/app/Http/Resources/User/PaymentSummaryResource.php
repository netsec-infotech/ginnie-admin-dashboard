<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items_total' => $this['items_total'] ?? 0,
            'per_store_drop_off_fee' => $this['per_store_drop_off_fee'] ?? 0,
            'is_rush_delivery' => $this['is_rush_delivery'] ?? false,
            'is_rush_delivery_available' => $this['is_rush_delivery_available'] ?? false,
            'delivery_charges' => $this['delivery_charges'] ?? 0,
            'regular_delivery_charge' => $this['regular_delivery_charge'] ?? 0,
            'rush_delivery_charge' => $this['rush_delivery_charge'] ?? 0,
            'handling_charges' => $this['handling_charges'] ?? 0,
            'cod_charges' => round($this['cod_charges'] ?? 0, 2),
            'rider_tip_amount' => round($this['rider_tip_amount'] ?? 0, 2),
            'delivery_distance_charges' => $this['delivery_distance_charges'] ?? 0,
            'delivery_distance_km' => $this['delivery_distance_km'] ?? 0,
            'total_stores' => $this['total_stores'] ?? 0,
            'total_delivery_charges' => $this['total_delivery_charges'] ?? 0,
            'estimated_delivery_time' => $this['estimated_delivery_time'] ?? 0,
            'use_wallet' => $this['use_wallet'] ?? false,
            'promo_code' => $this['promo_code'] ?? '',
            'promo_discount' => $this['promo_discount'] ?? 0,
            'promo_applied' => $this['promo_applied'] ?? [],
            'promo_error' => $this['promo_error'] ?? null,
            'loyalty_points_discount_amount' => round($this['loyalty_summary']['loyalty_points_discount_amount'] ?? 0, 2),
            'loyalty_points_redeemed' => (int) ($this['loyalty_summary']['loyalty_points_redeemed'] ?? 0),
            'loyalty_summary' => $this['loyalty_summary'] ?? [
                'is_loyalty_enabled' => false,
                'user_points_balance' => 0,
                'min_order_value_to_redeem' => 0.0,
                'is_eligible_for_redemption' => false,
                'max_points_redeemable' => 0,
                'loyalty_points_redeemed' => 0,
                'loyalty_points_discount_amount' => 0.0,
                'potential_points_to_earn' => 0,
            ],
            'tipping_config' => $this['tipping_config'] ?? null,
            'wallet_balance' => round($this['wallet_balance'] ?? 0, 2),
            'wallet_amount_used' => round($this['wallet_amount_used'] ?? 0, 2),
            'payable_amount' => round($this['payable_amount'] ?? 0, 2),
        ];
    }
}
