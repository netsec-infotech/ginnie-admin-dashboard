<?php

namespace App\Http\Resources\User;

use App\Support\Currency\CurrencySnapshotPayload;
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
        $currencyCode = $this['currency_code'] ?? null;

        return [
            'currency_code' => $currencyCode,
            'currency_codes' => $this['currency_codes'] ?? [],
            'items_total' => $this['items_total'] ?? 0,
            'formatted_items_total' => CurrencySnapshotPayload::formatAmount($this['items_total'] ?? 0, $currencyCode),
            'per_store_drop_off_fee' => $this['per_store_drop_off_fee'] ?? 0,
            'formatted_per_store_drop_off_fee' => CurrencySnapshotPayload::formatAmount($this['per_store_drop_off_fee'] ?? 0, $currencyCode),
            'is_rush_delivery' => $this['is_rush_delivery'] ?? false,
            'is_rush_delivery_available' => $this['is_rush_delivery_available'] ?? false,
            'delivery_charges' => $this['delivery_charges'] ?? 0,
            'formatted_delivery_charges' => CurrencySnapshotPayload::formatAmount($this['delivery_charges'] ?? 0, $currencyCode),
            'regular_delivery_charge' => $this['regular_delivery_charge'] ?? 0,
            'formatted_regular_delivery_charge' => CurrencySnapshotPayload::formatAmount($this['regular_delivery_charge'] ?? 0, $currencyCode),
            'rush_delivery_charge' => $this['rush_delivery_charge'] ?? 0,
            'formatted_rush_delivery_charge' => CurrencySnapshotPayload::formatAmount($this['rush_delivery_charge'] ?? 0, $currencyCode),
            'handling_charges' => $this['handling_charges'] ?? 0,
            'formatted_handling_charges' => CurrencySnapshotPayload::formatAmount($this['handling_charges'] ?? 0, $currencyCode),
            'cod_charges' => round($this['cod_charges'] ?? 0, 2),
            'formatted_cod_charges' => CurrencySnapshotPayload::formatAmount(round($this['cod_charges'] ?? 0, 2), $currencyCode),
            'rider_tip_amount' => round($this['rider_tip_amount'] ?? 0, 2),
            'formatted_rider_tip_amount' => CurrencySnapshotPayload::formatAmount(round($this['rider_tip_amount'] ?? 0, 2), $currencyCode),
            'delivery_distance_charges' => $this['delivery_distance_charges'] ?? 0,
            'formatted_delivery_distance_charges' => CurrencySnapshotPayload::formatAmount($this['delivery_distance_charges'] ?? 0, $currencyCode),
            'delivery_distance_km' => $this['delivery_distance_km'] ?? 0,
            'total_stores' => $this['total_stores'] ?? 0,
            'total_delivery_charges' => $this['total_delivery_charges'] ?? 0,
            'formatted_total_delivery_charges' => CurrencySnapshotPayload::formatAmount($this['total_delivery_charges'] ?? 0, $currencyCode),
            'estimated_delivery_time' => $this['estimated_delivery_time'] ?? 0,
            'use_wallet' => $this['use_wallet'] ?? false,
            'promo_code' => $this['promo_code'] ?? '',
            'promo_discount' => $this['promo_discount'] ?? 0,
            'formatted_promo_discount' => CurrencySnapshotPayload::formatAmount($this['promo_discount'] ?? 0, $currencyCode),
            'promo_applied' => $this['promo_applied'] ?? [],
            'promo_error' => $this['promo_error'] ?? null,
            'loyalty_points_discount_amount' => round($this['loyalty_summary']['loyalty_points_discount_amount'] ?? 0, 2),
            'formatted_loyalty_points_discount_amount' => CurrencySnapshotPayload::formatAmount(round($this['loyalty_summary']['loyalty_points_discount_amount'] ?? 0, 2), $currencyCode),
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
            'formatted_wallet_balance' => CurrencySnapshotPayload::formatAmount(round($this['wallet_balance'] ?? 0, 2), $currencyCode),
            'wallet_amount_used' => round($this['wallet_amount_used'] ?? 0, 2),
            'formatted_wallet_amount_used' => CurrencySnapshotPayload::formatAmount(round($this['wallet_amount_used'] ?? 0, 2), $currencyCode),
            'payable_amount' => round($this['payable_amount'] ?? 0, 2),
            'formatted_payable_amount' => CurrencySnapshotPayload::formatAmount(round($this['payable_amount'] ?? 0, 2), $currencyCode),
        ];
    }
}
