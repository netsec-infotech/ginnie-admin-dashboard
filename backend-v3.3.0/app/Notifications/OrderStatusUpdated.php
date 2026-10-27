<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\SellerOrderItem;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $event;

    public function __construct($event, protected ?NotificationRoleTypeEnum $roleType = null)
    {
        $this->event = $event;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class, 'mail'];
    }

    /**
     * Firebase push payload.
     */
    public function toFirebase(object $notifiable): array
    {
        return $this->buildPayload($notifiable);
    }

    /**
     * Mail representation.
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $payload = $this->buildPayload($notifiable);
            $order = $this->event->orderItem->order;

            $mail = (new MailMessage)
                ->subject($payload['title'])
                ->greeting('Hello '.($notifiable->name ?? '').'!')
                ->line($payload['body']);

            if ($url = $this->resolveOrderUrl($notifiable)) {
                $mail->action('View Order', $url);
            }

            return $mail;
        } catch (\Throwable $e) {
            Log::error('OrderStatusUpdated mail failed: '.$e->getMessage(), [
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => static::class,
            ]);

            return null;
        }
    }

    /**
     * Persisted database notification row.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $payload = $this->buildPayload($notifiable);
        $order = $this->event->orderItem->order;
        $data = $payload['data'];

        return [
            'title' => $payload['title'],
            'message' => $payload['body'],
            'type' => $data['type'] ?? NotificationTypeEnum::ORDER_UPDATE(),
            'sent_to' => $this->resolveRoleType($notifiable) === NotificationRoleTypeEnum::SELLER ? 'seller' : 'customer',
            'role_type' => $this->resolveRoleType($notifiable)->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => null,
            'order_id' => $data['order_id'] ?? ($order->id ?? null),
            'metadata' => array_merge($data, [
                'order_id' => $order->id ?? null,
                'order_slug' => $order->slug ?? null,
                'seller_order_id' => $this->resolveSellerOrderId(),
            ]),
        ];
    }

    /**
     * Legacy array shape (kept for broadcast compatibility).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $order = $this->event->orderItem->order;

        return [
            'order_id' => $order->id ?? null,
            'order_slug' => $order->slug ?? null,
            'status' => ucfirst(Str::replace('_', ' ', $order->status ?? '')),
        ];
    }

    /**
     * Build the title/body/image/data block for the given recipient.
     * Single source of truth for every channel.
     *
     * @return array{title:string,body:string,image:?string,data:array<string,mixed>}
     */
    public function buildPayload(object $notifiable): array
    {
        $isSeller = $this->resolveRoleType($notifiable) === NotificationRoleTypeEnum::SELLER;
        $audience = $isSeller ? 'seller' : 'customer';
        $orderItem = $this->event->orderItem;
        $order = $orderItem->order;
        $image = $orderItem->product->main_image ?? null;
        $orderId = $order->id ?? '-';
        $newStatus = $this->event->newStatus;
        // Optional source from the event (event was extended in Fix #1). Default
        // 'system' keeps any caller that hasn't passed it explicitly safe.
        $source = $this->event->source ?? 'system';
        $deliveryType = $order->sellerOrders->first()->delivery_type->value ?? 'delivery';

        // Automatic acceptance timeout rejection needs explicit copy so the
        // seller and customer know this was caused by the missed deadline.
        if (
            (string) $newStatus === OrderItemStatusEnum::REJECTED()
            && $source === 'system'
            && ! empty($this->event->acceptanceDeadline)
        ) {
            $deadline = $this->event->acceptanceDeadline;
            $deadlineIso = method_exists($deadline, 'toIso8601String') ? $deadline->toIso8601String() : null;
            $deadlineText = method_exists($deadline, 'format') ? $deadline->format('M d, Y h:i A') : null;

            $body = $audience === 'seller'
                ? 'Order item "'.$orderItem->title.'" from order #'.$orderId.' was automatically rejected because it was not accepted before the acceptance window expired.'
                : 'The seller did not accept "'.$orderItem->title.'" from your order #'.$orderId.' within the acceptance window, so it was automatically rejected. Any eligible refund has been processed.';

            if ($deadlineText) {
                $body .= ' Deadline: '.$deadlineText.'.';
            }

            return [
                'title' => 'Order Auto-Rejected: '.$orderItem->title,
                'body' => $body,
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'status' => OrderItemStatusEnum::REJECTED(),
                    'source' => $source,
                    'acceptance_deadline' => $deadlineIso,
                    'type' => NotificationTypeEnum::ORDER_UPDATE(),
                    'role_type' => $this->resolveRoleType($notifiable)->value,
                    'delivery_type' => $deliveryType,
                ],
            ];
        }

        // Item delivered
        if ((string) $newStatus === OrderItemStatusEnum::DELIVERED()) {
            $body = match ($audience) {
                'seller' => 'The order item "'.$orderItem->title.'" from order #'.$orderId.' has been delivered to the customer.',
                'rider' => 'You delivered "'.$orderItem->title.'" successfully. Earnings have been credited.',
                default => 'Your order item "'.$orderItem->title.'" has been successfully delivered. We hope you enjoy your purchase!',
            };

            return [
                'title' => 'Order Delivered: '.$orderItem->title,
                'body' => $audience === 'seller'
                    ? 'The order item "'.$orderItem->title.'" from order #'.$orderId.' has been delivered to the customer.'
                    : 'Your order item "'.$orderItem->title.'" has been successfully delivered. We hope you enjoy your purchase!',
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'status' => $orderItem->status,
                    'type' => NotificationTypeEnum::DELIVERY(),
                    'role_type' => $this->resolveRoleType($notifiable)->value,
                ],
            ];
        }

        // Item cancelled — copy depends on WHO triggered the cancellation.
        if ((string) $newStatus === OrderItemStatusEnum::CANCELLED()) {
            $bySeller = $source === 'seller';
            $byCustomer = $source === 'customer';
            $byAdmin = $source === 'admin';

            $sellerBody = match (true) {
                $bySeller => 'You cancelled "'.$orderItem->title.'" from order #'.$orderId.'. The customer has been refunded.',
                $byCustomer => 'The customer cancelled "'.$orderItem->title.'" from order #'.$orderId.'.',
                $byAdmin => 'An administrator cancelled "'.$orderItem->title.'" from order #'.$orderId.'.',
                default => '"'.$orderItem->title.'" from order #'.$orderId.' has been cancelled.',
            };

            $customerBody = match (true) {
                $bySeller => 'The seller cancelled "'.$orderItem->title.'" from your order #'.$orderId.'. Your refund is on its way.',
                $byCustomer => 'Your order item "'.$orderItem->title.'" has been cancelled successfully.',
                $byAdmin => 'Your order item "'.$orderItem->title.'" has been cancelled by support. A refund has been issued.',
                default => 'Your order item "'.$orderItem->title.'" has been cancelled.',
            };

            $riderBody = match (true) {
                $bySeller => 'The seller cancelled "'.$orderItem->title.'" on order #'.$orderId.'. You no longer need to deliver this item.',
                $byCustomer => 'The customer cancelled "'.$orderItem->title.'" on order #'.$orderId.'.',
                $byAdmin => 'Order item "'.$orderItem->title.'" on order #'.$orderId.' was cancelled by support.',
                default => 'Order item "'.$orderItem->title.'" on order #'.$orderId.' has been cancelled.',
            };

            $body = match ($audience) {
                'seller' => $sellerBody,
                'rider' => $riderBody,
                default => $customerBody,
            };

            return [
                'title' => 'Order Item Cancelled: '.$orderItem->title,
                'body' => $audience === 'seller'
                    ? 'The order item "'.$orderItem->title.'" from order #'.$orderId.' has been cancelled by the customer.'
                    : 'Your order item "'.$orderItem->title.'" has been cancelled successfully.',
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'status' => $orderItem->status,
                    'source' => $source,
                    'type' => NotificationTypeEnum::ORDER_UPDATE(),
                    'role_type' => $this->resolveRoleType($notifiable)->value,
                    'delivery_type' => $deliveryType,
                ],
            ];
        }

        // Phase 1C — delivery attempt failed.
        if ((string) $newStatus === OrderItemStatusEnum::DELIVERY_FAILED()) {
            $body = match ($audience) {
                'seller' => 'Delivery of "'.$orderItem->title.'" from order #'.$orderId.' failed. The rider is returning the item to your store.',
                'rider' => 'You marked "'.$orderItem->title.'" as delivery failed. Please return the item to the seller — your earnings are preserved.',
                default => 'We couldn\'t complete the delivery of "'.$orderItem->title.'". The item is being returned and you\'ll be refunded once the seller confirms receipt.',
            };

            return [
                'title' => 'Delivery Failed: '.$orderItem->title,
                'body' => $body,
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'status' => $orderItem->status,
                    'type' => NotificationTypeEnum::ORDER_UPDATE(),
                ],
            ];
        }

        // Phase 1C — item is heading back to the seller (rider drop or
        // failed delivery aftermath).
        if ((string) $newStatus === OrderItemStatusEnum::RETURNING_TO_STORE()) {
            $body = match ($audience) {
                'seller' => 'Items for order #'.$orderId.' are being returned to your store. Please confirm receipt once they arrive.',
                'rider' => 'Please return "'.$orderItem->title.'" to the seller. The order will close once the seller confirms receipt.',
                default => 'Your item "'.$orderItem->title.'" is on its way back to the seller. We\'ll refund you once they confirm receipt.',
            };

            return [
                'title' => 'Items Returning to Store: '.$orderItem->title,
                'body' => $body,
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'status' => $orderItem->status,
                    'type' => NotificationTypeEnum::ORDER_UPDATE(),
                ],
            ];
        }

        // Delivery partner assigned at the order level
        if ($order && (string) ($order->status ?? '') === OrderStatusEnum::ASSIGNED()) {
            $body = match ($audience) {
                'seller' => 'A delivery partner has been assigned for Order #'.$orderId.'.',
                'rider' => 'You have been assigned to deliver order #'.$orderId .'.',
                default => 'A delivery partner has been assigned for your order #'.$orderId.'.',
            };

            return [
                'title' => 'Delivery Partner Assigned',
                'body' => $audience === 'seller'
                    ? 'A delivery partner has been assigned for Order #'.$orderId.'.'
                    : 'A delivery partner has been assigned for your order #'.$orderId.'.',
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'status' => $order->status ?? '',
                    'type' => NotificationTypeEnum::ORDER_UPDATE(),
                    'role_type' => $this->resolveRoleType($notifiable)->value,
                ],
            ];
        }

        // Default order-level update
        $status = ucfirst(Str::replace('_', ' ', $order->status ?? ''));

        $defaultBody = match ($audience) {
            'seller' => 'Order #'.$orderId.' is now '.$status.'.',
            'rider' => 'Order #'.$orderId.' you are delivering is now '.$status.'.',
            default => 'Your order #'.$orderId.' is now '.$status.'.',
        };

        return [
            'title' => 'Order Status Updated',
            'body' => $audience === 'seller'
                ? 'Order #'.$orderId.' is now '.$status.'.'
                : 'Your order #'.$orderId.' is now '.$status.'.',
            'image' => $image,
            'data' => [
                'order_slug' => $order->slug ?? null,
                'order_id' => $order->id ?? null,
                'status' => $status,
                'type' => NotificationTypeEnum::ORDER_UPDATE(),
                'role_type' => $this->resolveRoleType($notifiable)->value,
                'delivery_type' => $deliveryType,
            ],
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

    private function resolveSellerOrderId(): ?int
    {
        try {
            return SellerOrderItem::where('order_item_id', $this->event->orderItem->id)
                ->value('seller_order_id');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveOrderUrl(object $notifiable): ?string
    {
        $order = $this->event->orderItem->order;

        if ($this->resolveRoleType($notifiable) === NotificationRoleTypeEnum::SELLER) {
            $sellerOrderId = $this->resolveSellerOrderId();

            return $sellerOrderId ? route('seller.orders.show', $sellerOrderId) : null;
        }

        if (empty($order->slug)) {
            return null;
        }

        return rtrim((string) Config::get('app.frontendUrl'), '/').'/orders/'.$order->slug;
    }
}
