<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Store\StoreStatusEnum;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreSlotOverride;
use App\Notifications\ScheduledOrderNeedsAttention;
use App\Notifications\ScheduledOrdersAffectedNotification;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevalidateScheduledOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:revalidate-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revalidate future scheduled orders across 5 pillars and flag/auto-cancel invalid ones';

    /**
     * Terminal order statuses that should be excluded from revalidation.
     *
     * @var array<string>
     */
    protected array $terminalStatuses = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled order revalidation...');

        $this->terminalStatuses = [
            OrderStatusEnum::CANCELLED(),
            OrderStatusEnum::REJECTED_BY_SELLER(),
            OrderStatusEnum::FAILED(),
            OrderStatusEnum::DELIVERED(),
        ];

        // Step 1: Auto-cancel orders whose reschedule deadline has expired
        $this->sweepExpiredDeadlines();

        // Step 2: Revalidate future scheduled orders that are NOT already flagged
        $this->revalidateFutureOrders();

        $this->info('Scheduled order revalidation complete.');

        return Command::SUCCESS;
    }

    /**
     * Sweep and auto-cancel orders with expired reschedule deadlines.
     * Refunds to wallet for non-COD orders only.
     */
    protected function sweepExpiredDeadlines(): void
    {
        $expiredOrders = Order::where('reschedule_status', 'needs_reschedule')
            ->whereNotNull('reschedule_deadline')
            ->where('reschedule_deadline', '<', now())
            ->whereNotIn('status', $this->terminalStatuses)
            ->with(['items.store', 'user'])
            ->get();

        $cancelledCount = 0;

        foreach ($expiredOrders as $order) {
            try {
                // Use OrderService to cancel and refund the order
                app(OrderService::class)->cancelScheduledOrderWithRefund(
                    $order,
                    'Reschedule deadline expired'
                );

                Log::info("RevalidateScheduledOrders: Auto-cancelled expired order #{$order->id} (deadline: {$order->reschedule_deadline}).");
                $cancelledCount++;
            } catch (\Throwable $e) {
                Log::error("RevalidateScheduledOrders: Failed to auto-cancel order #{$order->id}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-cancelled {$cancelledCount} expired reschedule deadline(s).");
    }

    protected function revalidateFutureOrders(): void
    {
        $orders = Order::whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '>=', today()->format('Y-m-d'))
            ->whereNotIn('status', $this->terminalStatuses)
            // Skip orders already flagged for reschedule
            ->where(function ($query) {
                $query->whereNull('reschedule_status')
                    ->orWhere('reschedule_status', '!=', 'needs_reschedule');
            })
            ->with(['items.store', 'user'])
            ->get();

        $flaggedCount = 0;
        $flaggedOrdersByStore = [];

        foreach ($orders as $order) {
            $store = $order->items->first()?->store;
            if (! $store) {
                continue;
            }

            // Run 5-pillar validation checks
            $failureReason = $this->validateOrder($order, $store);

            if ($failureReason) {
                $this->flagOrderForReschedule($order, $store, $failureReason);
                $flaggedOrdersByStore[$store->id] = ($flaggedOrdersByStore[$store->id] ?? 0) + 1;
                $flaggedCount++;
            }
        }

        // Notify sellers for affected stores
        foreach ($flaggedOrdersByStore as $storeId => $count) {
            try {
                $store = Store::find($storeId);
                $sellerUser = $store?->seller?->user;
                if ($sellerUser) {
                    $sellerUser->notify(new ScheduledOrdersAffectedNotification(
                        $storeId,
                        $store->name ?? 'Your Store',
                        $count
                    ));
                    Log::info("RevalidateScheduledOrders: Notified seller for store #{$storeId} of {$count} affected orders.");
                }
            } catch (\Throwable $e) {
                Log::error("RevalidateScheduledOrders: Failed to notify seller for store #{$storeId}: {$e->getMessage()}");
            }
        }

        $this->info("Flagged {$flaggedCount} order(s) for reschedule.");
    }

    /**
     * Validate a single order across all 5 pillars.
     * Returns the failure reason string, or null if all checks pass.
     */
    protected function validateOrder(Order $order, Store $store): ?string
    {
        // Pillar 1: Store status must be online
        if ($store->status !== StoreStatusEnum::ONLINE()) {
            return 'store_offline';
        }

        // Pillar 2: Store must not be temporarily closed
        if ($store->is_temporarily_closed) {
            return 'store_temporarily_closed';
        }

        // Pillar 3: Scheduled slot must fall within current operating hours
        if ($order->scheduled_slot_start_time && $order->scheduled_slot_end_time) {
            $dayName = strtolower(Carbon::parse($order->scheduled_date)->format('l'));
            $operatingHours = $store->metadata['operating_hours'] ?? [];
            $dayConfig = $operatingHours[$dayName] ?? null;

            $isOpen = $dayConfig && isset($dayConfig['is_open']) && filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN);

            if (! $isOpen) {
                return 'timing_changed';
            }

            $openTime = $dayConfig['open_time'] ?? null;
            $closeTime = $dayConfig['close_time'] ?? null;

            if (! $openTime || ! $closeTime
                || $order->scheduled_slot_start_time < $openTime
                || $order->scheduled_slot_end_time > $closeTime) {
                return 'timing_changed';
            }

            // Check break hours overlap
            $hasBreak = ! empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
            if ($hasBreak) {
                $breakStart = $dayConfig['break_start_time'] ?? null;
                $breakEnd = $dayConfig['break_end_time'] ?? null;
                if ($breakStart && $breakEnd
                    && $order->scheduled_slot_start_time < $breakEnd
                    && $order->scheduled_slot_end_time > $breakStart) {
                    return 'timing_changed';
                }
            }
        }

        // Pillar 4: Check if the slot has been blocked via overrides
        if ($order->scheduled_date && $order->scheduled_slot_start_time) {
            $blocked = StoreSlotOverride::where('store_id', $store->id)
                ->where('is_blocked', true)
                ->where(function ($query) use ($order) {
                    // Match by override date
                    $query->where('override_date', $order->scheduled_date->format('Y-m-d'));
                })
                ->where('start_time', '<=', $order->scheduled_slot_start_time)
                ->where('end_time', '>=', $order->scheduled_slot_end_time)
                ->exists();

            if ($blocked) {
                return 'slot_unavailable';
            }
        }

        // Pillar 5: Check stock availability for all order items at this store
        foreach ($order->items as $item) {
            if ($item->status === OrderItemStatusEnum::CANCELLED()) {
                continue;
            }

            $stock = DB::table('store_product_variants')
                ->where('store_id', $item->store_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->value('stock');

            if ($stock !== null && (int) $stock <= 0) {
                return 'item_out_of_stock';
            }
        }

        return null;
    }

    /**
     * Flag an order as needing reschedule with the given reason.
     */
    protected function flagOrderForReschedule(Order $order, Store $store, string $reason): void
    {
        $deadline = now()->addHours((int) ($store->reschedule_deadline_hours ?? 24));

        $order->update([
            'reschedule_status' => 'needs_reschedule',
            'reschedule_reason' => $reason,
            'reschedule_deadline' => $deadline,
        ]);

        // Send push notification to customer
        try {
            $order->user?->notify(new ScheduledOrderNeedsAttention($order, $reason));
        } catch (\Throwable $e) {
            Log::warning("RevalidateScheduledOrders: Failed to notify user for order #{$order->id}: {$e->getMessage()}");
        }

        Log::info("RevalidateScheduledOrders: Flagged order #{$order->id} as needs_reschedule (reason: {$reason}).");
    }
}
