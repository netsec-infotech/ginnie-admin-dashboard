<?php

namespace App\Http\Resources;

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum;
use App\Enums\DeliveryBoy\EarningPaymentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Resources\User\PromoLineResource;
use App\Models\Order;
use App\Models\Setting;
use App\Services\DeliveryScheduleService;
use App\Services\OrderService;
use App\Support\Currency\CurrencySnapshotPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Check if this is a SellerOrder or an Order
        $isSellerOrder = get_class($this->resource) === 'App\Models\SellerOrder';

        if ($isSellerOrder) {
            $sellerPromoDiscount = $this->relationLoaded('items')
                ? (float) $this->items->sum(fn ($item) => $item->orderItem?->promo_discount ?? 0)
                : 0;

            $subtotal = (float) $this->total_price;

            $parent = $this->order;
            $firstOrderItem = $this->relationLoaded('items')
                ? $this->items->first()?->orderItem
                : null;
            $acceptanceTimeout = $parent
                ? app(OrderService::class)->acceptanceTimeoutPayloadForOrder($parent, $firstOrderItem)
                : null;
            $handlingCharges = 0;
            $walletBalance = 0;
            $deliveryCharge = 0;
            $perStoreDropOffFee = 0;
            $riderTipAmount = 0;

            if ($parent) {
                $sellerOrders = $parent->relationLoaded('sellerOrders')
                    ? $parent->sellerOrders
                    : $parent->sellerOrders()->get();
                $sellerOrdersCount = $sellerOrders->count();

                if ($sellerOrdersCount <= 1) {
                    $handlingCharges = (float) $parent->handling_charges;
                    $walletBalance = (float) $parent->wallet_balance;
                    $deliveryCharge = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->delivery_charge;
                    $perStoreDropOffFee = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->per_store_drop_off_fee;
                    $riderTipAmount = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->rider_tip_amount;
                } else {
                    $sellerOrdersSubtotal = (float) $sellerOrders->sum('total_price');
                    if ($sellerOrdersSubtotal > 0) {
                        $ratio = $subtotal / $sellerOrdersSubtotal;
                        $handlingCharges = (float) $parent->handling_charges * $ratio;
                        $walletBalance = (float) $parent->wallet_balance * $ratio;
                        $deliveryCharge = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->delivery_charge * $ratio;
                        $perStoreDropOffFee = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->per_store_drop_off_fee * $ratio;
                        $riderTipAmount = (($this->delivery_type?->value ?? $this->delivery_type) === 'pickup') ? 0 : (float) $parent->rider_tip_amount * $ratio;
                    }
                }
            }

            $finalTotal = max(0.0, $subtotal + $handlingCharges + $deliveryCharge + $perStoreDropOffFee + $riderTipAmount - $sellerPromoDiscount);
            $walletBalance = min($walletBalance, $finalTotal);
            $totalPayable = max(0.0, $finalTotal - $walletBalance);

            $parentItems = $this->order?->relationLoaded('items') ? $this->order->items : null;
            $itemsStatuses = $parentItems
                ? $parentItems->pluck('status')->filter()->toArray()
                : ($this->relationLoaded('items')
                    ? $this->items->map(fn ($item) => $item->orderItem?->status ?? null)->filter()->toArray()
                    : []);

            $terminalStatuses = [
                OrderItemStatusEnum::CANCELLED(),
                OrderItemStatusEnum::REJECTED(),
                OrderItemStatusEnum::RETURNING_TO_STORE(),
                OrderItemStatusEnum::DELIVERY_FAILED(),
                OrderItemStatusEnum::FAILED(),
            ];
            $activeCount = count(array_filter($itemsStatuses, fn ($s) => ! in_array($s, $terminalStatuses, true)));
            $allItemsTerminal = ! empty($itemsStatuses) && $activeCount === 0;

            $sellerOrderStatus = $this->order->status ?? null;
            if ($allItemsTerminal) {
                $sellerOrderStatus = app(OrderService::class)->determineSellerOrderStatus($itemsStatuses);
            } elseif ($this->picked_up_at !== null) {
                $sellerOrderStatus = OrderStatusEnum::DELIVERED();
            } elseif ($this->ready_for_pickup_at !== null) {
                $sellerOrderStatus = OrderStatusEnum::READY_FOR_PICKUP();
            } elseif (! empty($itemsStatuses)) {
                $sellerOrderStatus = app(OrderService::class)->determineSellerOrderStatus($itemsStatuses);
            }
            $store = $this->store ?? $this->items?->first()?->orderItem?->store;

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
                'order_id' => $this->order->id ?? $this->id,
                'uuid' => $this->order->uuid,
                'image' => ! empty($this->items?->first()?->variant?->image) ? $this->items->first()->variant->image : $this->items?->first()?->product?->main_image,
                'email' => $this->order->email,
                'status' => $sellerOrderStatus,
                'status_label' => formatOrderStatusLabel($sellerOrderStatus),
                'payment_method' => $this->order->payment_method,
                'payment_status' => $this->order->payment_status,
                'currency_code' => $this->order->currency_code,
                'currency_rate' => $this->order->currency_rate,
                'base_currency_code' => $this->order->base_currency_code,
                'display_currency_code' => $this->order->display_currency_code,
                'display_exchange_rate' => $this->order->display_exchange_rate,
                'currency_snapshot' => CurrencySnapshotPayload::order($this->order),
                'total_price' => (int) round($totalPayable),
                'delivery_type' => $this->delivery_type?->value ?? 'delivery',
                'ready_for_pickup_at' => $this->ready_for_pickup_at?->format('M d, Y h:i A'),
                'picked_up_at' => $this->picked_up_at?->format('M d, Y h:i A'),

                // Financial Breakdown
                'subtotal' => $subtotal,
                'promo_discount' => $sellerPromoDiscount,
                'wallet_balance' => $walletBalance,
                'handling_charges' => $handlingCharges,
                'delivery_charge' => $deliveryCharge,
                'per_store_drop_off_fee' => $perStoreDropOffFee,
                'rider_tip_amount' => $riderTipAmount,
                'final_total' => $finalTotal,
                'total_payable' => (int) round($totalPayable),
                'promo_code' => $this->order->promo_code ?? null,

                // Customer information
                'billing_name' => $this->order->billing_name,
                'billing_phone' => $this->order->billing_phone,

                // Shipping information
                'shipping_name' => $this->order->shipping_name,
                'shipping_address_1' => $this->order->shipping_address_1,
                'shipping_address_2' => $this->order->shipping_address_2,
                'shipping_landmark' => $this->order->shipping_landmark,
                'shipping_city' => $this->order->shipping_city,
                'shipping_state' => $this->order->shipping_state,
                'shipping_zip' => $this->order->shipping_zip,
                'shipping_country' => $this->order->shipping_country,
                'shipping_phone' => $this->order->shipping_phone,
                'order_note' => $this->order->order_note,
                'is_rush_order' => $this->order->is_rush_order,
                'estimated_delivery_time' => $this->order->isScheduledDelivery() ? null : ($this->order->estimated_delivery_time ?? null),
                'is_scheduled_delivery' => $this->order->isScheduledDelivery(),
                'scheduled_date' => $this->order->scheduled_date?->format('Y-m-d'),
                'delivery_time_slot_id' => $this->order->isScheduledDelivery() ? null : $this->order->delivery_time_slot_id,
                'scheduled_time_slot_label' => $this->resolveScheduledSlotLabel($this->order),
                'reschedule_status' => $this->order->reschedule_status,
                'reschedule_reason' => $this->order->reschedule_reason,
                'reschedule_deadline' => $this->order->reschedule_deadline?->toISOString(),
                'is_reschedule_pending' => $this->order->reschedule_status === 'needs_reschedule',
                'acceptance_timeout' => $acceptanceTimeout,
                'promo_line' => new PromoLineResource($this->whenLoaded('promoLine')),

                // Seller-side delivery context — read-only. The seller doesn't
                // reassign riders (admin-only), but should know who's coming.
                'delivery_zone' => $this->order->relationLoaded('deliveryZone') && $this->order->deliveryZone ? [
                    'id' => $this->order->deliveryZone->id,
                    'name' => $this->order->deliveryZone->name ?? null,
                ] : null,
                'delivery_boy' => $this->order?->deliveryBoy ? [
                    'id' => $this->order->deliveryBoy->id,
                    'full_name' => $this->order->deliveryBoy->full_name ?? null,
                    'email' => $this->order->deliveryBoy->user?->email,
                    'mobile' => $this->order->deliveryBoy->user?->mobile,
                    'status' => $this->resolveDeliveryBoyAssignmentStatus($this->order),
                    'status_label' => formatOrderStatusLabel($this->resolveDeliveryBoyAssignmentStatus($this->order)),
                    'is_blocked' => (bool) ($this->order->deliveryBoy->is_blocked ?? false),
                ] : null,
                'delivery_boy_id' => $this->order->delivery_boy_id ?? null,

                // Items
                'items' => $this->whenLoaded('items', function () {
                    return $this->items->map(function ($item) {
                        $attachments = [];
                        try {
                            $mediaItems = $item->orderItem->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
                            foreach ($mediaItems as $media) {
                                $attachments[] = $media->getUrl();
                            }
                        } catch (\Throwable $e) {
                            // Silently ignore if media library not set up for this resource
                        }

                        return [
                            'id' => $item->id,
                            'image' => ! empty($item->variant?->image) ? $item->variant->image : $item->product?->main_image,
                            'attachments' => $attachments,
                            'orderItem' => [
                                'id' => $item->orderItem->id,
                                'status' => $item->orderItem->status,
                                'status_formatted' => Str::ucfirst(Str::replace('_', ' ', $item->orderItem->status)),
                                'status_label' => formatOrderStatusLabel($item->orderItem->status),
                                'acceptance_timeout' => $this->order
                                    ? app(OrderService::class)->acceptanceTimeoutPayloadForOrder($this->order, $item->orderItem)
                                    : null,
                                'cancellation_reason' => $item->orderItem->cancellation_reason,
                                'delivery_fail_reason' => $item->orderItem->delivery_fail_reason,
                            ],
                            'product' => $item->product ? [
                                'id' => $item->product->id,
                                'title' => $item->orderItem->title,
                                'main_image' => $item->product->main_image,
                            ] : null,
                            'variant' => $item->variant ? [
                                'id' => $item->variant->id,
                                'title' => $item->variant->title,
                                'image' => $item->variant->image,
                            ] : null,
                            'store' => ($item->store ?? $item->orderItem?->store) ? [
                                'id' => ($item->store ?? $item->orderItem?->store)->id,
                                'name' => ($item->store ?? $item->orderItem?->store)->name,
                            ] : null,
                            'price' => $item->price,
                            'tax_amount' => $item->orderItem?->tax_amount,
                            'sub_total' => $item->orderItem?->subtotal,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->price * $item->quantity,
                            // Addon snapshot lives on the OrderItem, not the SellerOrderItem.
                            'addons' => $this->formatOrderItemAddons($item->orderItem),
                            'addons_total' => $this->sumOrderItemAddonsLineTotal($item->orderItem),
                        ];
                    });
                }),

                'created_at' => $this->created_at?->format('M d, Y h:i:s A'),
            ];
        }

        // RETURNING_TO_STORE + DELIVERY_FAILED items have physically left the
        // customer's basket (rider is bringing them back). They must be treated
        // the same as CANCELLED / REJECTED for display-amount purposes —
        // otherwise the customer / admin sees an inflated subtotal while the
        // return is in flight. Final CANCELLED status arrives later via
        // confirm-receipt.
        $terminalStatuses = [
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
            OrderItemStatusEnum::FAILED(),
        ];

        $items = $this->relationLoaded('items') ? $this->items : collect();
        $cancelledRejectedAmount = (float) $items
            ->whereIn('status', $terminalStatuses)
            ->sum('subtotal');
        $activeItemsCount = $items
            ->whereNotIn('status', $terminalStatuses)
            ->count();
        $allItemsTerminal = $items->isNotEmpty() && $activeItemsCount === 0;

        $displayFinalTotal = (float) $this->final_total + $cancelledRejectedAmount;
        $displaySubtotal = (float) $this->subtotal + $cancelledRejectedAmount;
        $displayTotalPayable = $allItemsTerminal ? 0 : (int) round($this->total_payable);
        $displayStatus = $this->resolveDisplayStatus($allItemsTerminal, $items);
        $store = $this->relationLoaded('items')
            ? $this->items->first()?->store
            : $this->items()->first()?->store;

        $codCharges = 0.0;
        $paymentMethod = is_callable($this->payment_method) ? $this->payment_method() : ($this->payment_method->value ?? $this->payment_method);
        if ($paymentMethod === 'cod') {
            $paymentSetting = app(\App\Services\SettingService::class)->getSettingByVariable(\App\Enums\SettingTypeEnum::PAYMENT());
            $settings = $paymentSetting ? $paymentSetting->toArray(request())['value'] ?? [] : [];
            if (! empty($settings['cod'])) {
                $rawCodCharges = $settings['cod_charges'] ?? $settings['codCharges'] ?? 0;
                if (is_numeric($rawCodCharges)) {
                    $codCharges = (float) $rawCodCharges;
                }
            }
        }

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
            'email' => $this->email,
            'status' => $displayStatus,
            'status_label' => formatOrderStatusLabel($displayStatus),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'currency_code' => $this->currency_code,
            'currency_rate' => $this->currency_rate,
            'base_currency_code' => $this->base_currency_code,
            'display_currency_code' => $this->display_currency_code,
            'display_exchange_rate' => $this->display_exchange_rate,
            'currency_snapshot' => CurrencySnapshotPayload::order($this->resource),
            'delivery_type' => $this->sellerOrders->first()?->delivery_type?->value ?? 'delivery',
            'ready_for_pickup_at' => $this->sellerOrders->first()?->ready_for_pickup_at?->format('M d, Y h:i A'),
            'picked_up_at' => $this->sellerOrders->first()?->picked_up_at?->format('M d, Y h:i A'),
            'promo_code' => $this->promo_code,
            'promo_discount' => $this->promo_discount,
            'wallet_balance' => $this->wallet_balance,
            'subtotal' => $displaySubtotal,
            'delivery_charge' => $this->delivery_charge,
            'handling_charges' => $this->handling_charges,
            'cod_charges' => $codCharges,
            'per_store_drop_off_fee' => $this->per_store_drop_off_fee,
            'rider_tip_amount' => (float) ($this->rider_tip_amount ?? 0),
            'loyalty_points_redeemed' => (int) ($this->loyalty_points_redeemed ?? 0),
            'loyalty_points_discount_amount' => (float) ($this->loyalty_points_discount_amount ?? 0.00),
            'loyalty_points_earned' => (int) ($this->loyalty_points_earned ?? 0),
            'total_payable' => $displayTotalPayable,
            'final_total' => $displayFinalTotal,

            // Customer information
            'billing_name' => $this->billing_name,
            'billing_phone' => $this->billing_phone,

            // Shipping information
            'shipping_name' => $this->shipping_name,
            'shipping_address_1' => $this->shipping_address_1,
            'shipping_address_2' => $this->shipping_address_2,
            'shipping_landmark' => $this->shipping_landmark,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_zip' => $this->shipping_zip,
            'shipping_country' => $this->shipping_country,
            'shipping_phone' => $this->shipping_phone,
            'order_note' => $this['order_note'],
            'is_rush_order' => $this->is_rush_order,
            'estimated_delivery_time' => $this->isScheduledDelivery() ? null : ($this->estimated_delivery_time ?? null),
            'is_scheduled_delivery' => $this->isScheduledDelivery(),
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'scheduled_time_slot_label' => $this->resolveScheduledSlotLabel($this->resource),
            'reschedule_status' => $this->reschedule_status,
            'reschedule_reason' => $this->reschedule_reason,
            'reschedule_deadline' => $this->reschedule_deadline?->toISOString(),
            'is_reschedule_pending' => $this->reschedule_status === 'needs_reschedule',
            'is_flagged' => (bool) ($this->is_flagged ?? false),
            'escalation_reasons' => $this->escalation_reasons ?? null,
            'escalated_at' => $this->escalated_at ?? null,
            'promo_line' => new PromoLineResource($this->whenLoaded('promoLine')),
            'delivery_zone' => $this->whenLoaded('deliveryZone', fn () => $this->deliveryZone ? [
                'id' => $this->deliveryZone->id,
                'name' => $this->deliveryZone->name ?? null,
            ] : null),
            'delivery_boy' => $this->whenLoaded('deliveryBoy', fn () => $this->deliveryBoy ? [
                'id' => $this->deliveryBoy->id,
                'full_name' => $this->deliveryBoy->full_name ?? null,
                'email' => $this->deliveryBoy->user?->email,
                'mobile' => $this->deliveryBoy->user?->mobile,
                'status' => $this->resolveDeliveryBoyAssignmentStatus($this->resource),
                'status_label' => formatOrderStatusLabel($this->resolveDeliveryBoyAssignmentStatus($this->resource)),
                'is_blocked' => (bool) ($this->deliveryBoy->is_blocked ?? false),
            ] : null),
            'delivery_boy_id' => $this->delivery_boy_id ?? null,
            // Persisted earnings on the currently-active delivery assignment.
            // Filtered to assignment_type === DELIVERY (return-pickup rows are
            // a separate concept). Falls back to the live calculation off the
            // delivery zone + route when no assignment row exists yet.
            'delivery_assignment' => $this->resolveActiveDeliveryAssignment(),
            // Snapshot of the calculated delivery route (distance + stops) so
            // the admin Delivery card can show the rider's trip context.
            'delivery_route' => $this->resolveDeliveryRouteSnapshot(),

            // Items
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    $attachments = [];
                    try {
                        $mediaItems = $item->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
                        foreach ($mediaItems as $media) {
                            $attachments[] = $media->getUrl();
                        }
                    } catch (\Throwable $e) {
                        // Silently ignore if media library not set up for this resource
                    }

                    return [
                        'id' => $item->id,
                        'image' => ! empty($item->variant?->image) ? $item->variant->image : $item->product?->main_image,
                        'attachments' => $attachments,
                        'orderItem' => [
                            'id' => $item->id,
                            'status' => $item->status,
                            'status_formatted' => Str::ucfirst(Str::replace('_', ' ', $item->status)),
                            'status_label' => formatOrderStatusLabel($item->status),
                            'cancellation_reason' => $item->cancellation_reason,
                            'delivery_fail_reason' => $item->delivery_fail_reason,
                        ],
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'title' => $item->title,
                            'main_image' => $item->product->main_image,
                        ] : null,
                        'variant' => $item->variant ? [
                            'id' => $item->variant->id,
                            'title' => $item->variant->title,
                            'image' => $item->variant->image,
                        ] : null,
                        'store' => $item->store ? [
                            'id' => $item->store->id,
                            'name' => $item->store->name,
                            'city' => $item->store->city ?? null,
                            'state' => $item->store->state ?? null,
                            'address' => $item->store->address ?? null,
                            'contact_email' => $item->store->contact_email ?? null,
                            'contact_number' => $item->store->contact_number ?? null,
                            'seller' => $item->store->seller ? [
                                'id' => $item->store->seller->id,
                                'name' => $item->store->seller->user?->name,
                                'email' => $item->store->seller->user?->email,
                                'mobile' => $item->store->seller->user?->mobile,
                            ] : null,
                        ] : null,
                        'price' => $item->price,
                        'tax_amount' => $item->tax_amount,
                        'sub_total' => $item->subtotal,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->price * $item->quantity,
                        'addons' => $this->formatOrderItemAddons($item),
                        'addons_total' => $this->sumOrderItemAddonsLineTotal($item),
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('M d, Y h:i A'),
        ];
    }

    protected function resolveDeliveryBoyAssignmentStatus(?Order $order): ?string
    {
        if (! $order) {
            return null;
        }

        if ($order->relationLoaded('deliveryBoyAssignments') && $order->deliveryBoyAssignments->isNotEmpty()) {
            $activeAssignment = $order->deliveryBoyAssignments
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->whereNotIn('status', [
                    DeliveryBoyAssignmentStatusEnum::CANCELED(),
                    DeliveryBoyAssignmentStatusEnum::DROPPED(),
                ])
                ->sortByDesc('assigned_at')
                ->first();

            if ($activeAssignment?->status) {
                return $activeAssignment->status;
            }
        }

        return $order->delivery_boy_id ? DeliveryBoyAssignmentStatusEnum::ASSIGNED() : null;
    }

    /**
     * Flatten an OrderItem's addons into a scalar-friendly list. Mirrors the
     * user-side `User/OrderItemResource::formatAddons()` shape so admin and
     * seller panels render the same breakdown.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formatOrderItemAddons($orderItem): array
    {
        if (! $orderItem) {
            return [];
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        if (! $addons || $addons->isEmpty()) {
            return [];
        }

        return $addons->map(function ($addon) {
            $group = $addon->addonGroup;
            $item = $addon->addonItem;

            return [
                'id' => $addon->id,
                'uuid' => $addon->uuid,
                'addon_group_id' => $addon->addon_group_id,
                'addon_item_id' => $addon->addon_item_id,
                'price' => (float) $addon->price,
                'group' => $group ? [
                    'id' => $group->id,
                    'title' => $group->title,
                    'selection_type' => $group->selection_type?->value,
                    'is_required' => (bool) $group->is_required,
                ] : null,
                'item' => $item ? [
                    'id' => $item->id,
                    'title' => $item->title,
                    'indicator' => $item->indicator?->value,
                ] : null,
            ];
        })->values()->all();
    }

    private function resolveDisplayStatus(bool $allItemsTerminal = false, $items = null): string
    {
        if ($allItemsTerminal && $items && $items->isNotEmpty()) {
            $failedCount = $items->where('status', OrderItemStatusEnum::FAILED())->count();
            if ($failedCount === $items->count()) {
                return OrderStatusEnum::FAILED();
            }
            $rejectedCount = $items->where('status', OrderItemStatusEnum::REJECTED())->count();
            if ($rejectedCount === $items->count()) {
                return OrderStatusEnum::REJECTED_BY_SELLER();
            }

            return OrderStatusEnum::CANCELLED();
        }

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

    /**
     * Line-total addon contribution: `quantity × sum(addon.price)`.
     */
    protected function sumOrderItemAddonsLineTotal($orderItem): float
    {
        if (! $orderItem) {
            return 0.0;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->get();

        if (! $addons || $addons->isEmpty()) {
            return 0.0;
        }

        return round(((int) $orderItem->quantity) * (float) $addons->sum(fn ($a) => (float) $a->price), 2);
    }

    private function resolveScheduledSlotLabel(?Order $order): ?string
    {
        if (! $order) {
            return null;
        }

        if (! empty($order->scheduled_slot_start_time) && ! empty($order->scheduled_slot_end_time)) {
            return $this->formatScheduledTime($order->scheduled_slot_start_time).' - '.$this->formatScheduledTime($order->scheduled_slot_end_time);
        }

        return $order->scheduled_time_slot_label;
    }

    private function formatScheduledTime(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }

    /**
     * Resolve the rider's earnings on the active DELIVERY assignment for this
     * order. Falls back to the live calculation off the zone + route when no
     * persisted row exists (e.g. admin viewing a brand-new order, or a row
     * that hasn't yet been written because the rider hasn't accepted).
     *
     * @return array<string, mixed>|null
     */
    protected function resolveActiveDeliveryAssignment(): ?array
    {
        // Only relevant on the Order branch; SellerOrder doesn't carry assignments.
        if (get_class($this->resource) !== Order::class) {
            return null;
        }

        $assignment = null;
        if ($this->relationLoaded('deliveryBoyAssignments')) {
            // Active assignments + CANCELLED_BY_ADMIN rows (the latter need to
            // surface so the admin can settle them via the Settle Earnings
            // panel on the Delivery card). CANCELED / DROPPED rows stay
            // hidden — those represent superseded/abandoned assignments.
            $assignment = $this->deliveryBoyAssignments
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->whereNotIn('status', [
                    DeliveryBoyAssignmentStatusEnum::CANCELED(),
                    DeliveryBoyAssignmentStatusEnum::DROPPED(),
                ])
                ->sortByDesc('assigned_at')
                ->first();
        }

        // Live-compute when no persisted row — pulls from the order's
        // computed delivery_route + zone settings.
        $live = $this->delivery_boy_earnings ?? null;

        // Format helper — tolerate both Carbon instances (model has the
        // datetime cast) and raw strings (e.g. legacy rows without the cast).
        $fmt = static function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return $value instanceof \DateTimeInterface
                    ? $value->format('M d, Y h:i A')
                    : Carbon::parse($value)->format('M d, Y h:i A');
            } catch (\Throwable) {
                return null;
            }
        };

        $payload = [
            'id' => $assignment?->id,
            'has_persisted_row' => (bool) $assignment,
            'status' => $assignment?->status,
            'payment_status' => $assignment?->payment_status,
            'paid_at' => $fmt($assignment?->paid_at),
            'assigned_at' => $fmt($assignment?->assigned_at),
            'base_fee' => (float) ($assignment?->base_fee ?? $live['breakdown']['base_fee'] ?? 0),
            'per_store_pickup_fee' => (float) ($assignment?->per_store_pickup_fee ?? $live['breakdown']['per_store_pickup_fee'] ?? 0),
            'distance_based_fee' => (float) ($assignment?->distance_based_fee ?? $live['breakdown']['distance_based_fee'] ?? 0),
            'per_order_incentive' => (float) ($assignment?->per_order_incentive ?? $live['breakdown']['per_order_incentive'] ?? 0),
            'tip_amount' => (float) ($this->rider_tip_amount ?? 0),
            'total_earnings' => (float) ($assignment ? $assignment->total_earnings : (($live['total'] ?? 0) + ($this->rider_tip_amount ?? 0))),
            // Surfaced so the Delivery card can decide whether to show the
            // Settle Earnings panel (true only for CANCELLED_BY_ADMIN + PENDING).
            'awaiting_settle' => $assignment
                && $assignment->status === DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN()
                && $assignment->payment_status === EarningPaymentStatusEnum::PENDING(),
        ];

        // If neither a persisted assignment nor a live route exists there's
        // nothing meaningful to show — surface null so the UI hides the card.
        if (! $assignment && empty($live['total'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Snapshot the delivery_route attribute (set by the controller before
     * resource construction) into a shape the admin Delivery card can render.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveDeliveryRouteSnapshot(): ?array
    {
        if (get_class($this->resource) !== Order::class) {
            return null;
        }

        $route = $this->delivery_route ?? null;
        if (empty($route) || empty($route['total_distance'])) {
            return null;
        }

        $stops = is_array($route['route'] ?? null) ? count($route['route']) : 0;

        return [
            'total_distance' => (float) ($route['total_distance'] ?? 0),
            'stops_count' => $stops,
        ];
    }
}
