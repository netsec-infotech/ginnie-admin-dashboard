<?php

namespace App\Notifications;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Order;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 3C — admin notification when an order trips an escalation threshold.
 * Mirrors the broadcast-only shape used by AdminCustomNotification so it
 * reaches the existing admin notification panel without extra plumbing.
 */
class OrderEscalatedNotification extends Notification
{
    use Queueable;

    public Order $order;
    /** @var array<int, string> */
    public array $reasons;

    public function __construct(Order $order, array $reasons)
    {
        $this->order = $order;
        $this->reasons = $reasons;
    }

    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $reasonLabels = collect($this->reasons)
            ->map(fn($r) => __('labels.' . $r) !== 'labels.' . $r ? __('labels.' . $r) : str_replace('_', ' ', $r))
            ->all();

        return [
            'title' => __('labels.order_escalated') . ' #' . $this->order->id,
            'body' => implode(', ', $reasonLabels),
            'sent_to' => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN(),
            'data' => [
                'order_id' => $this->order->id,
                'reasons' => $this->reasons,
                'type' => NotificationTypeEnum::ORDER_UPDATE(),
                'sent_to' => 'admin',
                'role_type' => NotificationRoleTypeEnum::ADMIN(),
            ],
        ];
    }
}
