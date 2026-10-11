<?php

namespace App\Services;

use App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyPointService
{
    public function getSettings(): array
    {
        return Setting::getLoyaltyPointSettings();
    }

    public function getUserBalance(int $userId): LoyaltyPoint
    {
        $loyaltyPoint = LoyaltyPoint::firstOrCreate(
            ['user_id' => $userId],
            ['points_balance' => 0]
        );

        $activeBalance = $this->getActivePointsBalance($userId);
        if ($loyaltyPoint->points_balance !== $activeBalance) {
            $loyaltyPoint->update(['points_balance' => $activeBalance]);
        }

        return $loyaltyPoint;
    }

    public function getActivePointsBalance(int $userId): int
    {
        $now = now();

        $activeEarnedQuery = LoyaltyPointTransaction::where('user_id', $userId)
            ->whereIn('type', [
                LoyaltyPointTransactionTypeEnum::EARNED(),
                LoyaltyPointTransactionTypeEnum::ADMIN_ADJUSTMENT(),
                LoyaltyPointTransactionTypeEnum::REFUNDED(),
            ])
            ->where('status', LoyaltyPointTransactionStatusEnum::CREDITED())
            ->where('points', '>', 0)
            ->whereRaw('points > used_points')
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });

        return (int) $activeEarnedQuery->sum(DB::raw('points - used_points'));
    }

    public function getPointsExpiringSoon(int $userId, int $days = 30): int
    {
        $now = now();
        $future = now()->addDays($days);

        return (int) LoyaltyPointTransaction::where('user_id', $userId)
            ->whereIn('type', [
                LoyaltyPointTransactionTypeEnum::EARNED(),
                LoyaltyPointTransactionTypeEnum::ADMIN_ADJUSTMENT(),
                LoyaltyPointTransactionTypeEnum::REFUNDED(),
            ])
            ->where('status', LoyaltyPointTransactionStatusEnum::CREDITED())
            ->where('points', '>', 0)
            ->whereRaw('points > used_points')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $future])
            ->sum(DB::raw('points - used_points'));
    }

    public function adjustBalance(
        int $userId,
        int $points,
        LoyaltyPointTransactionTypeEnum|string $type,
        ?int $orderId = null,
        ?string $description = null,
        LoyaltyPointTransactionStatusEnum|string $status = LoyaltyPointTransactionStatusEnum::CREDITED
    ): LoyaltyPointTransaction {
        $typeValue = is_callable($type) ? (string) $type() : (string) ($type->value ?? $type);
        $statusValue = is_callable($status) ? (string) $status() : (string) ($status->value ?? $status);

        return DB::transaction(function () use ($userId, $points, $typeValue, $statusValue, $orderId, $description) {
            $loyaltyPoint = LoyaltyPoint::where('user_id', $userId)->lockForUpdate()->first();

            if (! $loyaltyPoint) {
                $loyaltyPoint = LoyaltyPoint::create([
                    'user_id' => $userId,
                    'points_balance' => 0,
                ]);
                $loyaltyPoint = LoyaltyPoint::where('user_id', $userId)->lockForUpdate()->first();
            }

            $expiresAt = null;
            if ($typeValue === (string) LoyaltyPointTransactionTypeEnum::EARNED()) {
                $settings = $this->getSettings();
                if (! empty($settings['is_expiry_enabled']) && ! empty($settings['expiry_days'])) {
                    $expiresAt = now()->addDays((int) $settings['expiry_days']);
                }
            }

            if ($points < 0 || $typeValue === (string) LoyaltyPointTransactionTypeEnum::REDEEMED()) {
                if ($statusValue === (string) LoyaltyPointTransactionStatusEnum::CREDITED()) {
                    $statusValue = (string) LoyaltyPointTransactionStatusEnum::DEBITED();
                }
                $this->deductPointsFifo($userId, abs($points));
            }

            $transaction = LoyaltyPointTransaction::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'points' => $points,
                'used_points' => 0,
                'expires_at' => $expiresAt,
                'type' => $typeValue,
                'status' => $statusValue,
                'description' => $description,
            ]);

            $newBalance = $this->getActivePointsBalance($userId);
            $loyaltyPoint->update(['points_balance' => $newBalance]);

            return $transaction;
        });
    }

    private function deductPointsFifo(int $userId, int $pointsToDeduct): void
    {
        $now = now();
        $remaining = $pointsToDeduct;

        $batches = LoyaltyPointTransaction::where('user_id', $userId)
            ->whereIn('type', [
                LoyaltyPointTransactionTypeEnum::EARNED(),
                LoyaltyPointTransactionTypeEnum::ADMIN_ADJUSTMENT(),
                LoyaltyPointTransactionTypeEnum::REFUNDED(),
            ])
            ->where('status', LoyaltyPointTransactionStatusEnum::CREDITED())
            ->where('points', '>', 0)
            ->whereRaw('points > used_points')
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $availableInBatch = $batch->points - $batch->used_points;
            $deduct = min($remaining, $availableInBatch);

            $batch->increment('used_points', $deduct);
            $remaining -= $deduct;
        }
    }

    public function processExpiredPoints(): array
    {
        $settings = $this->getSettings();
        if (empty($settings['is_enabled']) || empty($settings['is_expiry_enabled'])) {
            return ['expired_batches' => 0, 'expired_points' => 0];
        }

        $now = now();
        $expiredBatches = LoyaltyPointTransaction::where('type', LoyaltyPointTransactionTypeEnum::EARNED())
            ->where('status', LoyaltyPointTransactionStatusEnum::CREDITED())
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->whereRaw('points > used_points')
            ->get();

        $processedCount = 0;
        $totalExpiredPoints = 0;

        foreach ($expiredBatches as $batch) {
            DB::transaction(function () use ($batch, &$processedCount, &$totalExpiredPoints) {
                $unexpiredPoints = $batch->points - $batch->used_points;
                if ($unexpiredPoints <= 0) {
                    return;
                }

                $batch->update(['used_points' => $batch->points]);

                LoyaltyPointTransaction::create([
                    'user_id' => $batch->user_id,
                    'order_id' => $batch->order_id,
                    'points' => -$unexpiredPoints,
                    'used_points' => 0,
                    'expires_at' => null,
                    'type' => LoyaltyPointTransactionTypeEnum::EXPIRED(),
                    'status' => LoyaltyPointTransactionStatusEnum::CREDITED(),
                    'description' => "{$unexpiredPoints} loyalty points expired",
                ]);

                $newBalance = $this->getActivePointsBalance($batch->user_id);
                LoyaltyPoint::where('user_id', $batch->user_id)->update(['points_balance' => $newBalance]);

                $processedCount++;
                $totalExpiredPoints += $unexpiredPoints;
            });
        }

        return [
            'expired_batches' => $processedCount,
            'expired_points' => $totalExpiredPoints,
        ];
    }

    public function sendExpiringPointsReminders(int $daysBefore = 3): int
    {
        $settings = $this->getSettings();
        if (empty($settings['is_enabled']) || empty($settings['is_expiry_enabled'])) {
            return 0;
        }

        $startWindow = now();
        $endWindow = now()->addDays($daysBefore);

        $expiringBatches = LoyaltyPointTransaction::where('type', LoyaltyPointTransactionTypeEnum::EARNED())
            ->where('status', LoyaltyPointTransactionStatusEnum::CREDITED())
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$startWindow, $endWindow])
            ->whereRaw('points > used_points')
            ->get()
            ->groupBy('user_id');

        $notificationService = app(NotificationService::class);
        $notificationsSent = 0;

        foreach ($expiringBatches as $userId => $batches) {
            $totalPointsExpiring = $batches->sum(fn ($b) => $b->points - $b->used_points);
            if ($totalPointsExpiring <= 0) {
                continue;
            }

            try {
                $notificationService->createNotification([
                    'user_id' => $userId,
                    'sent_to' => 'user',
                    'role_type' => \App\Enums\Notification\NotificationRoleTypeEnum::CUSTOMER(),
                    'type' => \App\Enums\NotificationTypeEnum::LOYALTY_POINT(),
                    'title' => 'Loyalty Points Expiring Soon!',
                    'message' => "You have {$totalPointsExpiring} loyalty points expiring in {$daysBefore} days. Redeem them now on your next order!",
                    'metadata' => [
                        'expiring_points' => $totalPointsExpiring,
                        'days_before' => $daysBefore,
                    ],
                ]);
                $notificationsSent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed sending loyalty point expiry notification to user {$userId}: ".$e->getMessage());
            }
        }

        return $notificationsSent;
    }

    public function calculateOrderReturnWindowEndsAt(Order $order): Carbon
    {
        $deliveredAt = $order->delivered_at ?? $order->updated_at ?? $order->created_at ?? now();
        if (! $deliveredAt instanceof Carbon) {
            $deliveredAt = Carbon::parse($deliveredAt);
        }

        $order->loadMissing('items.product');
        $maxReturnDays = 0;

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && ! empty($product->is_returnable)) {
                $days = (int) ($product->returnable_days ?? 0);
                if ($days > $maxReturnDays) {
                    $maxReturnDays = $days;
                }
            }
        }

        return $maxReturnDays > 0 ? $deliveredAt->copy()->addDays($maxReturnDays) : $deliveredAt->copy();
    }

    public function creditPendingOrderPoints(Order $order): bool
    {
        $pendingTx = LoyaltyPointTransaction::where('order_id', $order->id)
            ->where('type', LoyaltyPointTransactionTypeEnum::EARNED())
            ->where('status', LoyaltyPointTransactionStatusEnum::PENDING())
            ->first();

        if (! $pendingTx) {
            return false;
        }

        return DB::transaction(function () use ($pendingTx, $order) {
            $pendingTx->update([
                'status' => LoyaltyPointTransactionStatusEnum::CREDITED(),
            ]);

            $newBalance = $this->getActivePointsBalance($order->user_id);
            LoyaltyPoint::where('user_id', $order->user_id)->update(['points_balance' => $newBalance]);

            try {
                app(NotificationService::class)->createNotification([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'sent_to' => 'user',
                    'role_type' => \App\Enums\Notification\NotificationRoleTypeEnum::CUSTOMER(),
                    'type' => \App\Enums\NotificationTypeEnum::LOYALTY_POINT(),
                    'title' => 'Loyalty Points Earned!',
                    'message' => "You earned {$pendingTx->points} loyalty points for Order #{$order->id}!",
                    'metadata' => [
                        'points_earned' => $pendingTx->points,
                        'order_id' => $order->id,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning("Failed sending loyalty point earned notification for order {$order->id}: ".$e->getMessage());
            }

            return true;
        });
    }

    public function cancelPendingOrderPoints(Order $order): bool
    {
        $pendingTx = LoyaltyPointTransaction::where('order_id', $order->id)
            ->where('type', LoyaltyPointTransactionTypeEnum::EARNED())
            ->where('status', LoyaltyPointTransactionStatusEnum::PENDING())
            ->first();

        if (! $pendingTx) {
            return false;
        }

        $pendingTx->update([
            'status' => LoyaltyPointTransactionStatusEnum::CANCELLED(),
        ]);

        return true;
    }

    public function refundRedeemedOrderPoints(Order $order): bool
    {
        if ($order->loyalty_points_redeemed <= 0) {
            return false;
        }

        $alreadyRefunded = LoyaltyPointTransaction::where('order_id', $order->id)
            ->where('type', LoyaltyPointTransactionTypeEnum::REFUNDED())
            ->exists();

        if ($alreadyRefunded) {
            return false;
        }

        return DB::transaction(function () use ($order) {
            $pointsToRestore = $order->loyalty_points_redeemed;

            // Decrement used_points from earned batches to restore available balance
            $batches = LoyaltyPointTransaction::where('user_id', $order->user_id)
                ->where('type', LoyaltyPointTransactionTypeEnum::EARNED())
                ->where('used_points', '>', 0)
                ->orderBy('created_at', 'desc')
                ->lockForUpdate()
                ->get();

            $remaining = $pointsToRestore;
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $restoreAmount = min($remaining, $batch->used_points);
                $batch->decrement('used_points', $restoreAmount);
                $remaining -= $restoreAmount;
            }

            LoyaltyPointTransaction::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'points' => $pointsToRestore,
                'used_points' => $pointsToRestore,
                'expires_at' => null,
                'type' => LoyaltyPointTransactionTypeEnum::REFUNDED(),
                'status' => LoyaltyPointTransactionStatusEnum::CREDITED(),
                'description' => "Refunded {$pointsToRestore} redeemed points for cancelled/returned order #{$order->id}",
            ]);

            $newBalance = $this->getActivePointsBalance($order->user_id);
            LoyaltyPoint::where('user_id', $order->user_id)->update(['points_balance' => $newBalance]);

            return true;
        });
    }

    public function reverseCreditedOrderPoints(Order $order): bool
    {
        $creditedTx = LoyaltyPointTransaction::where('order_id', $order->id)
            ->where('type', LoyaltyPointTransactionTypeEnum::EARNED())
            ->where('status', LoyaltyPointTransactionStatusEnum::CREDITED())
            ->first();

        if (! $creditedTx || $creditedTx->points <= 0) {
            return false;
        }

        $alreadyReversed = LoyaltyPointTransaction::where('order_id', $order->id)
            ->where('type', LoyaltyPointTransactionTypeEnum::ADMIN_ADJUSTMENT())
            ->where('points', '<', 0)
            ->exists();

        if ($alreadyReversed) {
            return false;
        }

        $this->adjustBalance(
            userId: $order->user_id,
            points: -$creditedTx->points,
            type: LoyaltyPointTransactionTypeEnum::ADMIN_ADJUSTMENT(),
            orderId: $order->id,
            description: "Reversed {$creditedTx->points} earned points for returned order #{$order->id}"
        );

        return true;
    }

    public function processPendingEarnedPoints(): array
    {
        $settings = $this->getSettings();
        if (empty($settings['is_enabled'])) {
            return ['processed_orders' => 0, 'credited_points' => 0];
        }

        $pendingTxs = LoyaltyPointTransaction::where('type', LoyaltyPointTransactionTypeEnum::EARNED())
            ->where('status', LoyaltyPointTransactionStatusEnum::PENDING())
            ->whereNotNull('order_id')
            ->get();

        $processedCount = 0;
        $totalCreditedPoints = 0;

        foreach ($pendingTxs as $tx) {
            $order = Order::find($tx->order_id);
            if (! $order) {
                continue;
            }

            $orderStatus = is_object($order->status) && method_exists($order->status, 'value') ? $order->status->value : (string) $order->status;
            if ($orderStatus !== (string) OrderStatusEnum::DELIVERED()) {
                continue;
            }

            $returnEndsAt = $this->calculateOrderReturnWindowEndsAt($order);
            if (now()->gte($returnEndsAt)) {
                if ($this->creditPendingOrderPoints($order)) {
                    $processedCount++;
                    $totalCreditedPoints += $tx->points;
                }
            }
        }

        return [
            'processed_orders' => $processedCount,
            'credited_points' => $totalCreditedPoints,
        ];
    }

    public function handleOrderCancellationOrReturn(Order $order): void
    {
        $this->refundRedeemedOrderPoints($order);
        $this->cancelPendingOrderPoints($order);
        $this->reverseCreditedOrderPoints($order);
    }
}
