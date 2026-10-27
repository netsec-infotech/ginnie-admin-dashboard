<?php

namespace App\Services;

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\EarningPaymentStatusEnum;
use App\Http\Resources\DeliveryBoy\DeliveryBoyLocationResource;
use App\Http\Resources\DeliveryFeedbackResource;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyLocation;
use App\Models\DeliveryFeedback;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class DeliveryBoyService
{
    public static function validatePendingOrders($delivery_boy_id): array
    {
        $pendingOrders = DeliveryBoyAssignment::where('delivery_boy_id', $delivery_boy_id)->whereNotIn('status', [DeliveryBoyAssignmentStatusEnum::COMPLETED(), DeliveryBoyAssignmentStatusEnum::CANCELED(), DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN()])->get();
        if ($pendingOrders->isNotEmpty()) {
            return [
                'success' => false,
                'message' => __('labels.pending_orders_exist'),
                'data' => $pendingOrders
            ];
        }
        return [
            'success' => true,
            'message' => __('labels.no_pending_orders_exist'),
            'data' => []
        ];
    }

    public static function getLastLocation($delivery_boy_id): array
    {
        $data = DeliveryBoyLocation::with('deliveryBoy')->where('delivery_boy_id', $delivery_boy_id)->get()->first();
        if ($data != null) {
            return [
                'success' => true,
                'message' => __('labels.last_location_retrieved_successfully'),
                'data' => new DeliveryBoyLocationResource($data)
            ];
        }
        return [
            'success' => false,
            'message' => __('labels.no_location_found'),
            'data' => []
        ];
    }

    public static function checkDeliveryBoyFeedbackByOrderId($orderId, $deliveryBoyId): bool
    {
        return DeliveryFeedback::where(['order_id' => $orderId, 'delivery_boy_id' => $deliveryBoyId])->exists();
    }

    public static function getDeliveryBoyFeedbackByOrderId($orderId, $deliveryBoyId): DeliveryFeedbackResource|null
    {
        $feedback =  DeliveryFeedback::where(['order_id' => $orderId, 'delivery_boy_id' => $deliveryBoyId])->get()->first();
        if ($feedback) {
            return new DeliveryFeedbackResource($feedback);
        }
        return null;

    }

    /**
     * Block a delivery boy. Sets is_blocked + audit columns atomically and also
     * forces them inactive so they stop appearing in rider-pool queries. Existing
     * Sanctum tokens are revoked so any open mobile session is logged out on the
     * next request.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function block(DeliveryBoy $deliveryBoy, string $reason, int $adminUserId): array
    {
        if ($deliveryBoy->is_blocked) {
            return [
                'success' => false,
                'message' => __('labels.delivery_boy_already_blocked'),
                'data'    => [],
            ];
        }

        DB::transaction(function () use ($deliveryBoy, $reason, $adminUserId) {
            $deliveryBoy->update([
                'is_blocked'          => true,
                'blocked_at'          => now(),
                'blocked_reason'      => $reason,
                'blocked_by_admin_id' => $adminUserId,
                'status'              => 'inactive',
            ]);

            // Force-logout the rider's mobile session(s).
            $deliveryBoy->user?->tokens()->delete();
        });

        return [
            'success' => true,
            'message' => __('labels.delivery_boy_blocked_successfully'),
            'data'    => ['delivery_boy' => $deliveryBoy->fresh()],
        ];
    }

    /**
     * Unblock a delivery boy. Clears the audit columns; does not toggle status
     * back to active (the rider re-activates themselves from the app).
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function unblock(DeliveryBoy $deliveryBoy): array
    {
        if (!$deliveryBoy->is_blocked) {
            return [
                'success' => false,
                'message' => __('labels.delivery_boy_not_blocked'),
                'data'    => [],
            ];
        }

        $deliveryBoy->update([
            'is_blocked'          => false,
            'blocked_at'          => null,
            'blocked_reason'      => null,
            'blocked_by_admin_id' => null,
        ]);

        return [
            'success' => true,
            'message' => __('labels.delivery_boy_unblocked_successfully'),
            'data'    => ['delivery_boy' => $deliveryBoy->fresh()],
        ];
    }

    /**
     * Aggregate delivery stats for the admin detail page.
     *
     * @return array{total: int, completed: int, canceled: int, dropped: int, in_progress: int, completion_rate: float}
     */
    public function getAssignmentStats(DeliveryBoy $deliveryBoy): array
    {
        $counts = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoy->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dropped,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as assigned
            ", [
                DeliveryBoyAssignmentStatusEnum::COMPLETED(),
                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                DeliveryBoyAssignmentStatusEnum::DROPPED(),
                DeliveryBoyAssignmentStatusEnum::IN_PROGRESS(),
                DeliveryBoyAssignmentStatusEnum::ASSIGNED(),
            ])
            ->first();

        $total = (int) $counts->total;

        return [
            'total'           => $total,
            'completed'       => (int) $counts->completed,
            'canceled'        => (int) $counts->canceled,
            'dropped'         => (int) $counts->dropped,
            'in_progress'     => (int) $counts->in_progress,
            'assigned'        => (int) $counts->assigned,
            'completion_rate' => $total > 0 ? round(((int) $counts->completed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Aggregate earnings summary for the admin detail page.
     *
     * @return array{total_earnings: string, total_paid: string, total_pending: string, cod_collected: string, cod_submitted: string}
     */
    public function getEarningsSummary(DeliveryBoy $deliveryBoy): array
    {
        $data = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoy->id)
            ->selectRaw("
                COALESCE(SUM(total_earnings), 0) as total_earnings,
                COALESCE(SUM(CASE WHEN payment_status = ? THEN total_earnings ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN payment_status = ? OR payment_status IS NULL THEN total_earnings ELSE 0 END), 0) as total_pending,
                COALESCE(SUM(cod_cash_collected), 0) as cod_collected,
                COALESCE(SUM(cod_cash_submitted), 0) as cod_submitted
            ", [
                EarningPaymentStatusEnum::PAID(),
                EarningPaymentStatusEnum::PENDING(),
            ])
            ->first();

        return [
            'total_earnings' => number_format((float) $data->total_earnings, 2),
            'total_paid'     => number_format((float) $data->total_paid, 2),
            'total_pending'  => number_format((float) $data->total_pending, 2),
            'cod_collected'  => number_format((float) $data->cod_collected, 2),
            'cod_submitted'  => number_format((float) $data->cod_submitted, 2),
        ];
    }

    /**
     * Get recent assignments for the detail page table.
     */
    public function getRecentAssignments(DeliveryBoy $deliveryBoy, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $deliveryBoy->assignments()
            ->with(['order:id,uuid,slug,final_total,status,created_at'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent wallet transactions for the detail page.
     */
    public function getRecentTransactions(DeliveryBoy $deliveryBoy, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $wallet = $deliveryBoy->wallet;
        if (!$wallet) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Phase 1C — increment the rider's drop counter and auto-flag once they
     * exceed the threshold. Threshold is hardcoded for now; promote to a
     * Settings-driven value when the admin reliability dashboard ships in
     * Phase 3 (TODO: setting key like delivery_boy.drop_flag_threshold).
     *
     * Auto-flag does NOT auto-block — flagging surfaces them for admin review.
     */
    public function incrementDropCount(DeliveryBoy $deliveryBoy): void
    {
        $threshold = 3;

        $deliveryBoy->increment('drop_count');
        $deliveryBoy->refresh();

        if (!$deliveryBoy->is_flagged && $deliveryBoy->drop_count > $threshold) {
            $deliveryBoy->update(['is_flagged' => true]);
        }
    }
}
