<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\SellerOrder;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class OrderReadyForPickupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SellerOrder $sellerOrder,
        protected ?NotificationRoleTypeEnum $roleType = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class, 'mail'];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $payload = $this->buildPayload($notifiable);
            $order = $this->sellerOrder->order;

            return (new MailMessage)
                ->subject($payload['title'])
                ->greeting('Hello '.($notifiable->name ?? '').'!')
                ->line($payload['body'])
                ->action('View Order Details', url('orders/'.$order->id));
        } catch (\Throwable $e) {
            Log::error('OrderReadyForPickupNotification mail failed: '.$e->getMessage());

            return null;
        }
    }

    public function toFirebase(object $notifiable): array
    {
        return $this->buildPayload($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $payload = $this->buildPayload($notifiable);
        $order = $this->sellerOrder->order;
        $roleType = $this->resolveRoleType($notifiable);
        $isSeller = $roleType === NotificationRoleTypeEnum::SELLER;
        $data = $payload['data'];

        return [
            'title' => $payload['title'],
            'message' => $payload['body'],
            'type' => NotificationTypeEnum::ORDER_READY_FOR_PICKUP(),
            'sent_to' => $isSeller ? 'seller' : 'customer',
            'role_type' => $roleType->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => $this->sellerOrder->items()->first()?->orderItem?->store_id,
            'order_id' => $order->id ?? null,
            'metadata' => array_merge($data, [
                'seller_order_id' => $this->sellerOrder->id,
            ]),
        ];
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->sellerOrder->order;

        return [
            'order_id' => $order->id ?? null,
            'order_slug' => $order->slug ?? null,
            'status' => 'ready_for_pickup',
        ];
    }

    /**
     * Single source of truth for title/body/image/data per audience.
     */
    public function buildPayload(object $notifiable): array
    {
        $order = $this->sellerOrder->order;
        $roleType = $this->resolveRoleType($notifiable);
        $isSeller = $roleType === NotificationRoleTypeEnum::SELLER;
        $orderId = $order->id ?? '-';
        $firstItem = $this->sellerOrder->items()->first();
        $storeName = $firstItem?->orderItem?->store?->name ?? 'Store';
        $image = $firstItem?->product?->main_image ?? null;
        $otp = $firstItem?->orderItem?->otp ?? '';

        $data = [
            'order_slug' => $order->slug ?? null,
            'order_id' => $order->id ?? null,
            'status' => 'ready_for_pickup',
            'type' => NotificationTypeEnum::ORDER_READY_FOR_PICKUP(),
            'role_type' => $roleType->value,
            'seller_order_id' => $this->sellerOrder->id,
            'delivery_type' => $this->sellerOrder->delivery_type ?? 'delivery',
        ];

        if ($isSeller) {
            return [
                'title' => 'Order Ready for Pickup 🛍️',
                'body' => 'Order #'.$orderId.' is ready for pickup. Please verify the OTP when the customer arrives.',
                'image' => $image,
                'data' => $data,
            ];
        }

        return [
            'title' => 'Order Ready for Pickup 🛍️',
            'body' => 'Your order #'.$orderId.' from '.$storeName.' is ready for pickup! Use OTP '.$otp.' to collect your order.',
            'image' => $image,
            'data' => $data,
        ];
    }

    private function isSeller(object $notifiable): bool
    {
        return method_exists($notifiable, 'hasRole')
            && $notifiable->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    private function resolveRoleType(object $notifiable): NotificationRoleTypeEnum
    {
        if ($this->roleType !== null) {
            return $this->roleType;
        }

        return $this->isSeller($notifiable)
            ? NotificationRoleTypeEnum::SELLER
            : NotificationRoleTypeEnum::CUSTOMER;
    }
}
