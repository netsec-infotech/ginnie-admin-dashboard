<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PreparationSchedulerService;
use Illuminate\Console\Command;

class RetrofitScheduledOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:retrofit-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrofit existing scheduled orders by copying start/end times from static slots table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting retrofitting of scheduled orders...');

        $orders = Order::whereNotNull('delivery_time_slot_id')
            ->where(function ($query) {
                $query->whereNull('scheduled_slot_start_time')
                    ->orWhereNull('scheduled_slot_end_time');
            })
            ->with('deliveryTimeSlot')
            ->get();

        $count = $orders->count();
        $this->info("Found {$count} orders needing retrofit.");

        if ($count === 0) {
            $this->info('No orders require retrofitting.');

            return Command::SUCCESS;
        }

        $retrofittedCount = 0;
        $skippedCount = 0;

        foreach ($orders as $order) {
            $slot = $order->deliveryTimeSlot;
            if (! $slot) {
                $this->warn("Order #{$order->id} (UUID: {$order->uuid}) has slot ID {$order->delivery_time_slot_id} but slot was not found in the database. Skipping.");
                $skippedCount++;

                continue;
            }

            $order->update([
                'scheduled_slot_start_time' => $slot->start_time,
                'scheduled_slot_end_time' => $slot->end_time,
            ]);

            // Recalculate staggered preparation schedules for the retrofitted slot
            $store = $order->items->first()?->store;
            if ($store && $order->scheduled_date) {
                app(PreparationSchedulerService::class)->schedulePrepForSlot(
                    $store,
                    $order->scheduled_date->format('Y-m-d'),
                    $slot->start_time
                );
            }

            $retrofittedCount++;
        }

        $this->info("Retrofit complete. Retrofitted: {$retrofittedCount}, Skipped: {$skippedCount}.");

        return Command::SUCCESS;
    }
}
