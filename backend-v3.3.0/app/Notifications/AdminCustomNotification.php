<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminCustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected AppNotification $appNotification) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->appNotification->title,
            'message' => $this->appNotification->message,
            'type' => $this->appNotification->target_type?->value,
            'sent_to' => $this->appNotification->audience_type->value,
            'role_type' => $this->resolveRoleType()->value,
            'user_id' => $notifiable->id ?? null,
            'metadata' => $this->appNotification['metadata'],
        ];
    }

    /**
     * Get the Firebase representation of the notification.
     */
    public function toFirebase($notifiable): array
    {
        $metadata = $this->appNotification['metadata'] ?? [];

        return [
            'title' => $this->appNotification->title,
            'body' => $this->appNotification->message,
            'image' => $this->appNotification->notification_image ?? null,
            'data' => array_merge([
                'notification_id' => $this->id,
                'type' => $this->appNotification->target_type?->value,
                'role_type' => $this->resolveRoleType()->value,
            ], $metadata),
        ];
    }

    /**
     * Map the AppNotification audience to a role_type. NotificationAudience
     * values (customer/seller/rider) line up with NotificationRoleTypeEnum.
     */
    protected function resolveRoleType(): NotificationRoleTypeEnum
    {
        return NotificationRoleTypeEnum::fromSentTo(
            $this->appNotification->audience_type?->value
        ) ?? NotificationRoleTypeEnum::CUSTOMER;
    }
}
