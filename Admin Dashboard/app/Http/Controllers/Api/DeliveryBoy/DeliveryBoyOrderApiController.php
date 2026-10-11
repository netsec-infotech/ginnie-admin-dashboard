<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Events\Order\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryBoy\BulkCollectOrderRequest;
use App\Http\Requests\DeliveryBoy\BulkDeliverOrderRequest;
use App\Http\Requests\DeliveryBoy\DropOrderRequest;
use App\Http\Requests\DeliveryBoy\MarkDeliveryFailedRequest;
use App\Http\Requests\DeliveryBoy\UpdateOrderItemStatusRequest;
use App\Http\Resources\DeliveryBoy\DeliveryBoyOrderItemResource;
use App\Http\Resources\DeliveryBoy\DeliveryBoyOrderResource;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\CodPaymentService;
use App\Services\DeliveryZoneService;
use App\Services\OrderService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Group('DeliveryBoy Orders')]
class DeliveryBoyOrderApiController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Get orders available for the delivery boy
     */
    #[QueryParameter('page', description: 'Page.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'per_page.', type: 'int', default: 15, example: 15)]
    public function getAvailableOrders(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $deliveryBoy = $user->deliveryBoy;

            // Get page size from request or use default
            $perPage = $request->input('per_page', 10);

            // Get orders that are in the delivery boy's zone and are available for delivery
            $orders = $this->availableDeliveryOrdersQuery($deliveryBoy)
                ->withDeliveryBoyEarnings() // Use the scope to load relationships for earnings calculation
                ->with($this->deliveryBoyOrderRelations())
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Rider location for route origin
            $riderLoc = DeliveryBoyLocation::where('delivery_boy_id', $deliveryBoy->id)->first();

            // Calculate the delivery route for each order
            foreach ($orders->items() as $order) {
                // Get store IDs from order items
                $storeIds = $order->items->pluck('store_id')->unique()->toArray();

                // Calculate delivery route starting from rider's position
                $deliveryRoute = DeliveryZoneService::calculateDeliveryRoute(
                    $order->shipping_latitude,
                    $order->shipping_longitude,
                    $storeIds,
                    $order,
                    $riderLoc ? (float) $riderLoc->latitude : null,
                    $riderLoc ? (float) $riderLoc->longitude : null,
                );

                // Add delivery route to order
                $order->delivery_route = $deliveryRoute;
            }

            // [CHANGE] Load cash collection limit details for the rider
            $cashLimitAmount = $deliveryBoy->cash_collection_limit;
            $unsettledCash = $deliveryBoy->unsettled_cash;
            $isLimitReached = $deliveryBoy->isCashLimitReached();

            // Create a resource collection
            $resourceCollection = DeliveryBoyOrderResource::collection($orders);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.orders_fetched_successfully'),
                data: [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                    'orders' => $resourceCollection,
                    'cash_limit_details' => [
                        'cash_limit' => $cashLimitAmount,
                        'unsettled_cash' => $unsettledCash,
                        'is_limit_reached' => $isLimitReached,
                    ],
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Accept an order
     */
    public function acceptOrder(Request $request, string $orderId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $deliveryBoy = $user->deliveryBoy;

            // A blocked rider must not be able to claim new orders even if their
            // session predates the block. Bail before any DB writes.
            if ($deliveryBoy?->is_blocked) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.delivery_boy_account_blocked'),
                    data: ['blocked_reason' => $deliveryBoy->blocked_reason]
                );
            }

            // [CHANGE] Block order acceptance if cash collection limit is reached
            if ($deliveryBoy?->isCashLimitReached()) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.cash_collection_limit_exceeded') ?? 'Cash collection limit reached. Please settle cash with admin or online to accept new orders.',
                    data: [
                        'requires_settlement' => true,
                        'unsettled_cash' => $deliveryBoy->unsettled_cash,
                        'cash_limit' => $deliveryBoy->cash_collection_limit,
                    ]
                );
            }

            // Find the order
            $order = $this->availableDeliveryOrdersQuery($deliveryBoy)
                ->where('id', $orderId)
                ->with([
                    'items' => function ($query) {
                        // Hide items the rider can no longer act on — keeps the rider
                        // view aligned with the delivery-side state machine.
                        $query->whereNotIn('status', [
                            OrderItemStatusEnum::REJECTED(),
                            OrderItemStatusEnum::CANCELLED(),
                            OrderItemStatusEnum::FAILED(),
                        ]);
                    }, 'sellerOrders', 'sellerOrders.seller.user', 'user'])
                ->first();

            if (! $order) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found_or_not_available'),
                    data: []
                );
            }
            // Update the order with the delivery boy's ID and change status to ASSIGNED
            $order->update([
                'delivery_boy_id' => $deliveryBoy->id,
                'status' => OrderStatusEnum::ASSIGNED(),
            ]);

            // Load the order with relationships for earnings calculation
            $order->load('deliveryZone');

            // Calculate delivery route starting from rider's position
            $storeIds = $order->items->pluck('store_id')->unique()->toArray();
            $riderLoc = DeliveryBoyLocation::where('delivery_boy_id', $deliveryBoy->id)->first();
            $deliveryRoute = DeliveryZoneService::calculateDeliveryRoute(
                $order->shipping_latitude,
                $order->shipping_longitude,
                $storeIds,
                $order,
                $riderLoc ? (float) $riderLoc->latitude : null,
                $riderLoc ? (float) $riderLoc->longitude : null,
            );

            // Add delivery route to order
            $order->delivery_route = $deliveryRoute;

            // Calculate earnings
            $earnings = $order->delivery_boy_earnings;

            // Create a new entry in the delivery_boy_assignments table if no active assignment exists
            $existingAssignment = DeliveryBoyAssignment::where('order_id', $order->id)
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->whereNotIn('status', [
                    DeliveryBoyAssignmentStatusEnum::CANCELED(),
                    DeliveryBoyAssignmentStatusEnum::DROPPED(),
                ])
                ->first();

            if (! $existingAssignment) {
                DeliveryBoyAssignment::create([
                    'order_id' => $order->id,
                    'delivery_boy_id' => $deliveryBoy->id,
                    'assigned_at' => now(),
                    'status' => DeliveryBoyAssignmentStatusEnum::ASSIGNED(),
                    'base_fee' => $earnings['breakdown']['base_fee'] ?? 0,
                    'per_store_pickup_fee' => $earnings['breakdown']['per_store_pickup_fee'] ?? 0,
                    'distance_based_fee' => $earnings['breakdown']['distance_based_fee'] ?? 0,
                    'per_order_incentive' => $earnings['breakdown']['per_order_incentive'] ?? 0,
                    'total_earnings' => (float) (($earnings['total'] ?? 0) + ($order->rider_tip_amount ?? 0)),
                ]);
            }
            $orderItem = $order->items->first();
            if (
                ! $orderItem ||
                ! $orderItem->store ||
                ! $orderItem->store->seller
            ) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_not_found'),
                    data: []
                );
            }
            $orderItem->sellerOrder = $order->sellerOrders;
            $orderItem->order->user = $order->user;
            $orderItem->store->seller->user = $order->user;
            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $order->status,
                newStatus: DeliveryBoyAssignmentStatusEnum::ASSIGNED()
            ));
            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.order_accepted_successfully'),
                data: [
                    'order' => new DeliveryBoyOrderResource($order),
                ]
            );
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error').': '.$e->getMessage(),
                data: []
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get Delivery Boy's Orders
     */
    public function getMyOrders(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $deliveryBoy = $user->deliveryBoy;

            // Get page size from request or use default
            $perPage = $request->input('per_page', 10);

            // Get status filter from request if provided
            $status = $request->input('status');

            // Validate status if provided
            if ($status && ! in_array($status, DeliveryBoyAssignmentStatusEnum::values())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.invalid_status_provided'),
                    data: ['valid_statuses' => DeliveryBoyAssignmentStatusEnum::values()]
                );
            }

            $orders = Order::where(function ($query) use ($deliveryBoy, $status) {
                if ($status) {
                    $query->whereHas('deliveryBoyAssignments', function ($q) use ($deliveryBoy, $status) {
                        $q->where('delivery_boy_id', $deliveryBoy->id)
                            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY());

                        if ($status === DeliveryBoyAssignmentStatusEnum::CANCELED()) {
                            $q->whereIn('status', [
                                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                                DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
                            ]);
                        } else {
                            $q->where('status', $status);
                        }
                    });
                } else {
                    $query->where('delivery_boy_id', $deliveryBoy->id)
                        ->orWhereHas('deliveryBoyAssignments', function ($q) use ($deliveryBoy) {
                            $q->where('delivery_boy_id', $deliveryBoy->id)
                                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY());
                        });
                }
            })
                ->withDeliveryBoyEarnings()
                ->with([
                    'deliveryBoyAssignments' => function ($query) use ($deliveryBoy) {
                        $query->where('delivery_boy_id', $deliveryBoy->id)
                            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY());
                    },
                    'items',
                ])
                ->orderByDesc(
                    DeliveryBoyAssignment::select('assigned_at')
                        ->whereColumn('order_id', 'orders.id')
                        ->where('delivery_boy_id', $deliveryBoy->id)
                        ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                        ->orderByDesc('assigned_at')
                        ->limit(1)
                )
                ->paginate($perPage);
            // Rider location for route origin
            $riderLoc = DeliveryBoyLocation::where('delivery_boy_id', $deliveryBoy->id)->first();

            // Calculate delivery route for each order
            foreach ($orders->items() as $order) {
                // Get store IDs from order items
                $storeIds = $order->items->pluck('store_id')->unique()->toArray();

                // Calculate delivery route starting from rider's position
                $deliveryRoute = DeliveryZoneService::calculateDeliveryRoute(
                    $order->shipping_latitude,
                    $order->shipping_longitude,
                    $storeIds,
                    $order,
                    $riderLoc ? (float) $riderLoc->latitude : null,
                    $riderLoc ? (float) $riderLoc->longitude : null,
                );

                // Add delivery route to order
                $order->delivery_route = $deliveryRoute;
            }

            // Create a resource collection
            $resourceCollection = DeliveryBoyOrderResource::collection($orders);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.orders_fetched_successfully'),
                data: [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                    'filters' => [
                        'status' => $status ?: 'all',
                        'available_statuses' => DeliveryBoyAssignmentStatusEnum::values(),
                    ],
                    'orders' => $resourceCollection,
                ]
            );
        } catch (\Exception $e) {
            dd($e);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get order details
     */
    public function getOrderDetails(Request $request, int $orderId): JsonResponse
    {
        try {
            $user = $request->user();
            $deliveryBoy = $user->deliveryBoy;

            // Find the order
            $order = Order::where('id', $orderId)
                ->where(function ($query) use ($deliveryBoy) {
                    $query->where('delivery_boy_id', $deliveryBoy->id)
                        ->orWhereHas('deliveryBoyAssignments', function ($q) use ($deliveryBoy) {
                            $q->where('delivery_boy_id', $deliveryBoy->id)
                                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY());
                        });
                })
                ->withDeliveryBoyEarnings()
                ->with($this->deliveryBoyOrderRelations($deliveryBoy))
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $order) {
                $order = $this->availableDeliveryOrdersQuery($deliveryBoy)
                    ->where('id', $orderId)
                    ->withDeliveryBoyEarnings()
                    ->with($this->deliveryBoyOrderRelations())
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
            if (! $order) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found_or_not_available'),
                    data: []
                );
            }

            // Calculate delivery route starting from rider's position
            $storeIds = $order->items->pluck('store_id')->unique()->toArray();
            $riderLoc = DeliveryBoyLocation::where('delivery_boy_id', $deliveryBoy->id)->first();
            $deliveryRoute = DeliveryZoneService::calculateDeliveryRoute(
                $order->shipping_latitude,
                $order->shipping_longitude,
                $storeIds,
                $order,
                $riderLoc ? (float) $riderLoc->latitude : null,
                $riderLoc ? (float) $riderLoc->longitude : null,
            );

            // Add delivery route to order
            $order->delivery_route = $deliveryRoute;

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.order_fetched_successfully'),
                data: [
                    'order' => new DeliveryBoyOrderResource($order),
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Update order item status
     */
    public function updateOrderItemStatus(UpdateOrderItemStatusRequest $request, int $orderItemId): JsonResponse
    {
        try {

            // If status is 'delivered', check if OTP is required
            if ($request->input('status') === OrderItemStatusEnum::DELIVERED()) {
                // Find the order item to check if it requires OTP
                $orderItem = OrderItem::with('product')->findOrFail($orderItemId);

                // Check the order item snapshot (falling back to product setting for legacy orders)
                $requiresOtp = $orderItem->requires_otp ?? $orderItem->product?->requires_otp ?? false;
                if ($requiresOtp && ! $request->filled('otp')) {
                    throw ValidationException::withMessages([
                        'otp' => [__('validation.otp_required')],
                    ]);
                }
            }

            $user = $request->user();
            $deliveryBoy = $user->deliveryBoy;
            $status = $request->input('status');
            $otp = $request->input('otp');

            // Update the order item status
            $result = $this->orderService->updateOrderItemStatusByDeliveryBoy(
                $orderItemId,
                $status,
                $deliveryBoy->id,
                $otp ?? null
            );

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $result['message'],
                    data: $result['data']
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $result['message'],
                data: $result['data']
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error').':- '.$e->getMessage(),
                data: ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong').':- '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }

    }

    /**
     * Bulk collect all preparing order items for a specific store in an order.
     *
     * @return JsonResponse
     */
    public function collectAll(BulkCollectOrderRequest $request, int $orderId)
    {
        try {
            /** @var User $user */
            $user = auth()->user();
            $deliveryBoy = $user->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.delivery_boy_profile_not_found'),
                    data: [],
                    status: 403
                );
            }

            $result = $this->orderService->bulkCollectOrderItems(
                $orderId,
                $request->validated('store_id'),
                $deliveryBoy->id
            );

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $result['message'],
                    data: $result['data'] ?? []
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $result['message'],
                data: [
                    'order' => new DeliveryBoyOrderResource($result['data']['order']),
                    'order_items' => DeliveryBoyOrderItemResource::collection($result['data']['order_items']),
                ]
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error').':- '.$e->getMessage(),
                data: ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong').':- '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Bulk deliver all collected order items in an order.
     *
     * @return JsonResponse
     */
    public function deliverAll(BulkDeliverOrderRequest $request, int $orderId)
    {
        try {
            /** @var User $user */
            $user = auth()->user();
            $deliveryBoy = $user->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.delivery_boy_profile_not_found'),
                    data: [],
                    status: 403
                );
            }

            $result = $this->orderService->bulkDeliverOrderItems(
                $orderId,
                $deliveryBoy->id,
                $request->validated('otp')
            );

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $result['message'],
                    data: $result['data'] ?? []
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $result['message'],
                data: [
                    'order' => new DeliveryBoyOrderResource($result['data']['order']),
                    'order_items' => DeliveryBoyOrderItemResource::collection($result['data']['order_items']),
                ]
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error').':- '.$e->getMessage(),
                data: ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong').':- '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Drop an order — rider voluntarily abandons it.
     *
     * Only allowed before any item has been collected. The order is pushed
     * back to READY_FOR_PICKUP so other riders can claim it, the rider earns
     * nothing on the assignment, and their drop_count is incremented. Once
     * even a single item is COLLECTED the drop is refused — the rider must
     * either complete delivery or escalate via markDeliveryFailed.
     *
     * @param  int  $orderId  Order id the rider is currently assigned to.
     */
    public function dropOrder(DropOrderRequest $request, int $orderId): JsonResponse
    {
        try {
            $user = $request->user();
            $deliveryBoy = $user?->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(false, __('labels.not_a_delivery_boy'), []);
            }

            // Defense in depth — the rider auth gate already blocks login for
            // blocked riders, but tokens issued before the block could still
            // reach this endpoint.
            if ($deliveryBoy->is_blocked) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.delivery_boy_account_blocked'),
                    data: ['blocked_reason' => $deliveryBoy->blocked_reason]
                );
            }

            $result = $this->orderService->dropOrderByDeliveryBoy(
                orderId: $orderId,
                deliveryBoyId: $deliveryBoy->id,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Mark a collected item's delivery attempt as failed.
     *
     * Item must currently be COLLECTED. Sets delivery_fail_reason, records the
     * DELIVERY_FAILED event, then auto-transitions to RETURNING_TO_STORE so
     * the rider physically returns the goods. Rider earnings are preserved —
     * the failure isn't their fault.
     *
     * @param  int  $orderItemId  Order item id the rider was holding.
     */
    public function markDeliveryFailed(MarkDeliveryFailedRequest $request, int $orderItemId): JsonResponse
    {
        try {
            $user = $request->user();
            $deliveryBoy = $user?->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(false, __('labels.not_a_delivery_boy'), []);
            }

            if ($deliveryBoy->is_blocked) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.delivery_boy_account_blocked'),
                    data: ['blocked_reason' => $deliveryBoy->blocked_reason]
                );
            }

            $result = $this->orderService->markDeliveryFailed(
                orderItemId: $orderItemId,
                deliveryBoyId: $deliveryBoy->id,
                reasonCode: $request->validated('reason_code'),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Base query for unassigned orders a rider may preview or accept.
     */
    private function availableDeliveryOrdersQuery(DeliveryBoy $deliveryBoy)
    {
        return Order::where('delivery_zone_id', $deliveryBoy->delivery_zone_id)
            ->whereNull('delivery_boy_id')
            ->where('status', OrderStatusEnum::READY_FOR_PICKUP())
            ->whereDoesntHave('sellerOrders', function (Builder $query): void {
                $query->where('delivery_type', OrderDeliveryTypeEnum::PICKUP());
            })
            ->whereDoesntHave('deliveryBoyAssignments', function (Builder $query) use ($deliveryBoy): void {
                $query->where('delivery_boy_id', $deliveryBoy->id)
                    ->whereIn('status', [
                        DeliveryBoyAssignmentStatusEnum::DROPPED(),
                        DeliveryBoyAssignmentStatusEnum::CANCELED(),
                    ]);
            });
    }

    /**
     * Relationships needed by the delivery-boy order response.
     *
     * @return array<string, \Closure>
     */
    private function deliveryBoyOrderRelations(?DeliveryBoy $deliveryBoy = null): array
    {
        $relations = [
            'items',
        ];

        if ($deliveryBoy) {
            $relations['deliveryBoyAssignments'] = function ($query) use ($deliveryBoy): void {
                $query->where('delivery_boy_id', $deliveryBoy->id);
            };
        }

        return $relations;
    }

    /**
     * Get the COD online payment link and available gateways for an order.
     * Allows delivery boy to show QR/link at customer's doorstep.
     */
    public function getOnlinePaymentLink(Request $request, int $orderId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $deliveryBoy = $user?->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(false, __('labels.not_a_delivery_boy'), []);
            }

            $order = Order::where('id', $orderId)
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->first();

            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), [], 404);
            }

            // Ensure the order is COD and not already paid
            if ((string) $order->payment_method !== (string) PaymentTypeEnum::COD() ||
                (string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED() ||
                $order->is_cod_paid_online) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.online_payment_not_applicable_for_this_order'),
                    ['order' => new DeliveryBoyOrderResource($order)],
                    400
                );
            }

            // Get available gateways from CodPaymentService
            $codPaymentService = app(CodPaymentService::class);
            $gateways = $codPaymentService->availableGateways($order);

            $paymentUrl = route('cod.public.payment.show', ['token' => $order->uuid]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.payment_link_generated_successfully'),
                data: [
                    'order_id' => $order->id,
                    'uuid' => $order->uuid,
                    'amount' => $order->total_payable,
                    'currency_code' => $order->currency_code,
                    'payment_url' => $paymentUrl,
                    'available_gateways' => $gateways,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong').': '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Poll COD online payment status for an order.
     * Delivery boy app polls this while customer is paying via doorstep QR/link.
     */
    public function getPaymentStatus(Request $request, int $orderId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $deliveryBoy = $user?->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(false, __('labels.not_a_delivery_boy'), []);
            }

            $order = Order::where('id', $orderId)
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->first();

            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), [], 404);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.payment_status_fetched_successfully'),
                data: [
                    'order_id' => $order->id,
                    'uuid' => $order->uuid,
                    'payment_status' => $order->payment_status,
                    'is_cod_paid_online' => (bool) $order->is_cod_paid_online,
                    'cod_online_gateway' => $order->cod_online_gateway,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong').': '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Cancel / clear pending online payment reference if customer switches back to physical cash.
     */
    public function cancelOnlinePayment(Request $request, int $orderId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $deliveryBoy = $user?->deliveryBoy;

            if (! $deliveryBoy) {
                return ApiResponseType::sendJsonResponse(false, __('labels.not_a_delivery_boy'), []);
            }

            $order = Order::where('id', $orderId)
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->first();

            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), [], 404);
            }

            if (! $order->is_cod_paid_online) {
                $order->update([
                    'cod_online_reference' => null,
                    'cod_online_gateway' => null,
                ]);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.online_payment_cancelled_successfully'),
                data: [
                    'order' => new DeliveryBoyOrderResource($order),
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong').': '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }
}
