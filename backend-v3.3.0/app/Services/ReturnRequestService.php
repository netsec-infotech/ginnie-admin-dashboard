<?php

namespace App\Services;

use App\Enums\Finance\SellerSettlementTypeEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderReturnActorEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Events\Order\OrderStatusUpdated;
use App\Models\DeliveryBoy;
use App\Models\OrderItemReturn;
use App\Models\SellerStatement;
use App\Models\User;
use App\Models\PlatformExpenseRecord;
use App\Enums\Order\AdminLossBearerEnum;
use App\Notifications\ReturnDisputeResolvedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Models\OrderAuditLog;
use App\Support\Currency\CurrencyWriteGuard;
use App\Services\SettingService;

class ReturnRequestService
{
    public function __construct(
        protected OrderService $orderService,
        protected SellerStatementService $sellerStatementService
    ) {}

    /**
     * Apply approve/reject on a return request.
     *
     * Single write path — called by both the seller and admin controllers so
     * the two cannot drift. Idempotency: any non-REQUESTED status throws,
     * preventing seller/admin races from double-processing.
     *
     * @throws RuntimeException when the return has already been decided.
     */
    public function decide(
        OrderItemReturn $return,
        string $action,
        User $actor,
        OrderReturnActorEnum $actorType,
        ?string $comment = null,
    ): OrderItemReturn {
        if (! in_array($action, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('Unsupported return decision action: '.$action);
        }

        $current = (string) $return->return_status;

        if (in_array($current, [
            (string) OrderItemReturnStatusEnum::SELLER_APPROVED(),
            (string) OrderItemReturnStatusEnum::SELLER_REJECTED(),
            (string) OrderItemReturnStatusEnum::CANCELLED(),
        ], true)) {
            throw new RuntimeException(__('messages.return_already_processed'));
        }

        $return->return_status = $action === 'approve'
            ? OrderItemReturnStatusEnum::SELLER_APPROVED()
            : OrderItemReturnStatusEnum::SELLER_REJECTED();

        if ($action === 'approve') {
            $return->seller_approved_at = Carbon::now();
        }

        $return->decided_by_type = $actorType;
        $return->decided_by_id = $actor->id;

        if ($actorType === OrderReturnActorEnum::ADMIN) {
            $return->admin_comment = $comment;
            $return->admin_decided_at = Carbon::now();
        } else {
            $return->seller_comment = $comment;
        }

        $return->save();

        return $return;
    }

    /**
     * Two-step store receipt confirmation: approve (process refund) vs reject (with reason and action).
     */
    public function confirmStoreReceipt(
        OrderItemReturn $return,
        string $action,
        array $options = [],
        ?User $actor = null
    ): OrderItemReturn {
        if (! in_array($action, ['approve', 'escalate'], true)) {
            throw new \InvalidArgumentException('Unsupported store confirmation action: '.$action);
        }

        $current = (string) $return->return_status;
        if (! in_array($current, [
            (string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
            (string) OrderItemReturnStatusEnum::PICKED_UP(),
            (string) OrderItemReturnStatusEnum::SELLER_APPROVED(),
        ], true)) {
            throw new RuntimeException(__('messages.return_already_processed') ?? 'Return is not pending store confirmation.');
        }

        if ($action === 'approve') {
            // ==========================================================
            // Return Dispute System
            // Phase 3
            // Seller Accept Return Logic
            // ==========================================================
            $return->received_at = $return->received_at ?? Carbon::now();
            $return->seller_comment = $options['remarks'] ?? null;
            $return->return_status = OrderItemReturnStatusEnum::COMPLETED();
            $return->save();

            // Return Dispute System - Phase 5 - Audit Logging
            $this->writeAuditLog(
                return: $return,
                action: 'SELLER_ACCEPTED_RETURN',
                reason: $options['remarks'] ?? 'Seller accepted the returned item.',
                oldValue: ['status' => $current],
                newValue: ['status' => (string) OrderItemReturnStatusEnum::COMPLETED(), 'refund_amount' => $return->refund_amount]
            );

            // Upload optional seller inspection photos
            if (!empty($options['inspection_photos'])) {
                $photos = is_array($options['inspection_photos']) ? $options['inspection_photos'] : [$options['inspection_photos']];
                foreach ($photos as $photo) {
                    if ($photo instanceof \Illuminate\Http\UploadedFile) {
                        SpatieMediaService::uploadFromRequest($return, $photo, SpatieMediaCollectionName::SELLER_INSPECTION_PHOTOS());
                    }
                }
            }

            // Centralized Settlement Integration Point (TODO for Phase 4)
            // In Phase 4, we will delegate customer refund, wallet credits, and statements to ReturnSettlementService.
            // For Phase 3, we execute the legacy refund/statement logic below so everything remains fully functional:
            $return->loadMissing('orderItem.order');
            $orderItem = $return->orderItem;
            if ($orderItem && $this->orderService) {
                $refundResult = $this->orderService->processOrderItemRefund(orderItem: $orderItem, type: 'return_pickup');
                if ($refundResult['success'] ?? false) {
                    $return->update(['refund_processed_at' => Carbon::now()]);

                    // Return Dispute System - Phase 5 - Audit Logging
                    $this->writeAuditLog(
                        return: $return,
                        action: 'RETURN_SETTLEMENT_COMPLETED',
                        reason: 'Customer refund and seller settlement processed.',
                        oldValue: null,
                        newValue: ['refund_amount' => $return->refund_amount]
                    );

                    $oldStatus = $orderItem->status;
                    $orderItem->update(['status' => OrderItemStatusEnum::REFUNDED()]);
                    event(new OrderStatusUpdated(
                        orderItem: $orderItem,
                        oldStatus: $oldStatus,
                        newStatus: OrderItemStatusEnum::REFUNDED()
                    ));

                    try {
                        if ($this->sellerStatementService) {
                            $exists = SellerStatement::where('return_id', $return->id)
                                ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
                                ->where('reference_type', 'order_item_return')
                                ->exists();
                            if (!$exists) {
                                $this->sellerStatementService->recordReturnRefund(
                                    $return,
                                    amount: (float) ($return->refund_amount ?? 0),
                                    isCredit: false,
                                    currency: $return->order?->currency_code
                                );
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to record seller return refund statement on approve', [
                            'return_id' => $return->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    Log::warning('Store confirmation refund failed', [
                        'return_id' => $return->id,
                        'error' => $refundResult['message'] ?? 'unknown'
                    ]);
                }
            }
        } elseif ($action === 'escalate') {
            // ==========================================================
            // Return Dispute System
            // Phase 3
            // Seller Escalate to Admin (Dispute) Logic
            // ==========================================================
            $return->received_at = $return->received_at ?? Carbon::now();
            $return->seller_comment = $options['remarks'] ?? null;
            $return->dispute_reason = $options['dispute_reason'] ?? null;
            $return->disputed_at = Carbon::now();
            $return->return_status = OrderItemReturnStatusEnum::DISPUTED();
            $return->save();

            // Return Dispute System - Phase 5 - Audit Logging
            $this->writeAuditLog(
                return: $return,
                action: 'SELLER_ESCALATED_RETURN',
                reason: $options['dispute_reason'] ?? 'Seller escalated return to admin.',
                oldValue: ['status' => $current],
                newValue: ['status' => (string) OrderItemReturnStatusEnum::DISPUTED(), 'remarks' => $options['remarks'] ?? null]
            );

            // Upload mandatory seller inspection photos
            if (!empty($options['inspection_photos'])) {
                $photos = is_array($options['inspection_photos']) ? $options['inspection_photos'] : [$options['inspection_photos']];
                foreach ($photos as $photo) {
                    if ($photo instanceof \Illuminate\Http\UploadedFile) {
                        SpatieMediaService::uploadFromRequest($return, $photo, SpatieMediaCollectionName::SELLER_INSPECTION_PHOTOS());
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Resolve Return Dispute by Administrator (Phase 4).
     */
    public function resolveDispute(
        OrderItemReturn $return,
        string $action,
        array $options,
        User $admin
    ): OrderItemReturn {
        $current = (string) $return->return_status;
        if ($current !== (string) OrderItemReturnStatusEnum::DISPUTED()) {
            throw new RuntimeException(__('messages.return_not_disputed') ?? 'Only disputed return requests can be resolved.');
        }

        if (!in_array($action, AdminLossBearerEnum::values(), true)) {
            throw new \InvalidArgumentException('Unsupported dispute resolution action: ' . $action);
        }

        // 1. Transaction block for financial and state integrity
        DB::transaction(function () use ($return, $action, $options, $admin, $current) {
            $return->resolved_by_admin_at = Carbon::now();
            $return->resolved_by = $admin->id;
            $return->admin_resolution = $action;
            $return->admin_comment = $options['remarks'] ?? null;

            $orderItem = $return->orderItem;

            if ($action === AdminLossBearerEnum::CUSTOMER->value) {
                // Customer Bears the Loss:
                // No refund to customer, seller keeps payment.
                $return->return_status = OrderItemReturnStatusEnum::REJECTED();
                $return->save();

                // Return Dispute System - Phase 5 - Audit Logging
                $this->writeAuditLog(
                    return: $return,
                    action: 'ADMIN_RESOLVED_DISPUTE_CUSTOMER_LOSS',
                    reason: $options['remarks'] ?? 'Admin resolved dispute: Customer bears the loss.',
                    oldValue: ['status' => $current],
                    newValue: ['status' => (string) OrderItemReturnStatusEnum::REJECTED(), 'bearer' => 'customer'],
                    adminId: $admin->id
                );
            } elseif ($action === AdminLossBearerEnum::SELLER->value) {
                // Seller Bears the Loss:
                // Process customer refund via existing service.
                $return->return_status = OrderItemReturnStatusEnum::COMPLETED();
                $return->save();

                // Return Dispute System - Phase 5 - Audit Logging
                $this->writeAuditLog(
                    return: $return,
                    action: 'ADMIN_RESOLVED_DISPUTE_SELLER_LOSS',
                    reason: $options['remarks'] ?? 'Admin resolved dispute: Seller bears the loss.',
                    oldValue: ['status' => $current],
                    newValue: ['status' => (string) OrderItemReturnStatusEnum::COMPLETED(), 'bearer' => 'seller'],
                    adminId: $admin->id
                );

                if ($orderItem && $this->orderService) {
                    $refundResult = $this->orderService->processOrderItemRefund(orderItem: $orderItem, type: 'return_pickup');
                    if ($refundResult['success'] ?? false) {
                        $refundedAmount = (float) ($refundResult['data']['refund_amount'] ?? $return->refund_amount);
                        $return->update([
                            'refund_processed_at' => Carbon::now(),
                            'refund_amount' => $refundedAmount,
                        ]);

                        // Return Dispute System - Phase 5 - Audit Logging
                        $this->writeAuditLog(
                            return: $return,
                            action: 'RETURN_SETTLEMENT_COMPLETED',
                            reason: 'Customer refund and seller settlement processed.',
                            oldValue: null,
                            newValue: ['refund_amount' => $refundedAmount]
                        );

                        $oldStatus = $orderItem->status;
                        $orderItem->update(['status' => OrderItemStatusEnum::REFUNDED()]);
                        event(new OrderStatusUpdated(
                            orderItem: $orderItem,
                            oldStatus: $oldStatus,
                            newStatus: OrderItemStatusEnum::REFUNDED()
                        ));

                        try {
                            if ($this->sellerStatementService) {
                                $this->sellerStatementService->recordReturnRefund(
                                    $return,
                                    amount: $refundedAmount,
                                    isCredit: false,
                                    currency: $return->order?->currency_code
                                );
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Failed to record seller statement on admin dispute resolve', [
                                'return_id' => $return->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } else {
                        throw new RuntimeException('Failed to process customer refund: ' . ($refundResult['message'] ?? 'unknown'));
                    }
                }
            } elseif ($action === AdminLossBearerEnum::RIDER->value) {
                // Delivery Rider Bears the Loss:
                // Process customer refund. Platform absorbs loss.
                $return->return_status = OrderItemReturnStatusEnum::COMPLETED();
                $return->save();

                // Determine the rider using existing relationships (schema-agnostic)
                $riderId = $return->delivery_boy_id;
                if (!$riderId && $return->relationLoaded('deliveryBoy') && $return->deliveryBoy) {
                    $riderId = $return->deliveryBoy->id;
                }
                if (!$riderId) {
                    $riderId = $return->order?->delivery_boy_id;
                }

                // Return Dispute System - Phase 5 - Audit Logging
                $this->writeAuditLog(
                    return: $return,
                    action: 'ADMIN_RESOLVED_DISPUTE_RIDER_LOSS',
                    reason: $options['remarks'] ?? 'Admin resolved dispute: Delivery rider bears the loss.',
                    oldValue: ['status' => $current],
                    newValue: ['status' => (string) OrderItemReturnStatusEnum::COMPLETED(), 'bearer' => 'rider', 'rider_id' => $riderId],
                    adminId: $admin->id
                );

                if ($orderItem && $this->orderService) {
                    $refundResult = $this->orderService->processOrderItemRefund(orderItem: $orderItem, type: 'return_pickup');
                    if ($refundResult['success'] ?? false) {
                        $refundedAmount = (float) ($refundResult['data']['refund_amount'] ?? $return->refund_amount);
                        $return->update([
                            'refund_processed_at' => Carbon::now(),
                            'refund_amount' => $refundedAmount,
                        ]);

                        // Return Dispute System - Phase 5 - Audit Logging
                        $this->writeAuditLog(
                            return: $return,
                            action: 'RETURN_SETTLEMENT_COMPLETED',
                            reason: 'Customer refund and seller settlement processed.',
                            oldValue: null,
                            newValue: ['refund_amount' => $refundedAmount]
                        );

                        $oldStatus = $orderItem->status;
                        $orderItem->update(['status' => OrderItemStatusEnum::REFUNDED()]);
                        event(new OrderStatusUpdated(
                            orderItem: $orderItem,
                            oldStatus: $oldStatus,
                            newStatus: OrderItemStatusEnum::REFUNDED()
                        ));

                        // Create Platform Expense Record
                        PlatformExpenseRecord::create([
                            'order_item_return_id' => $return->id,
                            'delivery_boy_id' => $riderId ?: null,
                            'amount' => $refundedAmount,
                            'currency_code' => app(CurrencyWriteGuard::class)->requireValid($return->order?->currency_code, 'Return expense'),
                            'reference_number' => 'EXP-RET-' . $return->id . '-' . strtoupper(Str::random(5)),
                            'classification' => 'RETURN_DISPUTE',
                            'remarks' => $options['remarks'] ?? 'Rider responsible return dispute loss.',
                        ]);
                    } else {
                        throw new RuntimeException('Failed to process customer refund: ' . ($refundResult['message'] ?? 'unknown'));
                    }
                }
            } elseif ($action === AdminLossBearerEnum::PLATFORM->value) {
                // Platform Bears the Loss:
                // Process customer refund. Platform absorbs loss without rider linkage.
                $return->return_status = OrderItemReturnStatusEnum::COMPLETED();
                $return->save();

                // Return Dispute System - Phase 5 - Audit Logging
                $this->writeAuditLog(
                    return: $return,
                    action: 'ADMIN_RESOLVED_DISPUTE_PLATFORM_LOSS',
                    reason: $options['remarks'] ?? 'Admin resolved dispute: Platform bears the loss.',
                    oldValue: ['status' => $current],
                    newValue: ['status' => (string) OrderItemReturnStatusEnum::COMPLETED(), 'bearer' => 'platform'],
                    adminId: $admin->id
                );

                if ($orderItem && $this->orderService) {
                    $refundResult = $this->orderService->processOrderItemRefund(orderItem: $orderItem, type: 'return_pickup');
                    if ($refundResult['success'] ?? false) {
                        $refundedAmount = (float) ($refundResult['data']['refund_amount'] ?? $return->refund_amount);
                        $return->update([
                            'refund_processed_at' => Carbon::now(),
                            'refund_amount' => $refundedAmount,
                        ]);

                        // Return Dispute System - Phase 5 - Audit Logging
                        $this->writeAuditLog(
                            return: $return,
                            action: 'RETURN_SETTLEMENT_COMPLETED',
                            reason: 'Customer refund and seller settlement processed.',
                            oldValue: null,
                            newValue: ['refund_amount' => $refundedAmount]
                        );

                        $oldStatus = $orderItem->status;
                        $orderItem->update(['status' => OrderItemStatusEnum::REFUNDED()]);
                        event(new OrderStatusUpdated(
                            orderItem: $orderItem,
                            oldStatus: $oldStatus,
                            newStatus: OrderItemStatusEnum::REFUNDED()
                        ));

                        // Create Platform Expense Record
                        PlatformExpenseRecord::create([
                            'order_item_return_id' => $return->id,
                            'delivery_boy_id' => null,
                            'amount' => $refundedAmount,
                            'currency_code' => app(CurrencyWriteGuard::class)->requireValid($return->order?->currency_code, 'Return expense'),
                            'reference_number' => 'EXP-RET-' . $return->id . '-' . strtoupper(Str::random(5)),
                            'classification' => 'PLATFORM_GOODWILL',
                            'remarks' => $options['remarks'] ?? 'Platform goodwill return dispute loss.',
                        ]);
                    } else {
                        throw new RuntimeException('Failed to process customer refund: ' . ($refundResult['message'] ?? 'unknown'));
                    }
                }
            }

            // Upload optional admin resolution photos
            if (!empty($options['resolution_photos'])) {
                $photos = is_array($options['resolution_photos']) ? $options['resolution_photos'] : [$options['resolution_photos']];
                foreach ($photos as $photo) {
                    if ($photo instanceof \Illuminate\Http\UploadedFile) {
                        SpatieMediaService::uploadFromRequest($return, $photo, SpatieMediaCollectionName::ADMIN_RESOLUTION_PHOTOS());
                    }
                }
            }
        });

        // 2. Dispatch notifications POST-COMMIT
        try {
            $return->loadMissing(['user', 'seller.user', 'deliveryBoy.user', 'order']);
            $notification = new ReturnDisputeResolvedNotification($return);

            if ($return->user) {
                $return->user->notify($notification);
            }

            if ($return->seller?->user) {
                $return->seller->user->notify($notification);
            }

            if ($action === AdminLossBearerEnum::RIDER->value) {
                $rider = $return->deliveryBoy ?? ($return->order?->deliveryBoy ?? null);
                if ($rider?->user) {
                    $rider->user->notify($notification);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send dispute resolution notifications', [
                'return_id' => $return->id,
                'error' => $e->getMessage()
            ]);
        }

        return $return;
    }

    /**
     * Build the payload for the return detail page (seller + admin share view).
     */
    public function getDetail(OrderItemReturn $return, string $panel = 'seller'): array
    {
        $return->loadMissing([
            'order',
            'orderItem.product',
            'orderItem.variant',
            'orderItem.addons.addonGroup',
            'orderItem.addons.addonItem',
            'user',
            'seller.user',
            'store',
            'deliveryBoy.user',
            'decidedBy',
        ]);

        return [
            'return' => $return,
            'panel' => $panel,
            'refundBreakdown' => $this->refundBreakdown($return),
            'timelineSteps' => $this->timelineSteps($return),
        ];
    }

    /**
     * Compose a refund breakdown from the original order item snapshot.
     */
    public function refundBreakdown(OrderItemReturn $return): array
    {
        $orderItem = $return->orderItem;
        if (! $orderItem) {
            return [
                'items' => [],
                'total' => (float) $return->refund_amount,
            ];
        }

        $items = [];

        $unitPrice = (float) ($orderItem->discounted_price ?: $orderItem->price);
        $itemBase = $unitPrice * (int) ($orderItem->quantity ?? 1);
        if ($itemBase > 0) {
            $items[] = ['label' => __('labels.item'), 'amount' => $itemBase];
        }

        $addons = $orderItem->relationLoaded('addons') ? $orderItem->getRelation('addons') : collect();
        $addonsTotal = (float) $addons->sum('price');
        if ($addonsTotal > 0) {
            $items[] = ['label' => __('labels.addons'), 'amount' => $addonsTotal];
        }

        $tax = (float) ($orderItem->tax_amount ?? 0);
        if ($tax > 0) {
            $items[] = ['label' => __('labels.tax'), 'amount' => $tax];
        }

        return [
            'items' => $items,
            'total' => (float) $return->refund_amount,
        ];
    }

    /**
     * Derive a UI timeline from the return's timestamp columns. No separate
     * audit table needed — each timestamp maps to one step.
     */
    public function timelineSteps(OrderItemReturn $return): array
    {
        // Return Dispute System
        // UI Polish
        // Timeline Improvements representing every major return event with test-compatible keys
        $steps = [];

        // 1. Return Requested
        $steps[] = [
            'key' => 'requested',
            'label' => 'Return Requested',
            'at' => $return->created_at,
            'done' => true,
        ];

        // 2. Pickup Approved
        $isPickupApproved = in_array((string) $return->return_status, [
            (string) OrderItemReturnStatusEnum::SELLER_APPROVED(),
            (string) OrderItemReturnStatusEnum::PICKUP_ASSIGNED(),
            (string) OrderItemReturnStatusEnum::PICKED_UP(),
            (string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
            (string) OrderItemReturnStatusEnum::COMPLETED(),
            (string) OrderItemReturnStatusEnum::DISPUTED(),
        ], true);
        $steps[] = [
            'key' => 'decided',
            'label' => 'Pickup Approved',
            'at' => $return->seller_approved_at ?? $return->created_at,
            'done' => $isPickupApproved,
        ];

        // 3. Picked Up
        $steps[] = [
            'key' => 'picked_up',
            'label' => 'Picked Up',
            'at' => $return->picked_up_at,
            'done' => (bool) $return->picked_up_at,
        ];

        // 4. Delivered to Seller
        $steps[] = [
            'key' => 'received',
            'label' => 'Delivered to Seller',
            'at' => $return->received_at,
            'done' => (bool) $return->received_at,
        ];

        // 5. Seller Inspection (only if completed without dispute/admin resolution)
        if (in_array((string) $return->return_status, [(string) OrderItemReturnStatusEnum::COMPLETED(), (string) OrderItemReturnStatusEnum::REFUND_PROCESSED()], true) && !$return->disputed_at && !$return->resolved_by_admin_at) {
            $label = str_contains((string) $return->seller_comment, 'SLA expiry')
                ? 'Seller Inspection Auto Approved (SLA)'
                : 'Seller Accepted Return';

            $steps[] = [
                'key' => 'seller_inspection',
                'label' => $label,
                'at' => $return->refund_processed_at ?? $return->updated_at,
                'done' => true,
            ];
        }

        // 6. Seller Escalated / Disputed step
        if ($return->disputed_at || (string) $return->return_status === (string) OrderItemReturnStatusEnum::DISPUTED()) {
            $isResolved = (bool) $return->resolved_by_admin_at;
            $steps[] = [
                'key' => 'disputed',
                'label' => $isResolved ? 'Seller Escalated to Admin' : 'Seller Escalated (Awaiting Admin Review)',
                'at' => $return->disputed_at,
                'done' => (bool) $return->disputed_at,
            ];
        }

        // 7. Admin Decision resolved step
        if ($return->resolved_by_admin_at) {
            $label = match ($return->admin_resolution) {
                AdminLossBearerEnum::CUSTOMER => 'Customer Bears the Loss',
                AdminLossBearerEnum::SELLER => 'Seller Bears the Loss',
                AdminLossBearerEnum::RIDER => 'Delivery Rider Bears the Loss',
                AdminLossBearerEnum::PLATFORM => 'Platform Bears the Loss',
                default => 'Admin Resolved Dispute',
            };

            $steps[] = [
                'key' => 'admin_resolved',
                'label' => $label,
                'at' => $return->resolved_by_admin_at,
                'done' => true,
                'metadata' => [
                    'admin_name' => $return->resolvedBy?->name,
                    'timestamp' => $return->resolved_by_admin_at->toIso8601String(),
                ]
            ];
        }

        // 8. Completed / Refund processed final step
        $isCompleted = in_array((string) $return->return_status, [
            (string) OrderItemReturnStatusEnum::COMPLETED(),
            (string) OrderItemReturnStatusEnum::REFUND_PROCESSED(),
            (string) OrderItemReturnStatusEnum::REJECTED(),
            (string) OrderItemReturnStatusEnum::SELLER_REJECTED(),
            (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
            'completed',
            'rejected'
        ], true) && ($return->disputed_at ? (bool) $return->resolved_by_admin_at : true);

        $isRejected = in_array((string) $return->return_status, [
            (string) OrderItemReturnStatusEnum::REJECTED(),
            (string) OrderItemReturnStatusEnum::SELLER_REJECTED(),
            (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
            'rejected',
        ], true) || $return->admin_resolution === AdminLossBearerEnum::CUSTOMER;

        $finalKey = (string) $return->return_status === (string) OrderItemReturnStatusEnum::REFUND_PROCESSED()
            ? 'refund_processed'
            : 'completed';

        $finalLabel = match (true) {
            $isRejected => 'Rejected',
            (string) $return->return_status === (string) OrderItemReturnStatusEnum::REFUND_PROCESSED() => 'Refund Processed',
            default => 'Completed',
        };

        $steps[] = [
            'key' => $finalKey,
            'label' => $finalLabel,
            'at' => $return->resolved_by_admin_at ?? $return->refund_processed_at ?? $return->updated_at,
            'done' => $isCompleted,
        ];

        return $steps;
    }

    /**
     * Process returns in received_by_seller state that have exceeded the SLA threshold.
     * Return Dispute System
     * Phase 5
     */
    public function processSlaTimeouts(): array
    {
        $slaHours = (int) (app(SettingService::class)->getSettingValues('seller')['return_seller_inspection_sla_hours'] ?? 48);
        $threshold = Carbon::now()->subHours($slaHours);

        $expiredReturns = OrderItemReturn::where('return_status', OrderItemReturnStatusEnum::RECEIVED_BY_SELLER())
            ->whereNotNull('received_at')
            ->where('received_at', '<=', $threshold)
            ->get();

        $processed = [];
        $failed = [];

        foreach ($expiredReturns as $return) {
            DB::beginTransaction();
            try {
                // Confirm receipt with 'approve' exactly as if the seller approved it
                $this->confirmStoreReceipt(
                    return: $return,
                    action: 'approve',
                    options: [
                        'remarks' => 'Approved automatically due to Seller Inspection SLA expiry.',
                    ]
                );

                // Write custom audit log for SLA auto-approval
                $this->writeAuditLog(
                    return: $return,
                    action: 'SELLER_INSPECTION_SLA_AUTO_APPROVAL',
                    reason: "Seller inspection SLA of {$slaHours} hours expired.",
                    oldValue: ['status' => (string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER()],
                    newValue: ['status' => (string) OrderItemReturnStatusEnum::COMPLETED()]
                );

                DB::commit();
                $processed[] = $return->id;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to auto-process expired return SLA', [
                    'return_id' => $return->id,
                    'error' => $e->getMessage()
                ]);
                $failed[] = [
                    'id' => $return->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'sla_hours' => $slaHours
        ];
    }

    /**
     * Helper to log actions in OrderAuditLog.
     * Return Dispute System
     * Phase 5
     */
    protected function writeAuditLog(
        OrderItemReturn $return,
        string $action,
        ?string $reason = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?int $adminId = null
    ): void {
        try {
            OrderAuditLog::create([
                'order_id' => $return->order_id,
                'order_item_id' => $return->order_item_id,
                'admin_id' => $adminId,
                'action' => $action,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Return audit log write failed: ' . $e->getMessage(), [
                'return_id' => $return->id,
                'action' => $action
            ]);
        }
    }
}
