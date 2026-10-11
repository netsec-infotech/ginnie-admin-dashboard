<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Models\Order;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to customers when their scheduled order is auto-cancelled
 * due to an expired reschedule deadline.
 */
class ScheduledOrderAutoCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Order $order,
        protected float $refundAmount = 0.0
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    /**
     * Get the Firebase push notification payload.
     */
    public function toFirebase(object $notifiable): array
    {
        $title = 'Your scheduled order has been cancelled';
        $body = $this->refundAmount > 0
            ? "Your order #{$this->order->slug} was automatically cancelled as the reschedule deadline expired. A refund of {$this->refundAmount} has been credited to your wallet."
            : "Your order #{$this->order->slug} was automatically cancelled as the reschedule deadline expired.";

        return [
            'title' => $title,
            'body' => $body,
            'role_type' => NotificationRoleTypeEnum::CUSTOMER,
            'data' => [
                'type' => 'scheduled_order_auto_cancelled',
                'order_id' => $this->order->id,
                'order_slug' => $this->order->slug,
                'refund_amount' => $this->refundAmount,
            ],
        ];
    }

    /**
     * Get the database notification representation.
     */
    public function toDatabase(object $notifiable): array
    {
        $body = $this->refundAmount > 0
            ? "Your order #{$this->order->slug} was automatically cancelled as the reschedule deadline expired. A refund of {$this->refundAmount} has been credited to your wallet."
            : "Your order #{$this->order->slug} was automatically cancelled as the reschedule deadline expired.";

        return [
            'title' => 'Your scheduled order has been cancelled',
            'message' => $body,
            'type' => 'scheduled_order_auto_cancelled',
            'sent_to' => 'customer',
            'role_type' => NotificationRoleTypeEnum::CUSTOMER,
            'user_id' => $notifiable->id,
            'order_id' => $this->order->id,
            'metadata' => [
                'order_slug' => $this->order->slug,
                'refund_amount' => $this->refundAmount,
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_slug' => $this->order->slug,
            'refund_amount' => $this->refundAmount,
        ];
    }
}
