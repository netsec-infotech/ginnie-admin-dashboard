<?php

namespace App\Console\Commands;

use App\Services\LoyaltyPointService;
use Illuminate\Console\Command;

class ExpireLoyaltyPointsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loyalty:expire-points';

    /**
     * The console command description.
     */
    protected $description = 'Process expired loyalty point batches, deduct expired points, and send push notification reminders for points expiring soon.';

    protected LoyaltyPointService $loyaltyPointService;

    public function __construct(LoyaltyPointService $loyaltyPointService)
    {
        parent::__construct();
        $this->loyaltyPointService = $loyaltyPointService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Loyalty Points Expiry Processor ===');

        $result = $this->loyaltyPointService->processExpiredPoints();
        $this->info("✓ Expired Batches Processed: {$result['expired_batches']}");
        $this->info("✓ Total Points Expired: {$result['expired_points']}");

        $remindersSent = $this->loyaltyPointService->sendExpiringPointsReminders(daysBefore: 3);
        $this->info("✓ Expiry Push Notifications Dispatched: {$remindersSent}");

        $this->info('=== Done ===');

        return 0;
    }
}
