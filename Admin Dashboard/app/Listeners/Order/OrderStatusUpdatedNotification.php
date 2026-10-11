<?php

namespace App\Listeners\Order;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\Order\OrderStatusUpdated;
use App\Notifications\OrderStatusUpdated as OrderStatusUpdatedNotificationClass;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class OrderStatusUpdatedNotification implements ShouldQueue
{
    /**
     * Fan the event out to every user who should hear about it.
     * All message copy lives in OrderStatusUpdatedNotificationClass — do not build payloads here.
     */
    public function handle(OrderStatusUpdated $event): void
    {
        try {
            $customer = $event->orderItem->order->user ?? null;
            if ($customer) {
                $customer->notify(new OrderStatusUpdatedNotificationClass($event, NotificationRoleTypeEnum::CUSTOMER));
            }

            // Order-level ASSIGNED transition touches every seller in the order (multi-vendor).
            if ((string) $event->newStatus === OrderStatusEnum::ASSIGNED()) {
                $sellerOrders = $event->orderItem->order->sellerOrders ?? collect();
                foreach ($sellerOrders as $sellerOrder) {
                    $sellerUser = $sellerOrder->seller->user ?? null;
                    if ($sellerUser) {
                        $sellerUser->notify(new OrderStatusUpdatedNotificationClass($event, NotificationRoleTypeEnum::SELLER));
                    }
                }

                return;
            }

            $sellerUser = $event->orderItem->store->seller->user ?? null;
            if ($sellerUser) {
                $sellerUser->notify(new OrderStatusUpdatedNotificationClass($event, NotificationRoleTypeEnum::SELLER));
            }

            // Loop the assigned rider in too — they need to know when the
            // store cancels something they're already on (Phase 1D), and when
            // any item lifecycle event affects an order they're delivering.
            $deliveryBoyUser = $event->orderItem->order->deliveryBoy->user ?? null;
            if ($deliveryBoyUser && $sellerUser?->id !== $deliveryBoyUser->id) {
                $deliveryBoyUser->notify(new OrderStatusUpdatedNotificationClass($event));
            }
        } catch (\Throwable $e) {
            Log::error('OrderStatusUpdatedNotification listener failed: '.$e->getMessage(), [
                'order_item_id' => $event->orderItem->id ?? null,
                'new_status' => $event->newStatus ?? null,
            ]);
        }
    }
}
