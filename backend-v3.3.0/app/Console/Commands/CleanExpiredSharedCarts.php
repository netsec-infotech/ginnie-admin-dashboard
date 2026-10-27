<?php

namespace App\Console\Commands;

use App\Models\SharedCart;
use Illuminate\Console\Command;

class CleanExpiredSharedCarts extends Command
{
    protected $signature = 'cart:clean-expired-shares';

    protected $description = 'Delete shared carts that have been expired for at least seven days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = SharedCart::query()
            ->where('expires_at', '<=', now()->subDays(7))
            ->delete();

        $this->info("Deleted {$deleted} expired shared carts.");

        return self::SUCCESS;
    }
}
