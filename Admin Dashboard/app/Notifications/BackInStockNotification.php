<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\ProductVariant;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BackInStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ProductVariant $variant,
        public ?int $storeId = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
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
        $productSlug = $this->variant->product?->slug ?? '';
        $productName = $this->variant->product?->title ?? $this->variant->title;
        $title = __('labels.product_back_in_stock') ?? 'Product Back In Stock!';
        $body = "Good news! {$productName} ({$this->variant->title}) is back in stock. Order now before it runs out!";

        return [
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'image' => null,
            'type' => NotificationTypeEnum::BACK_IN_STOCK(),
            'role_type' => NotificationRoleTypeEnum::CUSTOMER->value,
            'sent_to' => 'customer',
            'store_id' => $this->storeId,
            'metadata' => [
                'product_id' => $this->variant->product_id,
                'product_slug' => $productSlug,
                'product_variant_id' => $this->variant->id,
                'store_id' => $this->storeId,
            ],
            'data' => [
                'type' => NotificationTypeEnum::BACK_IN_STOCK->value,
                'product_id' => (string) $this->variant->product_id,
                'product_slug' => (string) $productSlug,
                'product_variant_id' => (string) $this->variant->id,
                'store_id' => (string) $this->storeId,
                'role_type' => NotificationRoleTypeEnum::CUSTOMER->value,
            ],
        ];
    }
}
