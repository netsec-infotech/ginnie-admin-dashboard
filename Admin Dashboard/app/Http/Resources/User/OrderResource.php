<?php

namespace App\Http\Resources\User;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Http\Resources\OrderSellerFeedbackResource;
use App\Models\Setting;
use App\Services\DeliveryBoyService;
use App\Services\DeliveryScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDeliveryFeedbackGiven = DeliveryBoyService::checkDeliveryBoyFeedbackByOrderId(orderId: $this->id, deliveryBoyId: $this->delivery_boy_id);
        if ($isDeliveryFeedbackGiven) {
            $deliveryFeedback = DeliveryBoyService::getDeliveryBoyFeedbackByOrderId(orderId: $this->id, deliveryBoyId: $this->delivery_boy_id);
        }

        // For display only: include cancelled/rejected items amount back into subtotal and final_total
        // The stored order totals exclude these items, but the API should show amounts including them.
        //
        // RETURNING_TO_STORE + DELIVERY_FAILED items are physically being brought
        // back to the seller — they're already excluded from the stored subtotal
        // (recalculateOrderAmounts treats them as out-of-basket), so they belong
        // in this list too.
        $terminalStatuses = [
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
        ];

        $cancelledRejectedAmount = (float) ($this->items()
            ->whereIn('status', $terminalStatuses)
            ->sum('subtotal'));

        // When every item is cancelled/rejected, drop `total_payable` to 0 for display
        // (charges included). The stored value keeps whatever the backend logic wrote.
        $totalItemsCount = (int) $this->items()->count();
        $terminalItemsCount = (int) $this->items()
            ->whereIn('status', $terminalStatuses)
            ->count();
        $allItemsTerminal = $totalItemsCount > 0 && $terminalItemsCount === $totalItemsCount;

        $displaySubtotal = (float) $this->subtotal + $cancelledRejectedAmount;
        $displayFinalTotal = (float) $this->final_total + $cancelledRejectedAmount;
        $displayTotalPayable = $allItemsTerminal ? 0 : (int) round($this->total_payable);
        $displayStatus = $this->resolveDisplayStatus();

        $store = $this->relationLoaded('items')
            ? $this->items->first()?->store
            : $this->items()->first()?->store;

        return [
            'id' => $this->id,
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
                'logo' => $store->store_logo,
                'is_scheduled_delivery_enabled' => (bool) ($store->is_scheduled_delivery_enabled && Setting::isScheduledDeliveryEnabled()),
                'max_advance_booking_days' => (int) ($store->max_advance_booking_days ?? 7),
                'available_days' => ($store->is_scheduled_delivery_enabled && Setting::isScheduledDeliveryEnabled())
                    ? (app(DeliveryScheduleService::class)->getAvailableDatesForStore($store)['available_days'] ?? [])
                    : [],
            ] : null,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'user_id' => $this->user_id,
            'email' => $this->email,
            'currency_code' => $this->currency_code,
            'currency_rate' => $this->currency_rate,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'status' => $displayStatus,
            'status_label' => formatOrderStatusLabel($displayStatus),
            'invoice' => URL::signedRoute('order-invoice', ['id' => $this->uuid]) ?? '',
            'fulfillment_type' => $this->fulfillment_type,
            'delivery_type' => $this->sellerOrders->first()?->delivery_type?->value ?? 'delivery',

            'pickup_instructions' => $this->items->first()?->store?->pickup_instructions,
            'is_rush_order' => $this->is_rush_order,
            'estimated_delivery_time' => $this->isScheduledDelivery() ? null : $this->estimated_delivery_time,
            'is_scheduled_delivery' => $this->isScheduledDelivery(),
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'scheduled_time_slot_label' => $this->resolveScheduledSlotLabel(),
            'delivery_time_slot_id' => $this->isScheduledDelivery() ? null : $this->delivery_time_slot_id,
            'reschedule_status' => $this->reschedule_status,
            'reschedule_reason' => $this->reschedule_reason,
            'reschedule_deadline' => $this->reschedule_deadline?->toISOString(),
            'delivery_boy_id' => $this->delivery_boy_id,
            'delivery_boy_name' => $this->deliveryBoy->full_name ?? '',
            'delivery_boy_phone' => (float) ($this->deliveryBoy->user->mobile ?? 0),
            'delivery_boy_profile' => $this->deliveryBoy->user->profile_image ?? '',
            'is_delivery_feedback_given' => $isDeliveryFeedbackGiven,
            'delivery_feedback' => $deliveryFeedback ?? null,
            'wallet_balance_used' => $this->wallet_balance ?? '0.00',
            'promo_code' => $this->promo_code,
            'promo_discount' => $this->promo_discount,
            'gift_card' => $this->gift_card,
            'gift_card_discount' => $this->gift_card_discount,
            'delivery_charge' => $this->delivery_charge,
            'handling_charges' => $this->handling_charges,
            'cod_charges' => (function () {
                $paymentMethod = is_callable($this->payment_method) ? $this->payment_method() : ($this->payment_method->value ?? $this->payment_method);
                if ($paymentMethod === 'cod') {
                    $paymentSetting = app(\App\Services\SettingService::class)->getSettingByVariable(\App\Enums\SettingTypeEnum::PAYMENT());
                    $settings = $paymentSetting ? $paymentSetting->toArray(request())['value'] ?? [] : [];
                    if (! empty($settings['cod'])) {
                        $raw = $settings['cod_charges'] ?? $settings['codCharges'] ?? 0;
                        return is_numeric($raw) ? (float) $raw : 0.0;
                    }
                }
                return 0.0;
            })(),
            'per_store_drop_off_fee' => $this->per_store_drop_off_fee,
            'rider_tip_amount' => (float) ($this->rider_tip_amount ?? 0),
            'loyalty_points_redeemed' => (int) ($this->loyalty_points_redeemed ?? 0),
            'loyalty_points_discount_amount' => (float) ($this->loyalty_points_discount_amount ?? 0.00),
            'loyalty_points_earned' => (int) ($this->loyalty_points_earned ?? 0),
            'tipping_config' => Setting::getRiderTipSettings(),
            // Display values include cancelled/rejected items for API visibility only
            'subtotal' => (string) $displaySubtotal,
            'total_payable' => (string) $displayTotalPayable,
            'final_total' => (string) $displayFinalTotal,

            // Billing info
            //            'billing_name' => $this->billing_name,
            //            'billing_address_1' => $this->billing_address_1,
            //            'billing_address_2' => $this->billing_address_2,
            //            'billing_landmark' => $this->billing_landmark,
            //            'billing_zip' => $this->billing_zip,
            //            'billing_phone' => $this->billing_phone,
            //            'billing_address_type' => $this->billing_address_type,
            //            'billing_latitude' => $this->billing_latitude,
            //            'billing_longitude' => $this->billing_longitude,
            //            'billing_city' => $this->billing_city,
            //            'billing_state' => $this->billing_state,
            //            'billing_country' => $this->billing_country,
            //            'billing_country_code' => $this->billing_country_code,

            // Shipping info
            'shipping_name' => $this->shipping_name,
            'shipping_address_1' => $this->shipping_address_1,
            'shipping_address_2' => $this->shipping_address_2,
            'shipping_landmark' => $this->shipping_landmark,
            'shipping_zip' => $this->shipping_zip,
            'shipping_phone' => $this->shipping_phone,
            'shipping_address_type' => $this->shipping_address_type,
            'shipping_latitude' => $this->shipping_latitude,
            'shipping_longitude' => $this->shipping_longitude,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_country' => $this->shipping_country,
            'shipping_country_code' => $this->shipping_country_code,
            'order_note' => $this->order_note ?? '',

            // Relationships
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'seller_feedbacks' => OrderSellerFeedbackResource::collection($this->whenLoaded('sellerOrders')),
            'promo_line' => new PromoLineResource($this->whenLoaded('promoLine')),
            'user' => $this->when($this->relationLoaded('user'), function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'payment_response' => $this->payment_response ?? null,
        ];
    }

    private function resolveDisplayStatus(): string
    {
        if ($this->status !== OrderStatusEnum::PARTIALLY_ACCEPTED()) {
            return $this->status;
        }

        $itemStatuses = $this->relationLoaded('items')
            ? $this->items->pluck('status')->all()
            : $this->items()->pluck('status')->all();

        if (
            in_array(OrderItemStatusEnum::AWAITING_STORE_RESPONSE(), $itemStatuses, true)
            && in_array(OrderItemStatusEnum::REJECTED(), $itemStatuses, true)
        ) {
            return OrderStatusEnum::PARTIALLY_REJECTED();
        }

        return $this->status;
    }

    private function resolveScheduledSlotLabel(): ?string
    {
        if (! empty($this->scheduled_slot_start_time) && ! empty($this->scheduled_slot_end_time)) {
            return $this->formatScheduledTime($this->scheduled_slot_start_time).' - '.$this->formatScheduledTime($this->scheduled_slot_end_time);
        }

        return $this->scheduled_time_slot_label;
    }

    private function formatScheduledTime(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }
}
