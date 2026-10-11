<?php

namespace App\Listeners\Order;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Events\Order\OrderPlaced;
use App\Notifications\NewOrderNotification as NewOrderNotificationClass;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NewOrderNotification implements ShouldQueue
{
    /**
     * Fan the OrderPlaced event out to the customer and every seller in the order.
     * Message copy is owned by NewOrderNotificationClass — do not build payloads here.
     */
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        $customer = $order->user ?? null;
        if ($customer) {
            $this->safeNotify($customer, $event, NotificationRoleTypeEnum::CUSTOMER);
        } else {
            Log::warning('NewOrderNotification skipped: customer missing', [
                'order_id' => $order->id ?? null,
            ]);
        }

        foreach ($order->sellerOrders ?? [] as $sellerOrder) {
            $sellerUser = $sellerOrder->seller->user ?? null;
            if ($sellerUser) {
                $this->safeNotify($sellerUser, $event, NotificationRoleTypeEnum::SELLER);
            }
        }
    }

    private function safeNotify($user, OrderPlaced $event, NotificationRoleTypeEnum $roleType): void
    {
        try {
            $user->notify(new NewOrderNotificationClass($event, $roleType));
        } catch (\Throwable $e) {
            Log::error('NewOrderNotification failed to dispatch notification', [
                'message' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'order_id' => $event->order->id ?? null,
            ]);
        }
    }
}
