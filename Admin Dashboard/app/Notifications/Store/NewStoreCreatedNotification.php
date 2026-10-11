<?php

namespace App\Notifications\Store;

use App\Broadcasting\FirebaseChannel;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewStoreCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Store $store) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, 'mail', FirebaseChannel::class];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        $store = $this->store;
        $subject = 'New Store Created';
        $greeting = 'Hello '.($notifiable->name ?? '');
        $line = 'A new store has been created: '.($store->name ?? '-').'.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($line)
            ->line('City: '.($store->city ?? '-'))
            ->line('Contact: '.($store->contact_number ?? '-'));
    }

    public function toFirebase($notifiable): array
    {
        $store = $this->store;
        $roleType = $this->resolveRoleType($notifiable);

        return [
            'title' => 'New Store Created',
            'body' => 'Store '.($store->name ?? '-').' has been created.',
            'image' => $store->getFirstMediaUrl() ?? null,
            'data' => [
                'store_id' => $store->id ?? null,
                'store_slug' => $store->slug ?? null,
                'type' => NotificationTypeEnum::SYSTEM(),
                'role_type' => $roleType->value,
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $store = $this->store;
        $isSeller = method_exists($notifiable, 'hasRole') && $notifiable->hasRole(DefaultSystemRolesEnum::SELLER());
        $isAdmin = method_exists($notifiable, 'hasRole') && $notifiable->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN());

        return [
            'title' => 'New Store Created',
            'message' => 'Store '.($store->name ?? '-').' has been created.',
            'type' => NotificationTypeEnum::SYSTEM(),
            'sent_to' => $isSeller ? 'seller' : ($isAdmin ? 'admin' : 'user'),
            'role_type' => $this->resolveRoleType($notifiable)->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => $store->id ?? null,
            'metadata' => [
                'store_id' => $store->id ?? null,
                'store_slug' => $store->slug ?? null,
                'seller_id' => $store->seller_id ?? null,
            ],
        ];
    }

    /**
     * Mirror the sent_to branching: seller → SELLER, admin → ADMIN, otherwise
     * CUSTOMER (sent_to='user' is mapped to CUSTOMER by the role enum).
     */
    protected function resolveRoleType(object $notifiable): NotificationRoleTypeEnum
    {
        if (method_exists($notifiable, 'hasRole')) {
            if ($notifiable->hasRole(DefaultSystemRolesEnum::SELLER())) {
                return NotificationRoleTypeEnum::SELLER;
            }
            if ($notifiable->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                return NotificationRoleTypeEnum::ADMIN;
            }
        }

        return NotificationRoleTypeEnum::CUSTOMER;
    }
}
