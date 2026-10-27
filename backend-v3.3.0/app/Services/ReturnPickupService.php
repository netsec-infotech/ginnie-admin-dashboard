<?php

namespace App\Services;

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Events\Order\OrderStatusUpdated;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\OrderItemReturn;
use App\Models\SellerStatement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnPickupService
{
    protected OrderService $orderService;

    public function __construct(protected SellerStatementService $sellerStatementService, OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Compute delivery route and earnings for a return pickup for the given delivery boy
     * Returns array: ['route' => array, 'earnings' => array, 'zone' => array|null]
     */
    public function computeRouteAndEarnings(OrderItemReturn $orderReturn, DeliveryBoy $deliveryBoy): array
    {
        $order = $orderReturn->order; // expect loaded
        $storeId = $orderReturn->store_id ? [$orderReturn->store_id] : [];

        $route = DeliveryZoneService::calculateDeliveryRoute(
            (float) $order->shipping_latitude,
            (float) $order->shipping_longitude,
            $storeId,
            $order
        );

        $deliveryBoy->loadMissing('deliveryZone');
        $zone = $deliveryBoy->deliveryZone;

        $baseFee = $zone->delivery_boy_base_fee ?? 0;
        $perStorePickupFee = ($zone->delivery_boy_per_store_pickup_fee ?? 0) * 1; // single store for return pickup
        $distanceBasedFee = ($zone->delivery_boy_distance_based_fee ?? 0) * ($route['total_distance'] ?? 0);
        $perOrderIncentive = $zone->delivery_boy_per_order_incentive ?? 0;
        $total = round($baseFee + $perStorePickupFee + $distanceBasedFee + $perOrderIncentive, 2);

        return [
            'route' => $route,
            'earnings' => [
                'total' => $total,
                'breakdown' => [
                    'base_fee' => round($baseFee, 2),
                    'per_store_pickup_fee' => round($perStorePickupFee, 2),
                    'distance_based_fee' => round($distanceBasedFee, 2),
                    'per_order_incentive' => round($perOrderIncentive, 2),
                ],
            ],
            'zone' => $zone ? [
                'id' => $zone->id,
                'name' => $zone->name,
            ] : null,
        ];
    }

    /**
     * Attach computed values onto the model instance (transient, not saved)
     */
    public function attachComputedFields(OrderItemReturn $orderReturn, array $computed): OrderItemReturn
    {
        $orderReturn->delivery_route = $computed['route'] ?? null;
        $orderReturn->earnings = $computed['earnings'] ?? null;
        $orderReturn->delivery_zone = $computed['zone'] ?? null;

        return $orderReturn;
    }

    /**
     * Accept a return pickup: assign delivery boy, set pickup status, create assignment and attach computed fields
     */
    public function acceptPickup(OrderItemReturn $orderReturn, DeliveryBoy $deliveryBoy): array
    {
        // Generate pickup OTP if required by the return request snapshot
        $requiresOtp = (bool) ($orderReturn->snapshot_otp_required ?? false);
        $otp = $orderReturn->pickup_otp;
        if ($requiresOtp && empty($otp)) {
            $otp = (string) mt_rand(100000, 999999);
        }

        // assign and mark as assigned
        $orderReturn->update([
            'delivery_boy_id' => $deliveryBoy->id,
            'pickup_status' => OrderItemReturnPickupStatusEnum::ASSIGNED(),
            'pickup_otp' => $otp,
        ]);

        // compute route + earnings
        $computed = $this->computeRouteAndEarnings($orderReturn, $deliveryBoy);

        // persist earnings snapshot in assignment
        DeliveryBoyAssignment::create([
            'delivery_boy_id' => $deliveryBoy->id,
            'order_id' => $orderReturn->order_id,
            'order_item_id' => $orderReturn->order_item_id,
            'return_id' => $orderReturn->id,
            'assignment_type' => DeliveryBoyAssignmentTypeEnum::RETURN_PICKUP(),
            'assigned_at' => now(),
            'status' => DeliveryBoyAssignmentStatusEnum::ASSIGNED(),
            'base_fee' => $computed['earnings']['breakdown']['base_fee'] ?? 0,
            'per_store_pickup_fee' => $computed['earnings']['breakdown']['per_store_pickup_fee'] ?? 0,
            'distance_based_fee' => $computed['earnings']['breakdown']['distance_based_fee'] ?? 0,
            'per_order_incentive' => $computed['earnings']['breakdown']['per_order_incentive'] ?? 0,
            'total_earnings' => $computed['earnings']['total'] ?? 0,
        ]);

        // refresh with relations and attach computed
        $orderReturn = $orderReturn->fresh(['order', 'orderItem.product', 'orderItem.variant', 'store', 'user']);
        $this->attachComputedFields($orderReturn, $computed);

        return [
            'success' => true,
            'pickup' => $orderReturn,
        ];
    }

    /**
     * Admin manually assigns / reassigns / unassigns a rider for a return
     * pickup. Mirrors OrderService::reassignRiderByAdmin but routes the actual
     * assignment through the existing acceptPickup() so earnings + route logic
     * stay single-source. Passing $newDeliveryBoyId = null unassigns.
     *
     * @return array{success: bool, message: string, pickup?: OrderItemReturn}
     */
    public function assignByAdmin(OrderItemReturn $orderReturn, ?int $newDeliveryBoyId, string $reason): array
    {
        // Only assignable while approved and not yet physically in motion.
        $assignablePickupStatuses = [
            OrderItemReturnPickupStatusEnum::PENDING(),
            OrderItemReturnPickupStatusEnum::ASSIGNED(),
        ];

        if ((string) $orderReturn->return_status !== (string) OrderItemReturnStatusEnum::SELLER_APPROVED()
            || ! in_array((string) $orderReturn->pickup_status, array_map('strval', $assignablePickupStatuses), true)) {
            return [
                'success' => false,
                'message' => __('labels.return_not_assignable'),
            ];
        }

        try {
            DB::beginTransaction();

            // Cancel the currently-assigned rider's open assignment (if any).
            $currentRiderId = $orderReturn->delivery_boy_id;
            if ($currentRiderId) {
                DeliveryBoyAssignment::where('return_id', $orderReturn->id)
                    ->where('delivery_boy_id', $currentRiderId)
                    ->whereNotIn('status', [
                        DeliveryBoyAssignmentStatusEnum::COMPLETED(),
                        DeliveryBoyAssignmentStatusEnum::CANCELED(),
                        DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
                        DeliveryBoyAssignmentStatusEnum::DROPPED(),
                    ])
                    ->update([
                        'status' => DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
                        'total_earnings' => 0,
                    ]);
            }

            if ($newDeliveryBoyId) {
                $deliveryBoy = DeliveryBoy::find($newDeliveryBoyId);
                if (! $deliveryBoy) {
                    DB::rollBack();

                    return ['success' => false, 'message' => __('labels.delivery_boy_not_found')];
                }

                // Reset to a clean pending state first so acceptPickup() (which
                // expects to claim a free pickup) behaves identically to the
                // rider self-accept path.
                $orderReturn->update([
                    'delivery_boy_id' => null,
                    'pickup_status' => OrderItemReturnPickupStatusEnum::PENDING(),
                ]);

                $orderReturn->loadMissing('order');
                $result = $this->acceptPickup($orderReturn, $deliveryBoy);

                DB::commit();

                return [
                    'success' => true,
                    'message' => __('labels.return_rider_assigned_successfully'),
                    'pickup' => $result['pickup'] ?? $orderReturn->fresh(),
                ];
            }

            // Unassign: push the return back to the rider pool.
            $orderReturn->update([
                'delivery_boy_id' => null,
                'pickup_status' => OrderItemReturnPickupStatusEnum::PENDING(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.return_rider_unassigned_successfully'),
                'pickup' => $orderReturn->fresh(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Admin return rider assignment failed', [
                'return_id' => $orderReturn->id,
                'new_delivery_boy_id' => $newDeliveryBoyId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong')];
        }
    }

    /**
     * Validate status transition
     */
    public function isValidTransition(string $current, string $requested): bool
    {
        if ($requested === OrderItemReturnPickupStatusEnum::PICKED_UP()) {
            return in_array($current, [OrderItemReturnPickupStatusEnum::ASSIGNED()], true);
        }
        if ($requested === OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER()) {
            return in_array($current, [OrderItemReturnPickupStatusEnum::PICKED_UP()], true);
        }

        return false;
    }

    /**
     * Update pickup status and related assignment; attach computed fields for response
     */
    public function updatePickupStatus(OrderItemReturn $orderReturn, string $newStatus, DeliveryBoy $deliveryBoy, array $options = []): array
    {
        $current = $orderReturn->pickup_status;
        if (! $this->isValidTransition($current, $newStatus)) {
            return [
                'success' => false,
                'error' => 'invalid_transition',
                'current' => $current,
                'requested' => $newStatus,
            ];
        }

        $requiresOtp = (bool) ($orderReturn->snapshot_otp_required ?? false);
        $requiresProof = (bool) ($orderReturn->snapshot_proof_required ?? false);

        $update = ['pickup_status' => $newStatus];
        if ($newStatus === OrderItemReturnPickupStatusEnum::PICKED_UP()) {
            // ==========================================================
            // Return Dispute System
            // Phase 2
            // Rider Pickup Mandatory Proof and Condition Fields
            // ==========================================================
            if (empty($options['images']) && empty($options['image'])) {
                return [
                    'success' => false,
                    'error' => 'return_pickup_images_required',
                    'message' => __('labels.return_pickup_images_required') ?? 'Photo proof is required when picking up this return item.',
                ];
            }

            if ($requiresOtp) {
                $providedOtp = (string) ($options['otp'] ?? '');
                if ($providedOtp === '' || $providedOtp !== (string) $orderReturn->pickup_otp) {
                    return [
                        'success' => false,
                        'error' => 'invalid_otp',
                        'message' => __('labels.invalid_otp') ?? 'Invalid return verification OTP.',
                    ];
                }
                $update['pickup_otp_verified'] = true;
            }

            if (isset($options['pickup_condition'])) {
                $update['pickup_condition'] = $options['pickup_condition'];
            }
            if (isset($options['pickup_remarks'])) {
                $update['pickup_remarks'] = $options['pickup_remarks'];
            }

            $update['picked_up_at'] = now();
            $update['return_status'] = OrderItemReturnStatusEnum::PICKED_UP();
        }

        if ($newStatus === OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER()) {
            // ==========================================================
            // Return Dispute System
            // Phase 2
            // Deactivate auto-refund on delivery; transition status to RECEIVED_BY_SELLER
            // ==========================================================
            $update['received_at'] = now();
            $update['return_status'] = OrderItemReturnStatusEnum::RECEIVED_BY_SELLER();
        } elseif ($newStatus === OrderItemReturnPickupStatusEnum::CANCELLED() || strcasecmp((string) $newStatus, 'rejected') === 0 || strcasecmp((string) $newStatus, 'cancelled') === 0) {
            // Comment: When pickup is cancelled/rejected after pickup status, finalize return status and order item status
            $update['return_status'] = OrderItemReturnStatusEnum::CANCELLED();
            $orderReturn->loadMissing('orderItem');
            if ($orderReturn->orderItem) {
                $orderReturn->orderItem->update(['status' => OrderItemStatusEnum::CANCELLED()]);
            }
        }

        $orderReturn->update($update);

        // Upload any provided condition images via SpatieMediaService when picked up
        if ($newStatus === OrderItemReturnPickupStatusEnum::PICKED_UP() && (! empty($options['images']) || ! empty($options['image']))) {
            $mediaService = app(SpatieMediaService::class);
            $photoInput = $options['image'] ?? $options['images'];
            $images = is_array($photoInput) ? $photoInput : [$photoInput];
            foreach ($images as $img) {
                if ($img instanceof UploadedFile) {
                    $mediaService->uploadFromRequest($orderReturn, $img, SpatieMediaCollectionName::RETURN_PICKUP_IMAGES());
                }
            }
        }

        // Update assignment status
        $assignment = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoy->id)
            ->where('return_id', $orderReturn->id)
            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::RETURN_PICKUP())
            ->latest('id')
            ->first();
        if ($assignment) {
            if ($newStatus === OrderItemReturnPickupStatusEnum::PICKED_UP()) {
                $assignment->update(['status' => DeliveryBoyAssignmentStatusEnum::IN_PROGRESS()]);
            } elseif ($newStatus === OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER()) {
                $assignment->update(['status' => DeliveryBoyAssignmentStatusEnum::COMPLETED()]);
            }
        }

        // ==========================================================
        // Return Dispute System
        // Phase 2
        // Block auto-refund upon delivery to seller
        // ==========================================================
        // if ($newStatus === OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER()) {
        //     $this->refundDeliveredReturn($orderReturn);
        // }

        // compute and attach for response
        $computed = $this->computeRouteAndEarnings($orderReturn->loadMissing('order'), $deliveryBoy);
        $orderReturn = $orderReturn->fresh(['order', 'orderItem.product', 'orderItem.variant', 'store', 'user']);
        $this->attachComputedFields($orderReturn, $computed);

        return [
            'success' => true,
            'pickup' => $orderReturn,
        ];
    }

    /**
     * CodeCanyon release flow: once the rider delivers the item to the seller,
     * refund immediately and keep this idempotent for repeated rider app calls.
     */
    private function refundDeliveredReturn(OrderItemReturn $orderReturn): void
    {
        $orderReturn->loadMissing(['order', 'orderItem.order']);

        if ($orderReturn->refund_processed_at || ! $orderReturn->orderItem) {
            return;
        }

        $refundResult = $this->orderService->processOrderItemRefund(
            orderItem: $orderReturn->orderItem,
            type: 'return_pickup'
        );

        if (! ($refundResult['success'] ?? false)) {
            Log::warning('Return pickup delivery refund failed', [
                'return_id' => $orderReturn->id,
                'error' => $refundResult['message'] ?? 'unknown',
            ]);

            return;
        }

        $orderReturn->update(['refund_processed_at' => now()]);

        $oldStatus = $orderReturn->orderItem->status;
        $orderReturn->orderItem->update(['status' => OrderItemStatusEnum::REFUNDED()]);
        event(new OrderStatusUpdated(
            orderItem: $orderReturn->orderItem,
            oldStatus: $oldStatus,
            newStatus: OrderItemStatusEnum::REFUNDED()
        ));

        try {
            $exists = SellerStatement::where('return_id', $orderReturn->id)
                ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
                ->where('reference_type', 'order_item_return')
                ->exists();

            if (! $exists) {
                $this->sellerStatementService->recordReturnRefund(
                    $orderReturn,
                    amount: (float) ($orderReturn->refund_amount ?? 0),
                    isCredit: false,
                    currency: $orderReturn->order?->currency_code
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to record seller return refund statement on rider delivery', [
                'return_id' => $orderReturn->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
