<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\Order;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Events\Order\OrderDelivered;
use App\Notifications\OrderReadyForPickupNotification;
use App\Notifications\OrderPickedUpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PickupService
{
    public function __construct(
        protected OrderService $orderService
    ) {
    }

    /**
     * Mark a seller order as ready for pickup.
     */
    public function markReadyForPickup(SellerOrder $sellerOrder): array
    {
        if ($sellerOrder->order?->reschedule_status === 'needs_reschedule') {
            return [
                'success' => false,
                'message' => __('messages.scheduled_order_pending_reschedule'),
            ];
        }

        if ($sellerOrder->ready_for_pickup_at !== null) {
            return [
                'success' => true,
                'message' => __('messages.order_ready_for_pickup_successfully') ?? 'Order is ready for pickup.',
            ];
        }

        // Verify that the seller order has been fully accepted/prepared and has no items still awaiting response
        $hasAwaiting = $sellerOrder->items()->whereHas('orderItem', function ($query) {
            $query->where('status', \App\Enums\Order\OrderItemStatusEnum::AWAITING_STORE_RESPONSE());
        })->exists();

        if ($hasAwaiting) {
            return [
                'success' => false,
                'message' => __('messages.cannot_mark_ready_some_items_awaiting') ?? 'Cannot mark ready. Some items are still awaiting response.',
            ];
        }

        // Verify there is at least one active (accepted) item
        $hasActive = $sellerOrder->items()->whereHas('orderItem', function ($query) {
            $query->whereNotIn('status', [
                \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
                \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
                \App\Enums\Order\OrderItemStatusEnum::FAILED(),
            ]);
        })->exists();

        if (!$hasActive) {
            return [
                'success' => false,
                'message' => __('messages.cannot_mark_ready_no_active_items') ?? 'Cannot mark ready. There are no active items in the order.',
            ];
        }

        try {
            DB::beginTransaction();

            $sellerOrder->update([
                'ready_for_pickup_at' => now(),
            ]);

            // Determine if the parent order should also transition to READY_FOR_PICKUP.
            $parentOrder = $sellerOrder->order;
            $allReadyOrCompleted = true;
            $hasAnyActiveSellerOrder = false;

            foreach ($parentOrder->sellerOrders as $so) {
                // Check if this seller order has any active (non-rejected/non-cancelled/non-failed) items
                $hasActiveItems = $so->items()->whereHas('orderItem', function ($query) {
                    $query->whereNotIn('status', [
                        \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
                        \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
                        \App\Enums\Order\OrderItemStatusEnum::FAILED(),
                    ]);
                })->exists();

                if (!$hasActiveItems) {
                    continue;
                }

                $hasAnyActiveSellerOrder = true;

                $isReady = ($so->id === $sellerOrder->id)
                    || ($so->ready_for_pickup_at !== null)
                    || ($so->picked_up_at !== null);

                if (!$isReady) {
                    $allReadyOrCompleted = false;
                    break;
                }
            }

            if ($allReadyOrCompleted && $hasAnyActiveSellerOrder) {
                $parentOrder->update([
                    'status' => OrderStatusEnum::READY_FOR_PICKUP(),
                ]);
            }

            // Fire notifications to Customer and Seller.
            $customer = $parentOrder->user;
            if ($customer) {
                $customer->notify(new OrderReadyForPickupNotification($sellerOrder, NotificationRoleTypeEnum::CUSTOMER));
            }

            $sellerUser = $sellerOrder->seller->user ?? null;
            if ($sellerUser) {
                $sellerUser->notify(new OrderReadyForPickupNotification($sellerOrder, NotificationRoleTypeEnum::SELLER));
            }

            DB::commit();

            return [
                'success' => true,
                'message' => __('messages.order_ready_for_pickup_successfully') ?? 'Order is ready for pickup.',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to mark order ready for pickup: ' . $e->getMessage(), [
                'seller_order_id' => $sellerOrder->id,
            ]);
            return [
                'success' => false,
                'message' => __('messages.failed_to_mark_ready') ?? 'Failed to mark order ready for pickup.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify the pickup OTP and complete the order.
     */
    public function verifyPickupOtp(SellerOrder $sellerOrder, string $otp): array
    {
        if ($sellerOrder->order?->reschedule_status === 'needs_reschedule') {
            return [
                'success' => false,
                'message' => __('messages.scheduled_order_pending_reschedule'),
            ];
        }

        if ($sellerOrder->ready_for_pickup_at === null) {
            return [
                'success' => false,
                'message' => __('messages.order_not_ready_for_pickup') ?? 'Order is not ready for pickup yet.',
            ];
        }

        $cacheKey = 'pickup_otp_fail:' . $sellerOrder->id;
        $fails = (int) Cache::get($cacheKey, 0);

        if ($fails >= 5) {
            return [
                'success' => false,
                'message' => __('messages.too_many_otp_attempts') ?? 'Too many invalid OTP attempts. Locked for 5 minutes.',
            ];
        }

        $firstActiveItem = $sellerOrder->items()
            ->whereHas('orderItem', function ($query) {
                $query->whereNotIn('status', [
                    \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
                    \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
                    \App\Enums\Order\OrderItemStatusEnum::FAILED(),
                ]);
            })
            ->first()?->orderItem;

        $storedOtp = $firstActiveItem?->otp ?? $sellerOrder->items()->first()?->orderItem?->otp;

        if (empty($storedOtp) || (string) $storedOtp !== $otp) {
            Cache::put($cacheKey, $fails + 1, 300); // lock for 5 mins
            return [
                'success' => false,
                'message' => __('messages.invalid_otp') ?? 'Invalid OTP code.',
            ];
        }

        // OTP matched, clear fails.
        Cache::forget($cacheKey);

        try {
            DB::beginTransaction();

            $sellerOrder->update([
                'picked_up_at' => now(),
            ]);

            // Transition all order items to delivered.
            foreach ($sellerOrder->items as $item) {
                if ($item->orderItem) {
                    $item->orderItem->update(['otp_verified' => true]);
                    $this->orderService->deliverOrderItemByPickup($item->orderItem, $sellerOrder->seller_id);
                }
            }

            $parentOrder = $sellerOrder->order;

            // Handle COD payment status update.
            if ($parentOrder->payment_method === PaymentTypeEnum::COD()) {
                $parentOrder->update([
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                ]);
                event(new OrderDelivered($parentOrder));
            }

            // Fire notification to Customer and Seller.
            $customer = $parentOrder->user;
            if ($customer) {
                $customer->notify(new OrderPickedUpNotification($sellerOrder, NotificationRoleTypeEnum::CUSTOMER));
            }

            $sellerUser = $sellerOrder->seller->user ?? null;
            if ($sellerUser) {
                $sellerUser->notify(new OrderPickedUpNotification($sellerOrder, NotificationRoleTypeEnum::SELLER));
            }

            DB::commit();

            return [
                'success' => true,
                'message' => __('messages.order_picked_up_successfully') ?? 'Order picked up successfully.',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to complete pickup verification: ' . $e->getMessage(), [
                'seller_order_id' => $sellerOrder->id,
            ]);
            return [
                'success' => false,
                'message' => __('messages.pickup_verification_failed') ?? 'Pickup verification failed.',
                'error' => $e->getMessage(),
            ];
        }
    }
}
