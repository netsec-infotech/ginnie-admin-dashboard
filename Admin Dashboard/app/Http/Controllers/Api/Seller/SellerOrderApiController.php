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
     * List seller order items with filters and pagination
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
        $sortBy = in_array($request->input('sort_by'), ['id', 'order_id', 'price', 'status', 'created_at']) ? $request->input('sort_by') : 'id';
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

        $query = SellerOrderItem::with(['sellerOrder', 'orderItem', 'orderItem.store', 'orderItem.addons.addonGroup', 'orderItem.addons.addonItem', 'variant', 'product', 'sellerOrder.order.items', 'sellerOrder.items.orderItem'])
            ->whereHas('sellerOrder', fn ($q) => $q->where('seller_id', $seller->id))
            ->when($storeId, function ($q) use ($storeId) {
                $q->whereHas('orderItem', function ($qq) use ($storeId) {
                    $qq->where('store_id', $storeId);
                });
            });

        // [CHANGE] Default to active/ongoing statuses (pending, awaiting_store_response, accepted, preparing, ready_for_pickup) when no status filter is supplied. Other statuses are filtered when a status parameter is passed.
        if (! empty($status)) {
            if ($status === OrderStatusEnum::READY_FOR_PICKUP() || $status === 'ready_for_pickup') {
                $query->where(function ($q) {
                    $q->whereHas('sellerOrder', fn ($so) => $so->whereNotNull('ready_for_pickup_at')->whereNull('picked_up_at'))
                        ->orWhereHas('orderItem', fn ($oi) => $oi->where('status', 'ready_for_pickup'));
                });
            } else {
                $query->whereHas('orderItem', fn ($q) => $q->where('status', $status));
            }
        } else {
            $defaultStatuses = [
                OrderItemStatusEnum::PENDING(),
                OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
                OrderItemStatusEnum::ACCEPTED(),
                OrderItemStatusEnum::PREPARING(),
            ];
            $query->where(function ($q) use ($defaultStatuses) {
                $q->whereHas('orderItem', fn ($oi) => $oi->whereIn('status', $defaultStatuses))
                    ->orWhereHas('sellerOrder', fn ($so) => $so->whereNotNull('ready_for_pickup_at')->whereNull('picked_up_at'));
            });
        }

        if (! empty($paymentType)) {
            $query->whereHas('sellerOrder', function ($q) use ($paymentType) {
                $q->whereHas('order', fn ($qo) => $qo->where('payment_method', $paymentType));
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
                $query->whereHas('sellerOrder.order', fn ($q) => $q->where('created_by', OrderCreatedByEnum::SELLER()));
            } elseif ($orderType === 'regular') {
                $query->whereHas('sellerOrder.order', fn ($q) => $q->where(fn ($qq) => $qq->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER())))
                    ->whereHas('sellerOrder', fn ($q) => $q->where('delivery_type', '!=', OrderDeliveryTypeEnum::PICKUP()));
            } elseif ($orderType === 'pickup') {
                $query->whereHas('sellerOrder', fn ($q) => $q->where('delivery_type', OrderDeliveryTypeEnum::PICKUP()));
            }
        }

        if ($request->has('is_scheduled')) {
            $isScheduled = $request->boolean('is_scheduled');
            if ($isScheduled) {
                $query->whereHas('sellerOrder.order', function ($q) {
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
                $query->whereHas('sellerOrder.order', function ($q) {
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
            $query->whereHas('sellerOrder.order', fn ($q) => $q->where(fn ($qq) => $qq->whereNull('scheduled_date')->whereNull('delivery_time_slot_id'))->orWhere(fn ($qq) => $qq->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<=', Carbon::today())));
        }

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('seller_order_id', 'like', "%$searchValue%")
                    ->orWhereHas('sellerOrder', function ($orderQuery) use ($searchValue) {
                        $orderQuery->where('total_price', 'like', "%$searchValue%")
                            ->orWhereHas('order', function ($oq) use ($searchValue) {
                                $oq->where('shipping_name', 'like', "%$searchValue%")
                                    ->orWhere('order_id', 'like', "%$searchValue%");
                            });
                    })
                    ->orWhereHas('orderItem', function ($orderItemQuery) use ($searchValue) {
                        $orderItemQuery->where('status', 'like', "%$searchValue%")
                            ->orWhere('sku', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($searchValue) {
                        $productQuery->where('title', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('variant', function ($variantQuery) use ($searchValue) {
                        $variantQuery->where('title', 'like', "%$searchValue%");
                    });
            });
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            return $this->transformOrderItem($item);
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
            ->with(['order', 'items.product', 'items.variant', 'items.orderItem', 'items.orderItem.addons.addonGroup', 'items.orderItem.addons.addonItem', 'order.items.store'])
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

    private function transformOrderItem(SellerOrderItem $sellerOrderItem): array
    {
        $variantTitle = (! empty($sellerOrderItem->product->type) && $sellerOrderItem->product->type === ProductTypeEnum::SIMPLE()) ? '' : ($sellerOrderItem->variant->title ?? '');
        $storeName = $sellerOrderItem->orderItem->store ? $sellerOrderItem->orderItem->store->name : 'N/A';

        $sellerOrder = $sellerOrderItem->sellerOrder;
        $sellerOrderStatus = $sellerOrder->order->status ?? null;
        if ($sellerOrder->picked_up_at !== null) {
            $sellerOrderStatus = OrderStatusEnum::DELIVERED();
        } elseif ($sellerOrder->ready_for_pickup_at !== null) {
            $sellerOrderStatus = OrderStatusEnum::READY_FOR_PICKUP();
        } else {
            $itemsStatuses = $sellerOrder->order?->relationLoaded('items')
                ? $sellerOrder->order->items->pluck('status')->filter()->toArray()
                : ($sellerOrder->relationLoaded('items')
                    ? $sellerOrder->items->map(fn ($item) => $item->orderItem?->status ?? null)->filter()->toArray()
                    : []);
            if (! empty($itemsStatuses)) {
                $sellerOrderStatus = app(OrderService::class)->determineSellerOrderStatus($itemsStatuses);
            }
        }

        return [
            'order_item_id' => $sellerOrderItem->order_item_id,
            'seller_order_id' => $sellerOrderItem->seller_order_id,
            'created_at' => $sellerOrderItem->created_at,
            'order' => [
                'id' => $sellerOrder->order_id,
                'image' => ! empty($sellerOrderItem->variant->image) ? $sellerOrderItem->variant->image : $sellerOrderItem->product?->main_image,
                'uuid' => $sellerOrder->order->uuid ?? null,
                'buyer_name' => $sellerOrder->order->shipping_name ?? null,
                'payment_method' => $sellerOrder->order->payment_method ?? null,
                'is_rush_order' => (bool) ($sellerOrder->order->is_rush_order ?? false),
                'status' => $sellerOrderStatus,
                'delivery_type' => $sellerOrder->delivery_type?->value ?? 'delivery',
                'ready_for_pickup_at' => $sellerOrder->ready_for_pickup_at?->format('M d, Y h:i A'),
                'picked_up_at' => $sellerOrder->picked_up_at?->format('M d, Y h:i A'),
                'scheduled_date' => $sellerOrder->order->scheduled_date?->format('Y-m-d'),
                'scheduled_date_formatted' => $sellerOrder->order->scheduled_date?->format('M d, Y'),
                'delivery_time_slot_id' => $sellerOrder->order->delivery_time_slot_id,
                'scheduled_time_slot_label' => $this->resolveScheduledSlotLabel($sellerOrder->order),
                'reschedule_status' => $sellerOrder->order->reschedule_status,
                'reschedule_reason' => $sellerOrder->order->reschedule_reason,
                'reschedule_deadline' => $sellerOrder->order->reschedule_deadline?->toISOString(),
                'is_reschedule_pending' => $sellerOrder->order->reschedule_status === 'needs_reschedule',
            ],
            'product' => [
                'id' => $sellerOrderItem->product?->id,
                'title' => $sellerOrderItem->product?->title,
                'variant' => $variantTitle,
            ],
            'store' => [
                'name' => $storeName,
            ],
            'sku' => $sellerOrderItem->orderItem->sku,
            'quantity' => (int) $sellerOrderItem->orderItem->quantity,
            'subtotal' => [
                'raw' => (float) $sellerOrderItem->orderItem->subtotal,
                'formatted' => $this->currencyService->format($sellerOrderItem->orderItem->subtotal),
            ],
            'status' => $sellerOrderItem->orderItem->status,
            'status_label' => formatOrderStatusLabel($sellerOrderItem->orderItem->status),
            'cancellation_reason' => $sellerOrderItem->orderItem->cancellation_reason,
            'delivery_fail_reason' => $sellerOrderItem->orderItem->delivery_fail_reason,
            // Addon snapshot lives on the OrderItem, not the SellerOrderItem.
            'addons' => $this->formatOrderItemAddons($sellerOrderItem->orderItem),
            'addons_total' => $this->sumOrderItemAddonsLineTotal($sellerOrderItem->orderItem),
        ];
    }

    /**
     * Flatten an OrderItem's addons into a scalar-friendly list. Mirrors the
     * shape emitted by `OrderResource::formatOrderItemAddons()` so listing and
     * detail responses stay consistent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatOrderItemAddons($orderItem): array
    {
        if (! $orderItem) {
            return [];
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        if (! $addons || $addons->isEmpty()) {
            return [];
        }

        return $addons->map(function ($addon) {
            $group = $addon->addonGroup;
            $item = $addon->addonItem;

            return [
                'id' => $addon->id,
                'uuid' => $addon->uuid,
                'addon_group_id' => $addon->addon_group_id,
                'addon_item_id' => $addon->addon_item_id,
                'price' => (float) $addon->price,
                'group' => $group ? [
                    'id' => $group->id,
                    'title' => $group->title,
                    'selection_type' => $group->selection_type?->value,
                    'is_required' => (bool) $group->is_required,
                ] : null,
                'item' => $item ? [
                    'id' => $item->id,
                    'title' => $item->title,
                    'indicator' => $item->indicator?->value,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Line-total addon contribution: `quantity × sum(addon.price)`.
     */
    private function sumOrderItemAddonsLineTotal($orderItem): float
    {
        if (! $orderItem) {
            return 0.0;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->get();

        if (! $addons || $addons->isEmpty()) {
            return 0.0;
        }

        return round(((int) $orderItem->quantity) * (float) $addons->sum(fn ($a) => (float) $a->price), 2);
    }

    private function resolveScheduledSlotLabel(?Order $order): ?string
    {
        if (! $order) {
            return null;
        }

        if (! empty($order->scheduled_slot_start_time) && ! empty($order->scheduled_slot_end_time)) {
            return Carbon::parse($order->scheduled_slot_start_time)->format('g:i A').' - '.Carbon::parse($order->scheduled_slot_end_time)->format('g:i A');
        }

        return $order->scheduled_time_slot_label;
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
