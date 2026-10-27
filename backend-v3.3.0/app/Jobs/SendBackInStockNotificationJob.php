<?php

namespace App\Jobs;

use App\Models\AvailabilityNotification;
use App\Models\ProductVariant;
use App\Notifications\BackInStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBackInStockNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $productVariantId,
        public ?int $storeId = null
    ) {}

    public function handle(): void
    {
        Log::info("DEBUG: SendBackInStockNotificationJob handler started", [
            'product_variant_id' => $this->productVariantId,
            'store_id' => $this->storeId,
        ]);

        $variant = ProductVariant::with(['product'])->find($this->productVariantId);
        if (! $variant) {
            Log::error("DEBUG: SendBackInStockNotificationJob: Variant #{$this->productVariantId} not found in database");
            return;
        }

        // Query all pending subscriptions for this product variant
        $notifications = AvailabilityNotification::with('user')
            ->where('subscribable_type', 'product_variant')
            ->where('subscribable_id', $this->productVariantId)
            ->when($this->storeId !== null, fn ($query) => $query->where('store_id', $this->storeId))
            ->where('status', 'pending')
            ->get();

        Log::info("DEBUG: SendBackInStockNotificationJob: Pending subscriptions query result", [
            'product_variant_id' => $this->productVariantId,
            'store_id_passed' => $this->storeId,
            'found_pending_count' => $notifications->count(),
        ]);

        if ($notifications->isEmpty()) {
            Log::info("DEBUG: SendBackInStockNotificationJob: No pending subscriptions for variant #{$this->productVariantId}");
            return;
        }

        foreach ($notifications as $notification) {
            try {
                $user = $notification->user;

                Log::info("DEBUG: SendBackInStockNotificationJob: Processing subscriber", [
                    'notification_id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'user_exists' => (bool) $user,
                ]);

                if ($user) {
                    $user->notify(new BackInStockNotification($variant, $notification->store_id));
                    Log::info("DEBUG: SendBackInStockNotificationJob: Successfully notified user #{$user->id}");
                } else {
                    Log::error("DEBUG: SendBackInStockNotificationJob: User #{$notification->user_id} not found");
                }

                $notification->delete();
                Log::info("DEBUG: SendBackInStockNotificationJob: Deleted pending subscription record #{$notification->id}");
            } catch (\Throwable $e) {
                Log::error("DEBUG: SendBackInStockNotificationJob: Exception notifying user #{$notification->user_id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
