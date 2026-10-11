<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\OrderItemReturn;
use App\Models\SellerOrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * ==========================================================
 * Return Dispute System
 * Phase 4
 * Custom Dispute Resolution Notification
 * ==========================================================
 */
class ReturnDisputeResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected OrderItemReturn $orderReturn,
        protected string $audience = 'customer'
    ) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, 'mail', FirebaseChannel::class];
    }

    /**
     * Get the resolved message for the specific audience.
     */
    protected function getMessageText(): string
    {
        $r = $this->orderReturn;
        $resolution = (string) ($r->admin_resolution?->value ?? $r->admin_resolution);

        if ($resolution === 'customer') {
            return match ($this->audience) {
                'customer' => 'Your return dispute has been rejected after administrator review.',
                'seller' => 'The dispute has been resolved in your favor.',
                default => 'The dispute has been resolved in favor of the seller.',
            };
        } elseif ($resolution === 'seller') {
            return match ($this->audience) {
                'customer' => 'Your return dispute has been approved and your refund has been processed.',
                'seller' => 'The dispute has been resolved in favor of the customer.',
                default => 'The dispute has been resolved in favor of the customer.',
            };
        } elseif ($resolution === 'rider') {
            return match ($this->audience) {
                'customer' => 'Your return dispute has been approved and your refund has been processed.',
                'seller' => 'The platform has protected your settlement because the delivery rider bears the financial loss.',
                'rider' => "The return dispute on Order #{$r->order_id} has been resolved. The delivery rider bears the financial liability.",
                default => 'Your return dispute has been approved and your refund has been processed.',
            };
        } elseif ($resolution === 'platform') {
            return match ($this->audience) {
                'customer' => 'Your return dispute has been approved and your refund has been processed.',
                'seller' => 'The platform has absorbed the financial loss. Your settlement has not been affected.',
                default => 'Your return dispute has been approved and your refund has been processed.',
            };
        }

        return "Return dispute for Order #{$r->order_id} has been resolved.";
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $r = $this->orderReturn;
            $subject = match ($this->audience) {
                'seller' => 'Return Dispute Resolved',
                'rider' => 'Return Dispute Responsibility Assigned',
                default => 'Your Return Dispute Resolution',
            };

            return (new MailMessage)
                ->subject($subject)
                ->greeting('Hello ' . ($notifiable->name ?? ''))
                ->line($this->getMessageText())
                ->line("Order #{$r->order_id} | Item #{$r->order_item_id}")
                ->line('Remarks: ' . ($r->admin_comment ?? '-'));
        } catch (\Throwable $e) {
            Log::error('ReturnDisputeResolvedNotification mail failed: ' . $e->getMessage());
            return null;
        }
    }

    public function toFirebase($notifiable): array
    {
        $r = $this->orderReturn;
        $sellerOrderId = null;
        try {
            $sellerOrderId = SellerOrderItem::where('order_item_id', $r->order_item_id)->value('seller_order_id');
        } catch (\Throwable $e) {
            // no-op
        }

        return [
            'title' => match ($this->audience) {
                'seller' => 'Dispute Resolved',
                'rider' => 'Rider Loss Responsibility',
                default => 'Return Dispute Resolved',
            },
            'body' => $this->getMessageText(),
            'image' => null,
            'data' => [
                'type' => NotificationTypeEnum::RETURN_ORDER_UPDATE(),
                'order_id' => $r->order_id,
                'order_slug' => $r->order->slug ?? '',
                'order_item_id' => $r->order_item_id,
                'seller_order_id' => $sellerOrderId,
                'order_item_return_id' => $r->id,
                'audience' => $this->audience,
                'return_status' => (string) $r->return_status,
                'pickup_status' => (string) $r->pickup_status,
                'role_type' => $this->resolveRoleType()->value,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->orderReturn;
        return [
            'order_id' => $r->order_id,
            'order_item_id' => $r->order_item_id,
            'order_item_return_id' => $r->id,
            'message' => $this->getMessageText(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $r = $this->orderReturn;
        $sellerOrderId = null;
        try {
            $sellerOrderId = SellerOrderItem::where('order_item_id', $r->order_item_id)->value('seller_order_id');
        } catch (\Throwable $e) {
            // no-op
        }

        return [
            'title' => match ($this->audience) {
                'seller' => 'Return Dispute Resolved',
                'rider' => 'Rider Loss Responsibility',
                default => 'Return Dispute Update',
            },
            'message' => $this->getMessageText(),
            'type' => NotificationTypeEnum::RETURN_ORDER_UPDATE(),
            'sent_to' => $this->audience,
            'role_type' => $this->resolveRoleType()->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => $r->store_id ?? null,
            'order_id' => $r->order_id ?? null,
            'metadata' => [
                'order_item_return_id' => $r->id,
                'order_slug' => $r->order->slug ?? '',
                'order_item_id' => $r->order_item_id,
                'order_id' => $r->order_id,
                'seller_order_id' => $sellerOrderId,
                'return_status' => (string) $r->return_status,
                'pickup_status' => (string) $r->pickup_status,
                'resolution' => (string) ($r->admin_resolution?->value ?? $r->admin_resolution),
            ],
        ];
    }

    protected function resolveRoleType(): NotificationRoleTypeEnum
    {
        return match ($this->audience) {
            'seller' => NotificationRoleTypeEnum::SELLER,
            'rider' => NotificationRoleTypeEnum::RIDER,
            default => NotificationRoleTypeEnum::CUSTOMER,
        };
    }
}
