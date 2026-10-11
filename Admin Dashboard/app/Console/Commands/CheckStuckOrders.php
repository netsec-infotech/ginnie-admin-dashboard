<?php

namespace App\Console\Commands;

use App\Services\OrderEscalationService;
use Illuminate\Console\Command;

/**
 * Phase 3C — periodic sweep for stuck orders. Designed to run every 5 minutes
 * via the scheduler (see routes/console.php).
 */
class CheckStuckOrders extends Command
{
    protected $signature = 'orders:check-stuck';

    protected $description = 'Flag orders that have exceeded SLA thresholds (seller unresponsive, no rider, return unconfirmed)';

    public function handle(OrderEscalationService $service): int
    {
        $result = $service->checkStuckOrders();

        $this->info(__('labels.order_escalation_check_complete'));
        $this->line(sprintf(' Scanned: %d, Newly flagged: %d', $result['scanned'], $result['flagged']));

        return self::SUCCESS;
    }
}
