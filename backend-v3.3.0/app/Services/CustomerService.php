<?php

namespace App\Services;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Address;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

class CustomerService
{
    /**
     * @param User $user
     * @return array
     */
    public function getOrderStats(User $user): array
    {
        $stats = Order::where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered", [OrderStatusEnum::DELIVERED()])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled", [OrderStatusEnum::CANCELLED()])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [OrderStatusEnum::PENDING()])
            ->selectRaw("SUM(CASE WHEN status NOT IN (?,?,?) THEN 1 ELSE 0 END) as in_progress", [
                OrderStatusEnum::DELIVERED(),
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::PENDING(),
            ])
            ->selectRaw('COALESCE(SUM(final_total), 0) as total_spent')
            ->first();

        $total = (int) ($stats->total ?? 0);
        $delivered = (int) ($stats->delivered ?? 0);

        return [
            'total' => $total,
            'delivered' => $delivered,
            'cancelled' => (int) ($stats->cancelled ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'in_progress' => (int) ($stats->in_progress ?? 0),
            'completion_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
            'total_spent' => (float) ($stats->total_spent ?? 0),
        ];
    }

    /**
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getRecentOrders(User $user, int $limit = 10): Collection
    {
        return Order::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'uuid', 'slug', 'status', 'payment_status', 'payment_method', 'final_total', 'created_at']);
    }

    /**
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getRecentWalletTransactions(User $user, int $limit = 10): Collection
    {
        $wallet = $user->wallet;
        if (!$wallet) {
            return collect();
        }

        return WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param User $user
     * @return Collection
     */
    public function getAddresses(User $user): Collection
    {
        return Address::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getRecentNotifications(User $user, int $limit = 20): Collection
    {
        return Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->roleType(NotificationRoleTypeEnum::CUSTOMER)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
