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
 * Notification sent to customers when their scheduled order is flagged as needs_reschedule.
 */
class ScheduledOrderNeedsAttention extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Order $order,
        protected string $reason
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    /**
     * Build the notification title based on the failure reason.
     */
    protected function getTitle(): string
    {
        return __('notifications.scheduled_order_needs_attention_title');
    }

    /**
     * Build the notification message body based on the failure reason.
     */
    protected function getMessage(): string
    {
        return match ($this->reason) {
            'store_offline' => __('notifications.scheduled_order_store_offline'),
            'store_temporarily_closed' => __('notifications.scheduled_order_store_temporarily_closed'),
            'timing_changed', 'Store timing changed' => __('notifications.scheduled_order_timing_changed'),
            'slot_unavailable' => __('notifications.scheduled_order_slot_unavailable'),
            'item_out_of_stock' => __('notifications.scheduled_order_item_out_of_stock'),
            default => __('notifications.scheduled_order_needs_attention_message'),
        };
    }

    /**
     * Get the Firebase push notification payload.
     */
    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->getTitle(),
            'body' => $this->getMessage(),
            'role_type' => NotificationRoleTypeEnum::CUSTOMER,
            'data' => [
                'type' => 'scheduled_order_needs_attention',
                'order_id' => $this->order->id,
                'order_slug' => $this->order->slug,
                'reschedule_reason' => $this->reason,
                'reschedule_deadline' => $this->order->reschedule_deadline?->toIso8601String(),
            ],
        ];
    }

    /**
     * Get the database notification representation.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'type' => 'scheduled_order_needs_attention',
            'sent_to' => 'customer',
            'role_type' => NotificationRoleTypeEnum::CUSTOMER,
            'user_id' => $notifiable->id,
            'order_id' => $this->order->id,
            'metadata' => [
                'order_slug' => $this->order->slug,
                'reschedule_reason' => $this->reason,
                'reschedule_deadline' => $this->order->reschedule_deadline?->toIso8601String(),
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
            'reschedule_reason' => $this->reason,
        ];
    }
}
