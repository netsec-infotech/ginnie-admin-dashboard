<?php

namespace App\Console\Commands;

use App\Services\ReturnRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Return Dispute System
 * Phase 5
 * Scheduled command to process returns that have exceeded the Seller Inspection SLA.
 */
class ProcessReturnSlaCommand extends Command
{
    protected $signature = 'returns:process-inspection-sla';

    protected $description = 'Automatically approve returns in received_by_seller state that have exceeded the inspection SLA';

    public function handle(ReturnRequestService $service): int
    {
        $this->info('Starting Seller Inspection SLA check...');

        try {
            $result = $service->processSlaTimeouts();

            $processedCount = count($result['processed']);
            $failedCount = count($result['failed']);

            $this->info("SLA check complete (SLA configured: {$result['sla_hours']} hours).");
            $this->line(sprintf('Processed: %d, Failed: %d', $processedCount, $failedCount));

            if ($failedCount > 0) {
                foreach ($result['failed'] as $fail) {
                    $this->error("Return ID #{$fail['id']} failed: {$fail['error']}");
                }
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('ProcessReturnSlaCommand failed unexpectedly', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Unexpected failure: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
