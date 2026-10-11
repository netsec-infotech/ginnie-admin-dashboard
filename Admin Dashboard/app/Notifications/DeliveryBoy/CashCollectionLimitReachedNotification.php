<?php

namespace App\Notifications\DeliveryBoy;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\DeliveryBoy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CashCollectionLimitReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected DeliveryBoy $deliveryBoy) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    /**
     * Get the Firebase representation of the notification.
     *
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toFirebase(object $notifiable): array
    {
        $limitAmount = app(\App\Services\CurrencyService::class)->getSymbol() . number_format((float) $this->deliveryBoy->cash_collection_limit, 2);
        
        return [
            'title' => 'Cash Collection Limit Reached',
            'body' => "You have reached your cash collection limit of {$limitAmount}. Please settle your cash balance to accept new orders.",
            'image' => null,
            'data' => [
                'type' => NotificationTypeEnum::GENERAL(),
                'requires_settlement' => true,
                'unsettled_cash' => $this->deliveryBoy->unsettled_cash,
                'cash_limit' => $this->deliveryBoy->cash_collection_limit,
                'role_type' => NotificationRoleTypeEnum::RIDER(),
            ],
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $limitAmount = app(\App\Services\CurrencyService::class)->getSymbol() . number_format((float) $this->deliveryBoy->cash_collection_limit, 2);

        return [
            'title' => 'Cash Collection Limit Reached',
            'message' => "You have reached your cash collection limit of {$limitAmount}. Please settle your cash balance to accept new orders.",
            'type' => NotificationTypeEnum::GENERAL(),
            'sent_to' => 'delivery_boy',
            'role_type' => NotificationRoleTypeEnum::RIDER(),
            'user_id' => $notifiable->id ?? null,
            'metadata' => [
                'requires_settlement' => true,
                'unsettled_cash' => $this->deliveryBoy->unsettled_cash,
                'cash_limit' => $this->deliveryBoy->cash_collection_limit,
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'unsettled_cash' => $this->deliveryBoy->unsettled_cash,
            'cash_limit' => $this->deliveryBoy->cash_collection_limit,
        ];
    }
}
