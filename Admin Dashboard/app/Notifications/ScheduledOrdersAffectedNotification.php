<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to sellers when their store's scheduled orders are flagged for reschedule.
 */
class ScheduledOrdersAffectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $storeId,
        protected string $storeName,
        protected int $affectedCount
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    /**
     * Build the notification title.
     */
    protected function getTitle(): string
    {
        return 'Scheduled Orders Require Attention';
    }

    /**
     * Build the notification message body.
     */
    protected function getMessage(): string
    {
        return "You have {$this->affectedCount} scheduled order(s) for {$this->storeName} that have been flagged as needing rescheduling.";
    }

    /**
     * Get the Firebase push notification payload.
     */
    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->getTitle(),
            'body' => $this->getMessage(),
            'role_type' => NotificationRoleTypeEnum::SELLER,
            'data' => [
                'type' => 'scheduled_orders_affected',
                'store_id' => $this->storeId,
                'affected_count' => $this->affectedCount,
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
            'type' => 'scheduled_orders_affected',
            'sent_to' => 'seller',
            'role_type' => NotificationRoleTypeEnum::SELLER,
            'user_id' => $notifiable->id,
            'store_id' => $this->storeId,
            'metadata' => [
                'store_name' => $this->storeName,
                'affected_count' => $this->affectedCount,
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'affected_count' => $this->affectedCount,
        ];
    }
}
