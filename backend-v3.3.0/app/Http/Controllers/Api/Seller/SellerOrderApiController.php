<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\DateRangeFilterEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\BulkUpdateSellerOrderItemStatusRequest;
use App\Http\Requests\Order\CancelSellerOrderItemRequest;
use App\Http\Requests\Order\ConfirmStoreReturnRequest;
use App\Http\Requests\Order\VerifyPickupOtpRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\Store;
use App\Services\CurrencyService;
use App\Services\OrderService;
use App\Services\PickupService;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Orders')]
class SellerOrderApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OrderService $orderService,
        protected CurrencyService $currencyService,
        protected PickupService $pickupService
    ) {}

    /**
     * List seller orders with filters and pagination
     */
    #[QueryParameter('page', description: 'Page number for pagination', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('status', description: 'Filter by order item status', type: 'string', example: 'processing')]
    #[QueryParameter('payment_type', description: 'Filter by payment method', type: 'string', example: 'cod')]
    #[QueryParameter('range', description: 'Date range filter from DateRangeFilterEnum', type: 'string', example: 'LAST_7_DAYS')]
    #[QueryParameter('search', description: 'Search by order id, buyer name, product/variant title', type: 'string', example: 'John')]
    #[QueryParameter('sort_by', description: 'Sort column: id|order_id|price|status|created_at', type: 'string', default: 'id')]
    #[QueryParameter('sort_dir', description: 'Sort direction asc|desc', type: 'string', default: 'desc')]
    #[QueryParameter('store_id', description: 'Filter items by a specific store owned by the seller', type: 'int', example: 123)]
    #[QueryParameter('order_type', description: 'Filter by order type (pos, regular, pickup)', type: 'string', example: 'pickup')]
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $seller = $user?->seller();
        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $perPage = (int) $request->integer('per_page', 15);
        $searchValue = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $paymentType = $request->input('payment_type');
        $dateRange = $request->input('range');
        $orderType = $request->input('order_type');
        $sortBy = in_array($request->input('sort_by'), ['id', 'order_id', 'price', 'total_price', 'created_at']) ? $request->input('sort_by') : 'id';
        if ($sortBy === 'price') {
            $sortBy = 'total_price';
        }
        $sortDir = strtolower($request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

        // Optional store filter with ownership validation
        $storeId = $request->integer('store_id');
        if ($storeId) {
            $storeValid = Store::query()->where('id', $storeId)->where('seller_id', $seller->id)->exists();
            if (! $storeValid) {
                return ApiResponseType::sendJsonResponse(false, 'You are not authorized to access this store.', null, 403);
            }
        } else {
            $storeId = null;
        }

        $itemsRelation = function ($q) use ($storeId) {
            $q->with([
                'product',
                'variant',
                'orderItem.store',
                'orderItem.addons.addonGroup',
                'orderItem.addons.addonItem',
            ]);
            if ($storeId) {
                $q->whereHas('orderItem', fn ($qq) => $qq->where('store_id', $storeId));
            }
        };

        $query = SellerOrder::with([
            'order.deliveryTimeSlot',
            'order.deliveryZone',
            'order.deliveryBoy.user',
            'order.sellerOrders',
            'order.items',
            'items' => $itemsRelation,
        ])
            ->where('seller_id', $seller->id)
            ->when($storeId, function ($q) use ($storeId) {
                $q->whereHas('items.orderItem', function ($qq) use ($storeId) {
                    $qq->where('store_id', $storeId);
                });
            });

        // [CHANGE] Default to active/ongoing statuses (pending, awaiting_store_response, accepted, preparing, ready_for_pickup) when no status filter is supplied. Other statuses are filtered when a status parameter is passed.
        if (! empty($status)) {
            if ($status === OrderStatusEnum::READY_FOR_PICKUP() || $status === 'ready_for_pickup') {
                $query->where(function ($q) {
                    $q->whereNotNull('ready_for_pickup_at')->whereNull('picked_up_at')
                        ->orWhereHas('items.orderItem', fn ($oi) => $oi->where('status', 'ready_for_pickup'));
                });
            } else {
                $query->where(function ($q) use ($status) {
                    $q->whereHas('order', fn ($qo) => $qo->where('status', $status))
                        ->orWhereHas('items.orderItem', fn ($oi) => $oi->where('status', $status));
                });
            }
        } else {
            $defaultStatuses = [
                OrderItemStatusEnum::PENDING(),
                OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
                OrderItemStatusEnum::ACCEPTED(),
                OrderItemStatusEnum::PREPARING(),
            ];
            $query->where(function ($q) use ($defaultStatuses) {
                $q->whereHas('items.orderItem', fn ($oi) => $oi->whereIn('status', $defaultStatuses))
                    ->orWhere(fn ($so) => $so->whereNotNull('ready_for_pickup_at')->whereNull('picked_up_at'));
            });
        }

        if (! empty($paymentType)) {
            $query->whereHas('order', function ($q) use ($paymentType) {
                $q->where('payment_method', $paymentType);
            });
        }

        if (! empty($dateRange)) {
            $fromDate = $this->getDateRange($dateRange);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }
        }

        if (! empty($orderType)) {
            if ($orderType === 'pos') {
                $query->whereHas('order', fn ($q) => $q->where('created_by', OrderCreatedByEnum::SELLER()));
            } elseif ($orderType === 'regular') {
                $query->whereHas('order', fn ($q) => $q->where(fn ($qq) => $qq->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER())))
                    ->where('delivery_type', '!=', OrderDeliveryTypeEnum::PICKUP());
            } elseif ($orderType === 'pickup') {
                $query->where('delivery_type', OrderDeliveryTypeEnum::PICKUP());
            }
        }

        if ($request->has('is_scheduled')) {
            $isScheduled = $request->boolean('is_scheduled');
            if ($isScheduled) {
                $query->whereHas('order', function ($q) {
                    $q->where(function ($qq) {
                        $qq->whereNotNull('scheduled_date')->orWhereNotNull('delivery_time_slot_id');
                    })->where(function ($qq) {
                        $qq->whereDate('scheduled_date', '>', Carbon::today())
                            ->orWhere(function ($qToday) {
                                $qToday->whereDate('scheduled_date', Carbon::today())
                                    ->where('status', OrderStatusEnum::ACCEPTED_BY_SELLER());
                            });
                    });
                });
            } else {
                $query->whereHas('order', function ($q) {
                    $q->where(function ($qq) {
                        $qq->whereNull('scheduled_date')->whereNull('delivery_time_slot_id');
                    })->orWhere(function ($qq) {
                        $qq->whereNotNull('scheduled_date')
                            ->where(function ($qSub) {
                                $qSub->whereDate('scheduled_date', '<', Carbon::today())
                                    ->orWhere(function ($qToday) {
                                        $qToday->whereDate('scheduled_date', Carbon::today())
                                            ->where('status', '!=', OrderStatusEnum::ACCEPTED_BY_SELLER());
                                    });
                            });
                    });
                });
            }
        } else {
            // Default: include regular orders AND scheduled orders that have reached their scheduled delivery date today or earlier
            $query->whereHas('order', fn ($q) => $q->where(fn ($qq) => $qq->whereNull('scheduled_date')->whereNull('delivery_time_slot_id'))->orWhere(fn ($qq) => $qq->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<=', Carbon::today())));
        }

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%$searchValue%")
                    ->orWhere('order_id', 'like', "%$searchValue%")
                    ->orWhere('total_price', 'like', "%$searchValue%")
                    ->orWhereHas('order', function ($oq) use ($searchValue) {
                        $oq->where('shipping_name', 'like', "%$searchValue%")
                            ->orWhere('order_id', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('items.orderItem', function ($orderItemQuery) use ($searchValue) {
                        $orderItemQuery->where('status', 'like', "%$searchValue%")
                            ->orWhere('sku', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('items.product', function ($productQuery) use ($searchValue) {
                        $productQuery->whereEnglishTextLike($searchValue);
                    })
                    ->orWhereHas('items.variant', function ($variantQuery) use ($searchValue) {
                        $variantQuery->where('title', 'like', "%$searchValue%");
                    });
            });
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            return (new OrderResource($item))->resolve();
        });

        $response = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ];

        return ApiResponseType::sendJsonResponse(true, __('labels.orders_fetched_successfully') ?? 'Orders fetched successfully', $response);
    }

    /**
     * Show a seller order (not a global Order). ID is seller_orders.id
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $seller = $user?->seller();
        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $sellerOrder = SellerOrder::where('order_id', $id)
            ->with(['order.deliveryTimeSlot', 'items.product', 'items.variant', 'items.orderItem.store', 'items.orderItem.addons.addonGroup', 'items.orderItem.addons.addonItem', 'order.items.store'])
            ->where('seller_id', $seller->id)
            ->first();

        if (! $sellerOrder) {
            return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', null, 404);
        }

        try {
            $this->authorize('view', $sellerOrder);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $resource = new OrderResource($sellerOrder);

        return ApiResponseType::sendJsonResponse(true, __('labels.order_fetched_successfully') ?? 'Order fetched successfully', $resource);
    }

    /**
     * Bulk update status of seller order items.
     */
    public function bulkUpdateStatus(BulkUpdateSellerOrderItemStatusRequest $request): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), [], 404);
            }

            $validated = $request->validated();
            $status = $validated['status'];
            $itemIds = $validated['order_item_ids'] ?? [];
            $orderId = $validated['order_id'] ?? null;

            $query = SellerOrderItem::query()
                ->whereHas('sellerOrder', function ($q) use ($seller) {
                    $q->where('seller_id', $seller->id);
                });

            if (! empty($itemIds)) {
                $query->whereIn('order_item_id', $itemIds);
            } elseif ($orderId) {
                $query->whereHas('sellerOrder', function ($q) use ($orderId) {
                    $q->where('order_id', $orderId);
                });
            }

            $sellerOrderItems = $query->with('sellerOrder')->get();

            if ($sellerOrderItems->isEmpty()) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found') ?? 'No eligible order items found', [], 404);
            }

            $updated = [];
            $failed = [];

            foreach ($sellerOrderItems as $item) {
                try {
                    $this->authorize('updateStatus', $item);
                } catch (AuthorizationException) {
                    $failed[] = [
                        'order_item_id' => $item->order_item_id,
                        'message' => __('messages.unauthorized_action'),
                    ];
                    continue;
                }

                $result = $this->orderService->updateOrderStatusBySeller($item->order_item_id, $status, $seller->id);
                if ($result['success']) {
                    $updated[] = $item->order_item_id;
                } else {
                    $failed[] = [
                        'order_item_id' => $item->order_item_id,
                        'message' => $result['message'],
                    ];
                }
            }

            $success = count($updated) > 0;
            $message = $success
                ? (count($updated).' '.__('messages.order_status_updated_successfully'))
                : __('messages.order_status_update_failed');

            return ApiResponseType::sendJsonResponse($success, $message, [
                'updated_ids' => $updated,
                'failed' => $failed,
            ], $success ? 200 : 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('messages.order_status_update_failed') ?? 'Order status update failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of a seller's order item.
     * $id refers to global order_items.id to align with existing web route behavior.
     */
    public function updateStatus(int $id, string $status): JsonResponse
    {
        try {
            $seller = auth()->user()->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'));
            }

            $orderItem = SellerOrderItem::where('order_item_id', $id)
                ->whereHas('sellerOrder', function ($q) use ($seller) {
                    $q->where('seller_id', $seller->id);
                })
                ->first();

            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found') ?? 'Order item not found', []);
            }

            $this->authorize('updateStatus', $orderItem);

            $result = $this->orderService->updateOrderStatusBySeller($id, $status, $seller->id);
            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(false, $result['message'], $result['data'] ?? []);
            }

            return ApiResponseType::sendJsonResponse(true, $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('messages.unauthorized_action'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('messages.order_status_update_failed') ?? 'Order status update failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel an item the seller had already accepted (mobile entry point).
     * Mirrors the seller-web cancelItem and shares the same service call.
     *
     * @param  int  $id  Order item id (matches OrderItem.id, not SellerOrderItem.id)
     */
    public function cancelItem(CancelSellerOrderItemRequest $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $orderItem = OrderItem::find($id);
            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }

            $this->authorize('cancelItem', $orderItem);

            $result = $this->orderService->cancelOrderItemBySeller(
                orderItemId: $id,
                sellerId: $seller->id,
                reason: $request->validated('reason'),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Seller confirms physical receipt of items returning to the store
     * (mobile entry point). Mirrors the seller-web confirmReturn.
     *
     * @param  int  $id  Order item id
     */
    public function confirmReturn(ConfirmStoreReturnRequest $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $orderItem = OrderItem::find($id);
            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }

            $this->authorize('confirmReturn', $orderItem);

            $result = $this->orderService->confirmStoreReturn(
                orderItemId: $id,
                sellerId: $seller->id,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark seller order as ready for pickup.
     */
    public function markReadyForPickup(int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), [], 404);
            }

            $sellerOrder = SellerOrder::where('order_id', $id)
                ->where('seller_id', $seller->id)
                ->first();
            if (! $sellerOrder) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $this->authorize('pickupManage', $sellerOrder);

            $result = $this->pickupService->markReadyForPickup($sellerOrder);

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify pickup OTP and complete the order.
     */
    public function verifyPickupOtp(VerifyPickupOtpRequest $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), [], 404);
            }

            $sellerOrder = SellerOrder::where('order_id', $id)
                ->where('seller_id', $seller->id)
                ->first();

            if (! $sellerOrder) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $this->authorize('pickupManage', $sellerOrder);

            $result = $this->pickupService->verifyPickupOtp($sellerOrder, $request->validated('otp'));

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), ['error' => $e->getMessage()], 500);
        }
    }

    private function getDateRange($dateRange): ?Carbon
    {
        $fromDate = null;
        $now = Carbon::now();
        switch ($dateRange) {
            case DateRangeFilterEnum::LAST_30_MINUTES():
                $fromDate = $now->copy()->subMinutes(30);
                break;
            case DateRangeFilterEnum::LAST_1_HOUR():
                $fromDate = $now->copy()->subHour();
                break;
            case DateRangeFilterEnum::LAST_5_HOURS():
                $fromDate = $now->copy()->subHours(5);
                break;
            case DateRangeFilterEnum::LAST_1_DAY():
                $fromDate = $now->copy()->subDay();
                break;
            case DateRangeFilterEnum::LAST_7_DAYS():
                $fromDate = $now->copy()->subDays(7);
                break;
            case DateRangeFilterEnum::LAST_30_DAYS():
                $fromDate = $now->copy()->subDays(30);
                break;
            case DateRangeFilterEnum::LAST_365_DAYS():
                $fromDate = $now->copy()->subDays(365);
                break;
        }

        return $fromDate;
    }

    /**
     * Get all enums for filtering orders.
     */
    public function enums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.order_enums_retrieved_successfully',
            data: [
                'status' => OrderItemStatusEnum::values(),
                'range' => DateRangeFilterEnum::values(),
                'payment_type' => PaymentTypeEnum::values(),
            ]
        );
    }
}
