<?php

namespace App\Jobs;

use App\Models\AvailabilityNotification;
use App\Models\Store;
use App\Notifications\StoreBackOnlineNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendStoreBackOnlineNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public int $storeId
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->storeId;
    }

    public function handle(): void
    {
        $store = Store::find($this->storeId);
        if (! $store?->isAcceptingOrders()) {
            Log::info('Store back-online notification skipped because the store is unavailable.', [
                'store_id' => $this->storeId,
            ]);

            return;
        }

        $deliveredCount = 0;
        $skippedCount = 0;

        AvailabilityNotification::query()
            ->where('subscribable_type', 'store')
            ->where('subscribable_id', $this->storeId)
            ->where('status', 'pending')
            ->with(['user' => fn ($query) => $query->where('status', 'active')])
            ->chunkById(100, function ($subscriptions) use ($store, &$deliveredCount, &$skippedCount): bool {
                $currentStore = $store->fresh();
                if (! $currentStore?->isAcceptingOrders()) {
                    Log::info('Store back-online notification delivery stopped because the store became unavailable.', [
                        'store_id' => $store->id,
                    ]);

                    return false;
                }

                foreach ($subscriptions as $subscription) {
                    if (! $subscription->user) {
                        $subscription->delete();
                        $skippedCount++;

                        continue;
                    }

                    try {
                        $subscription->user->notify(new StoreBackOnlineNotification($currentStore));
                        $subscription->delete();
                        $deliveredCount++;
                    } catch (\Throwable $exception) {
                        Log::error('Unable to queue store back-online notification.', [
                            'store_id' => $store->id,
                            'user_id' => $subscription->user_id,
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);

                        throw $exception;
                    }
                }

                return true;
            });

        Log::info('Store back-online notification processing completed.', [
            'store_id' => $store->id,
            'delivered_count' => $deliveredCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Store back-online notification job failed.', [
            'store_id' => $this->storeId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
