<?php

namespace App\Console\Commands;

use App\Services\LoyaltyPointService;
use Illuminate\Console\Command;

class ProcessEarnedLoyaltyPointsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loyalty:process-earned-points';

    /**
     * The console command description.
     */
    protected $description = 'Credits pending loyalty points for delivered orders whose return window has passed.';

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
        $this->info('=== Loyalty Points Earning Processor ===');

        $result = $this->loyaltyPointService->processPendingEarnedPoints();
        $this->info("✓ Eligible Orders Processed: {$result['processed_orders']}");
        $this->info("✓ Total Points Credited: {$result['credited_points']}");

        $this->info('=== Done ===');

        return 0;
    }
}
