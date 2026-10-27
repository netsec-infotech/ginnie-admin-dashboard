<?php

namespace App\Http\Resources\User;

use App\Enums\Wallet\WalletTypeEnum;
use App\Models\Wallet;
use App\Services\PaymentCurrencyAvailabilityService;
use App\Models\Setting;
use App\Services\DeliveryScheduleService;
use App\Support\Currency\CurrencyDisplay;
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
        $currencyCodes = $cart->items
            ->pluck('store.currency_code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values();
        $cartCurrencyCode = $currencyCodes->count() === 1 ? $currencyCodes->first() : null;
        $walletCurrencyCode = Wallet::where('user_id', $cart->user_id)
            ->ofType(WalletTypeEnum::CUSTOMER())
            ->value('currency_code');

        return [
            'id' => $cart->id,
            'uuid' => $cart->uuid,
            'user_id' => $cart->user_id,
            'use_loyalty_points' => (bool) ($cart->use_loyalty_points ?? false),
            'items_count' => $cart->items->count(),
            'total_quantity' => $cart->items->sum('quantity'),
            'currency_code' => $cartCurrencyCode,
            'currency_codes' => $currencyCodes->all(),
            'currency_label' => $cartCurrencyCode ? CurrencyDisplay::label($cartCurrencyCode) : null,
            'available_payment_methods' => $cartCurrencyCode
                ? app(PaymentCurrencyAvailabilityService::class)->availabilityForCurrency($cartCurrencyCode, [], $walletCurrencyCode)
                : [],
            'items' => CartItemResource::collection($cart->items),
            'payment_summary' => $this->when(isset($cart->payment_summary), function () use ($cart, $cartCurrencyCode, $currencyCodes) {
                $summary = $cart->payment_summary;
                if (is_array($summary)) {
                    $summary['currency_code'] = $cartCurrencyCode;
                    $summary['currency_codes'] = $currencyCodes->all();
                }

                return new PaymentSummaryResource($summary);
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
                    'currency_code' => $store->currency_code,
                    'currency_label' => CurrencyDisplay::label($store->currency_code),
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
