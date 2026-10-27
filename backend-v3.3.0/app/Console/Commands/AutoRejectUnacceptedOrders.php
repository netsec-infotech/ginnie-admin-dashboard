<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class AutoRejectUnacceptedOrders extends Command
{
    protected $signature = 'orders:auto-reject-unaccepted';

    protected $description = 'Automatically reject seller-unaccepted orders after their acceptance deadline';

    public function handle(OrderService $orderService): int
    {
        $result = $orderService->autoRejectExpiredUnacceptedOrders();

        $this->info('Unaccepted order auto-reject check complete.');
        $this->line(sprintf(
            ' Scanned: %d, Rejected items: %d, Failed orders: %d',
            $result['scanned'],
            $result['rejected'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
