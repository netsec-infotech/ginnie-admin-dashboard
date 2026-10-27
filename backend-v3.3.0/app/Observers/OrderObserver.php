<?php

namespace App\Observers;

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DeliveryBoy;
use App\Models\Order;
use App\Notifications\Delivery\OrderReadyForPickupNotification;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        try {
            $status = is_object($order->status) && isset($order->status->value) ? $order->status->value : (string) $order->status;
            if ($order->wasChanged('status') && in_array($status, ['cancelled', 'rejected_by_seller', 'partially_rejected'])) {
                app(OrderService::class)->refundRiderTipToCustomerWallet($order);
            }

            if ($order->wasChanged('status') && $order->status === OrderStatusEnum::READY_FOR_PICKUP()) {
                // A rider may have been pre-assigned by an admin while the
                // order was still in a pre-pickup state. In that case the
                // open rider-pool broadcast is wrong — only the assigned
                // rider should act on this order.
                if ($order->delivery_boy_id) {
                    return;
                }

                // A pickup order should not notify delivery boys.
                $firstSellerOrder = $order->sellerOrders->first();
                $deliveryType = $firstSellerOrder?->delivery_type?->value ?? $firstSellerOrder?->delivery_type;
                if ($deliveryType === 'pickup') {
                    return;
                }

                // Find eligible delivery boys: verified, available (status = 1), same delivery zone
                $zoneId = $order->delivery_zone_id;
                if (! $zoneId) {
                    return; // Cannot target by zone
                }

                $deliveryBoys = DeliveryBoy::where('delivery_zone_id', $zoneId)
                    ->where('status', 'active')
                    ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
                    ->whereDoesntHave('assignments', function ($query) use ($order) {
                        $query->where('order_id', $order->id)
                            ->whereIn('status', [
                                DeliveryBoyAssignmentStatusEnum::DROPPED(),
                                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                            ]);
                    })
                    ->with('user')
                    ->get();

                foreach ($deliveryBoys as $db) {
                    $user = $db->user; // Notify user entity for FCM + mail + database
                    if ($user) {
                        // Eager-load items to enrich notification payload image
                        $order->loadMissing('items.product');
                        $user->notify(new OrderReadyForPickupNotification($order));
                    }
                }
            }
        } catch (\Throwable $e) {

            Log::warning('OrderObserver READY_FOR_PICKUP notification failed: '.$e->getMessage(), [
                'order_id' => $order->id ?? null,
                'exception' => $e,
                'stack_trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
