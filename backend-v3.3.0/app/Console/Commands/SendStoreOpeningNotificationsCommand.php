<?php

namespace App\Console\Commands;

use App\Enums\Store\StoreStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Jobs\SendStoreBackOnlineNotificationJob;
use App\Models\AvailabilityNotification;
use App\Models\Store;
use Illuminate\Console\Command;

class SendStoreOpeningNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:check-opening-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send back-online notifications for stores that have opened based on operating hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Find store IDs that have pending availability notifications
        $storeIds = AvailabilityNotification::where('subscribable_type', 'store')
            ->where('status', 'pending')
            ->distinct()
            ->pluck('subscribable_id');

        if ($storeIds->isEmpty()) {
            $this->info('No stores with pending availability notifications found.');
            return self::SUCCESS;
        }

        $stores = Store::whereIn('id', $storeIds)
            ->where('status', StoreStatusEnum::ONLINE())
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->get();

        $notifiedCount = 0;
        foreach ($stores as $store) {
            if ($store->isAcceptingOrders()) {
                SendStoreBackOnlineNotificationJob::dispatch($store->id)->afterCommit();
                $notifiedCount++;
                $this->line("Dispatched back-online notification job for store #{$store->id} ({$store->name}).");
            }
        }

        $this->info("Completed store opening notification check. Dispatched {$notifiedCount} jobs.");
        return self::SUCCESS;
    }
}
