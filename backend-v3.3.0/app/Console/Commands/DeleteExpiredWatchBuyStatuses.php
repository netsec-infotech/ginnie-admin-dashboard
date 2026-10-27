<?php

namespace App\Console\Commands;

use App\Services\WatchBuyStatusService;
use Illuminate\Console\Command;

class DeleteExpiredWatchBuyStatuses extends Command
{
    protected $signature = 'watch-buy:delete-expired-statuses';

    protected $description = 'Permanently delete expired Watch & Buy stories and their media';

    public function handle(WatchBuyStatusService $statuses): int
    {
        $deleted = $statuses->deleteExpired();
        $this->info("Deleted {$deleted} expired Watch & Buy stories.");

        return self::SUCCESS;
    }
}
