<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Store;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StoreBackOnlineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Store $store
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $store = Store::find($this->store->id);
        $customerIsActive = method_exists($notifiable, 'getRawOriginal')
            ? $notifiable->getRawOriginal('status') === 'active'
            : ($notifiable->status ?? false) === true;

        return $customerIsActive && $store?->isAcceptingOrders() === true;
    }

    /**
     * Database representation.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->buildPayload($notifiable);
    }

    /**
     * Firebase push payload.
     */
    public function toFirebase(object $notifiable): array
    {
        return $this->buildPayload($notifiable);
    }

    /**
     * Build unified payload array.
     */
    protected function buildPayload(object $notifiable): array
    {
        $storeName = $this->store->name;
        $title = __('notifications.store_back_online_title');
        $body = __('notifications.store_back_online_message', ['store' => $storeName]);

        return [
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'image' => $this->store->store_logo ?? null,
            'type' => NotificationTypeEnum::STORE_BACK_ONLINE(),
            'role_type' => NotificationRoleTypeEnum::CUSTOMER->value,
            'sent_to' => 'customer',
            'store_id' => $this->store->id,
            'metadata' => [
                'store_id' => $this->store->id,
                'store_slug' => $this->store->slug,
                'click_action' => 'store',
            ],
            'data' => [
                'type' => NotificationTypeEnum::STORE_BACK_ONLINE->value,
                'store_id' => (string) $this->store->id,
                'store_slug' => (string) $this->store->slug,
                'role_type' => NotificationRoleTypeEnum::CUSTOMER->value,
                'click_action' => 'store',
            ],
        ];
    }
}
