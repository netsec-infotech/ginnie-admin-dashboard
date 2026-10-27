<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DateRangeFilterEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Requests\Admin\Order\AdminAddOrderNoteRequest;
use App\Http\Requests\Admin\Order\AdminBulkUpdateItemStatusRequest;
use App\Http\Requests\Admin\Order\AdminForceCancelRequest;
use App\Http\Requests\Admin\Order\AdminForceRefundRequest;
use App\Http\Requests\Admin\Order\AdminReassignRiderRequest;
use App\Http\Requests\Admin\Order\SettleRiderEarningsRequest;
use App\Http\Requests\Order\CancelSellerOrderItemRequest;
use App\Http\Requests\Order\ConfirmStoreReturnRequest;
use App\Http\Requests\Order\VerifyPickupOtpRequest;
use App\Http\Requests\Seller\Order\SellerBulkUpdateItemStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyLocation;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\PosRefund;
use App\Models\PosRefundLine;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\Store;
use App\Services\CurrencyService;
use App\Services\DeliveryZoneService;
use App\Services\OrderService;
use App\Services\PickupService;
use App\Services\PosRefundService;
use App\Services\PreparationSchedulerService;
use App\Services\WhatsAppService;
use App\Support\WhatsAppTemplateCatalog;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    public bool $editPermission = false;

    protected OrderService $orderService;

    protected CurrencyService $currencyService;

    public function __construct(OrderService $orderService, CurrencyService $currencyService)
    {
        $this->orderService = $orderService;
        $this->currencyService = $currencyService;
        $user = auth()->user();
        if ($user) {
            if ($this->getPanel() === 'admin') {
                // Phase 3 — single ORDER_EDIT permission gates every admin write.
                $this->editPermission = $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            } else {
                $this->editPermission = $this->hasPermission(SellerPermissionEnum::ORDER_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            }
        }
    }

    /**
     * Display a listing of the seller's orders.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SellerOrder::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'order_date', 'name' => 'order_date', 'title' => __('labels.order_date'), 'orderable' => false, 'searchable' => false],
            ['data' => 'order_details', 'name' => 'order_details', 'title' => __('labels.order_details'), 'orderable' => false, 'searchable' => false],
            ['data' => 'product_details', 'name' => 'product_details', 'title' => __('labels.product_details'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions'), 'orderable' => false, 'searchable' => false],
        ];

        $orderColumns = [
            ['data' => 'expand', 'name' => 'expand', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.order_id')],
            ['data' => 'order_id', 'name' => 'order_id', 'title' => __('labels.uuid'), 'orderable' => false],
            ['data' => 'order_date', 'name' => 'created_at', 'title' => __('labels.order_date')],
            ['data' => 'buyer', 'name' => 'buyer', 'title' => __('labels.buyer_name'), 'orderable' => false],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'orderable' => false],
            ['data' => 'items_count', 'name' => 'items_count', 'title' => __('labels.items'), 'orderable' => false, 'searchable' => false],
            ['data' => 'total', 'name' => 'total_price', 'title' => __('labels.total'), 'orderable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions'), 'orderable' => false, 'searchable' => false],
        ];

        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $sellerId = null;
            if ($user) {
                $sellerId = $user->seller_id ?? Seller::where('user_id', $user->id)->value('id');
            }
            if (!$sellerId && session()->has('impersonating_as_seller')) {
                $sellerId = session('impersonating_as_seller');
            }

            $insightQuery = SellerOrder::query()->where('seller_id', $sellerId ?? 0);
            $this->applyRegularSellerOrderScope($insightQuery);
            $orderInsights = $this->getOrderInsights($insightQuery, 'items.orderItem');
        } else {
            $insightQuery = Order::query();
            $this->applyRegularOrderScope($insightQuery);
            $orderInsights = $this->getOrderInsights($insightQuery, 'items');
        }

        $activeStatusGroup = $this->normalizeStatusGroup($request->query('status_group'));

        return view($this->panelView('orders.index'), compact('columns', 'orderColumns', 'orderInsights', 'activeStatusGroup'));
    }

    /**
     * @return array<string, list<string>>
     */
    private function orderInsightStatusGroups(): array
    {
        return [
            'preparing' => [
                OrderItemStatusEnum::PREPARING(),
                OrderStatusEnum::ACCEPTED_BY_SELLER(),
                OrderStatusEnum::OUT_FOR_DELIVERY(),
                OrderStatusEnum::READY_FOR_PICKUP(),
            ],
            'delivered' => [OrderItemStatusEnum::DELIVERED()],
            'rejected' => [
                OrderItemStatusEnum::REJECTED(),
                OrderItemStatusEnum::CANCELLED(),
                OrderItemStatusEnum::FAILED(),
            ],
        ];
    }

    private function normalizeStatusGroup(mixed $statusGroup): ?string
    {
        if (! is_string($statusGroup)) {
            return null;
        }

        return array_key_exists($statusGroup, $this->orderInsightStatusGroups()) ? $statusGroup : null;
    }

    private function applyRegularOrderScope(Builder $query): Builder
    {
        return $query->where(function (Builder $scheduleQuery) {
            $scheduleQuery
                ->where(function (Builder $unscheduledQuery) {
                    $unscheduledQuery->whereNull('scheduled_date')->whereNull('delivery_time_slot_id');
                })
                ->orWhere(function (Builder $scheduledQuery) {
                    $scheduledQuery
                        ->whereNotNull('scheduled_date')
                        ->where(function (Builder $dateQuery) {
                            $dateQuery
                                ->whereDate('scheduled_date', '<', Carbon::today())
                                ->orWhere(function (Builder $todayQuery) {
                                    $todayQuery
                                        ->whereDate('scheduled_date', Carbon::today())
                                        ->where('status', '!=', OrderStatusEnum::ACCEPTED_BY_SELLER());
                                });
                        });
                });
        });
    }

    private function applyRegularSellerOrderScope(Builder $query): Builder
    {
        return $query->whereHas('order', function (Builder $orderQuery) {
            $this->applyRegularOrderScope($orderQuery);
        });
    }

    private function applyInsightStatusGroup(Builder $query, string $relation, ?string $statusGroup): Builder
    {
        $statusGroup = $this->normalizeStatusGroup($statusGroup);

        if ($statusGroup === null) {
            return $query;
        }

        $statuses = $this->orderInsightStatusGroups()[$statusGroup];

        return $query->whereHas($relation, function (Builder $relationQuery) use ($statuses) {
            $relationQuery->whereIn('status', $statuses);
        });
    }

    /**
     * @return array{total: int, preparing: int, delivered: int, rejected: int}
     */
    private function getOrderInsights(Builder $query, string $relation): array
    {
        return [
            'total' => (clone $query)->count(),
            'preparing' => $this->applyInsightStatusGroup(clone $query, $relation, 'preparing')->count(),
            'delivered' => $this->applyInsightStatusGroup(clone $query, $relation, 'delivered')->count(),
            'rejected' => $this->applyInsightStatusGroup(clone $query, $relation, 'rejected')->count(),
        ];
    }

    /**
     * Display a listing of scheduled delivery orders.
     */
    public function indexScheduled(): View
    {
        if ($this->getPanel() === 'seller') {
            $this->authorize('viewAny', SellerOrder::class);
        }

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'order_date', 'name' => 'order_date', 'title' => __('labels.order_date'), 'orderable' => false, 'searchable' => false],
            ['data' => 'scheduled_for', 'name' => 'scheduled_for', 'title' => __('labels.scheduled_for'), 'orderable' => false, 'searchable' => false],
            ['data' => 'preparation_starts_at', 'name' => 'preparation_starts_at', 'title' => __('labels.preparation_starts_at'), 'orderable' => false, 'searchable' => false],
            ['data' => 'order_details', 'name' => 'order_details', 'title' => __('labels.order_details'), 'orderable' => false, 'searchable' => false],
            ['data' => 'product_details', 'name' => 'product_details', 'title' => __('labels.product_details'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions'), 'orderable' => false, 'searchable' => false],
        ];

        $orderColumns = [
            ['data' => 'expand', 'name' => 'expand', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.order_id')],
            ['data' => 'order_id', 'name' => 'order_id', 'title' => __('labels.uuid'), 'orderable' => false],
            ['data' => 'order_date', 'name' => 'created_at', 'title' => __('labels.order_date')],
            ['data' => 'scheduled_for', 'name' => 'scheduled_for', 'title' => __('labels.scheduled_for'), 'orderable' => false, 'searchable' => false],
            ['data' => 'preparation_starts_at', 'name' => 'preparation_starts_at', 'title' => __('labels.preparation_starts_at'), 'orderable' => false, 'searchable' => false],
            ['data' => 'buyer', 'name' => 'buyer', 'title' => __('labels.buyer_name'), 'orderable' => false],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'orderable' => false],
            ['data' => 'items_count', 'name' => 'items_count', 'title' => __('labels.items'), 'orderable' => false, 'searchable' => false],
            ['data' => 'total', 'name' => 'total_price', 'title' => __('labels.total'), 'orderable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions'), 'orderable' => false, 'searchable' => false],
        ];

        return view($this->panelView('scheduled-orders.index'), compact('columns', 'orderColumns'));
    }

    public function getScheduledOrders(Request $request): JsonResponse
    {
        $request->merge(['is_scheduled' => true]);

        return $this->getOrders($request);
    }

    public function getScheduledOrdersGrouped(Request $request): JsonResponse
    {
        $request->merge(['is_scheduled' => true]);

        return $this->getOrdersGrouped($request);
    }

    /**
     * DataTable feed for the order-grouped listing tab.
     * One row per order (admin: per Order, seller: per SellerOrder).
     */
    public function getOrdersGrouped(Request $request): JsonResponse
    {
        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $status = $request->get('status');
        $statusGroup = $this->normalizeStatusGroup($request->get('status_group'));
        $paymentType = $request->get('payment_type');
        $dateRange = $request->get('range');
        $orderType = $request->get('order_type');
        $deliveryZoneId = $request->get('delivery_zone_id');
        $storeId = $request->get('store_id');

        $isSeller = $this->getPanel() === 'seller';

        if ($isSeller) {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $query = SellerOrder::with(['order.deliveryTimeSlot', 'order.items.store', 'items.orderItem.store'])
                ->withCount('items')
                ->where('seller_id', $seller->id);

            if ($statusGroup !== null) {
                $this->applyInsightStatusGroup($query, 'items.orderItem', $statusGroup);
            } elseif ($status !== null && $status !== '') {
                $query->whereHas('items.orderItem', fn($q) => $q->where('status', $status));
            }
            if ($paymentType !== null && $paymentType !== '') {
                $query->whereHas('order', fn($q) => $q->where('payment_method', $paymentType));
            }
            if ($orderType === 'pos') {
                $query->whereHas('order', fn($q) => $q->where('created_by', OrderCreatedByEnum::SELLER()));
            } elseif ($orderType === 'regular') {
                $query->whereHas('order', fn($q) => $q->where(fn($qq) => $qq->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER())))
                    ->where('delivery_type', '!=', OrderDeliveryTypeEnum::PICKUP());
            } elseif ($orderType === 'pickup') {
                $query->where('delivery_type', OrderDeliveryTypeEnum::PICKUP());
            }
            if ($deliveryZoneId !== null && $deliveryZoneId !== '') {
                $query->whereHas('order', fn($q) => $q->where('delivery_zone_id', (int) $deliveryZoneId));
            }
            if ($storeId !== null && $storeId !== '') {
                $query->whereHas('items.orderItem', fn($q) => $q->where('store_id', (int) $storeId));
            }
        } else {
            $query = Order::query()->with(['items.store', 'deliveryTimeSlot', 'sellerOrders'])->withCount('items');

            if ($statusGroup !== null) {
                $this->applyInsightStatusGroup($query, 'items', $statusGroup);
            } elseif ($status !== null && $status !== '') {
                $query->whereHas('items', fn($q) => $q->where('status', $status));
            }
            if ($paymentType !== null && $paymentType !== '') {
                $query->where('payment_method', $paymentType);
            }
            if ($orderType === 'pos') {
                $query->where('created_by', OrderCreatedByEnum::SELLER());
            } elseif ($orderType === 'regular') {
                $query->where(fn($q) => $q->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER()))
                    ->whereHas('sellerOrders', fn($q) => $q->where('delivery_type', '!=', OrderDeliveryTypeEnum::PICKUP()));
            } elseif ($orderType === 'pickup') {
                $query->whereHas('sellerOrders', fn($q) => $q->where('delivery_type', OrderDeliveryTypeEnum::PICKUP()));
            }
            if ($deliveryZoneId !== null && $deliveryZoneId !== '') {
                $query->where('delivery_zone_id', (int) $deliveryZoneId);
            }
        }

        $isScheduled = $request->boolean('is_scheduled') || str_contains($request->route()?->getName() ?? '', 'scheduled_orders');
        if ($isScheduled) {
            if ($isSeller) {
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
                $query->where(function ($q) {
                    $q->whereNotNull('scheduled_date')->orWhereNotNull('delivery_time_slot_id');
                })->where(function ($qq) {
                    $qq->whereDate('scheduled_date', '>', Carbon::today())
                        ->orWhere(function ($qToday) {
                            $qToday->whereDate('scheduled_date', Carbon::today())
                                ->where('status', OrderStatusEnum::ACCEPTED_BY_SELLER());
                        });
                });
            }
        } else {
            if ($isSeller) {
                $this->applyRegularSellerOrderScope($query);
            } else {
                $this->applyRegularOrderScope($query);
            }
        }

        if ($dateRange !== null && $dateRange !== '') {
            $fromDate = $this->getDateRange($dateRange);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }
        }

        $totalRecords = $query->count();

        if (! empty($searchValue)) {
            if ($isSeller) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('id', 'like', "%$searchValue%")
                        ->orWhere('total_price', 'like', "%$searchValue%")
                        ->orWhereHas('order', function ($oq) use ($searchValue) {
                            $oq->where('id', 'like', "%$searchValue%")
                                ->orWhere('uuid', 'like', "%$searchValue%")
                                ->orWhere('shipping_name', 'like', "%$searchValue%");
                        });
                });
            } else {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('id', 'like', "%$searchValue%")
                        ->orWhere('uuid', 'like', "%$searchValue%")
                        ->orWhere('shipping_name', 'like', "%$searchValue%")
                        ->orWhere('final_total', 'like', "%$searchValue%");
                });
            }
        }

        $filteredRecords = $query->count();

        $columnMap = [
            1 => 'id',
            3 => 'created_at',
        ];
        $orderColumnIndex = (int) ($request->get('order')[0]['column'] ?? 3);
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'created_at';

        // if ($isSeller) {
        //     $query->orderByRaw("CASE WHEN EXISTS (SELECT 1 FROM seller_order_items JOIN order_items ON seller_order_items.order_item_id = order_items.id WHERE seller_order_items.seller_order_id = seller_orders.id AND order_items.status IN ('pending', 'awaiting_store_response')) THEN 0 ELSE 1 END");
        // } else {
        //     $query->orderByRaw("CASE WHEN EXISTS (SELECT 1 FROM order_items WHERE order_items.order_id = orders.id AND order_items.status IN ('pending', 'awaiting_store_response')) THEN 0 ELSE 1 END");
        // }

        $rows = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows->map(fn($row) => $this->formatOrderGroupedRow($row, $isSeller));

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    private function formatOrderGroupedRow($row, bool $isSeller): array
    {
        $expand = '<button type="button" class="btn btn-icon btn-ghost-secondary expand-order-items" data-order-id="' . (int) $row->id . '" aria-label="' . e(__('labels.order_items')) . '"><i class="ti ti-chevron-right"></i></button>';

        if ($isSeller) {
            $id = $row->order_id ?? $row->id;
            $order = $row->order;
            $publicId = $order?->uuid ?? $order?->id ?? '—';
            $buyer = $order?->shipping_name ?? '—';
            $paymentMethod = $order?->payment_method ?? '—';
            $statusValue = $this->resolveOrderDisplayStatus($row);
            $total = $row->total_price ?? 0;
            if ($order) {
                $parentSubtotal = (float) $order->subtotal;
                if ($parentSubtotal > 0) {
                    $ratio = (float) $total / $parentSubtotal;
                    $total = (float) $order->total_payable * $ratio;
                } else {
                    $total = (float) $order->total_payable;
                }
            }
            $isScheduledRoute = request()->boolean('is_scheduled') || str_contains(request()->route()?->getName() ?? '', 'scheduled_orders');
            $detailRoute = route($isScheduledRoute ? 'seller.scheduled_orders.show' : 'seller.orders.show', $row->id);
        } else {
            $publicId = $row->uuid ?? $row->id;
            $buyer = $row->shipping_name ?? '—';
            $paymentMethod = $row->payment_method ?? '—';
            $statusValue = $this->resolveOrderDisplayStatus($row);
            $total = $row->final_total ?? 0;
            $isScheduledRoute = request()->boolean('is_scheduled') || str_contains(request()->route()?->getName() ?? '', 'scheduled_orders');
            $detailRoute = route($isScheduledRoute ? 'admin.scheduled_orders.show' : 'admin.orders.show', $row->id);
        }

        $itemsCount = (int) ($row->items_count ?? 0);
        $itemsBadge = '<span class="badge bg-secondary-lt"><i class="ti ti-package me-1"></i>'
            . $itemsCount . ' ' . ($itemsCount === 1 ? e(__('labels.item')) : e(__('labels.items'))) . '</span>';

        $createdBy = $isSeller ? ($order?->created_by ?? null) : ($row->created_by ?? null);
        $isPosOrder = $createdBy instanceof OrderCreatedByEnum
            ? $createdBy === OrderCreatedByEnum::SELLER
            : ($createdBy === OrderCreatedByEnum::SELLER());
        $posBadge = $isPosOrder
            ? ' <span class="badge bg-purple-lt ms-1">' . e(__('labels.pos')) . '</span>'
            : '';

        $isPickup = $isSeller
            ? (($row->delivery_type?->value ?? $row->delivery_type) === 'pickup')
            : ($row->sellerOrders->first()?->delivery_type?->value ?? $row->sellerOrders->first()?->delivery_type) === 'pickup';
        $pickupBadge = $isPickup
            ? ' <span class="badge bg-green-lt ms-1">' . e(__('labels.self_pickup')) . '</span>'
            : '';

        $uuid = $isSeller ? ($order?->uuid ?? '') : ($row->uuid ?? '');
        $invoiceUrl = $isSeller
            ? route($isScheduledRoute ? 'seller.scheduled_orders.invoice' : 'seller.orders.invoice', ['id' => $uuid])
            : route($isScheduledRoute ? 'admin.scheduled_orders.invoice' : 'admin.orders.invoice', ['id' => $uuid]);

        $actionsHtml = '<div class="btn-list flex-nowrap">';
        $actionsHtml .= '<a href="' . $detailRoute . '" class="btn btn-icon btn-outline-primary" title="' . e(__('labels.view')) . '" data-bs-toggle="tooltip"><i class="ti ti-eye"></i></a>';
        if ($uuid) {
            $actionsHtml .= '<a href="' . $invoiceUrl . '" class="btn btn-icon btn-outline-secondary" target="_blank" title="' . e(__('labels.download_invoice')) . '" data-bs-toggle="tooltip"><i class="ti ti-invoice"></i></a>';
        }
        $actionsHtml .= '</div>';

        $scheduledForHtml = '—';
        $preparationStartsAtHtml = '—';
        $targetOrder = $isSeller ? ($order ?? null) : $row;
        $isReschedulePending = $targetOrder?->reschedule_status === 'needs_reschedule';
        $rescheduleBadge = $isReschedulePending
            ? ' <span class="badge bg-warning-lt text-warning ms-1"><i class="ti ti-calendar-exclamation me-1"></i>' . e(__('labels.needs_reschedule')) . '</span>'
            : '';
        if ($targetOrder && ($targetOrder->scheduled_date || $targetOrder->delivery_time_slot_id)) {
            $scheduledDateStr = $targetOrder->scheduled_date?->format('d M, Y') ?? '—';
            $slotLabel = $this->resolveScheduledSlotLabel($targetOrder);
            $scheduledForHtml = '<span class="badge bg-blue-lt mb-1"><i class="ti ti-calendar-time me-1"></i>' . $scheduledDateStr . '</span>' . $rescheduleBadge . '<br><small class="text-muted">' . $slotLabel . '</small>';

            $store = $isSeller ? $row->items->first()?->orderItem?->store : $targetOrder->items->first()?->store;
            $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');
            $prepStartTime = $this->resolveScheduledPreparationStartTime($targetOrder, $store);

            if ($prepStartTime) {
                $now = Carbon::now($storeTz);
                if ($now->lt($prepStartTime)) {
                    $preparationStartsAtHtml = '<span class="badge bg-warning-lt" title="' . e(__('labels.prep_unlocks_at', ['time' => $prepStartTime->format('d M, h:i A')])) . '"><i class="ti ti-lock me-1"></i>' . $prepStartTime->format('d M, h:i A') . '</span>';
                } else {
                    $preparationStartsAtHtml = '<span class="badge bg-success-lt"><i class="ti ti-lock-open me-1"></i>' . $prepStartTime->format('d M, h:i A') . '</span>';
                }
            }
        }

        $acceptanceDeadlineHtml = $this->renderAcceptanceDeadlineForOrder($targetOrder, $isSeller ? $row : null);
        $statusValue = $statusValue->value ?? $statusValue;
        $statusStr = strtolower((string) $statusValue);
        $requiresAction = in_array($statusStr, ['pending', 'awaiting_store_response']);
        $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="' . e(__('labels.action_required') ?? 'Action Required') . '"></span>' : '';
        $rawId = $id ?? $row->id;
        $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">' . $actionDot . '<span>' . e($rawId) . '</span></div>' : $rawId;

        return [
            'expand' => $expand,
            'id' => $formattedId,
            'order_id' => '<span class="fw-medium text-primary">#' . e($publicId) . '</span>' . $posBadge . $pickupBadge . ($targetOrder && ($targetOrder->scheduled_date || $targetOrder->delivery_time_slot_id) ? ' <span class="badge bg-indigo-lt ms-1"><i class="ti ti-calendar-time me-1"></i>' . e(__('labels.scheduled') ?? 'Scheduled') . '</span>' : '') . $rescheduleBadge,
            'order_date' => $row->created_at?->format('Y-m-d H:i') ?? '—',
            'scheduled_for' => $scheduledForHtml,
            'preparation_starts_at' => $preparationStartsAtHtml,
            'buyer' => '<span class="text-capitalize">' . e($buyer) . '</span>',
            'payment_method' => '<span class="badge bg-blue-lt text-uppercase">' . e($paymentMethod) . '</span>',
            'items_count' => $itemsBadge,
            'total' => '<span class="fw-semibold">' . e($this->currencyService->format($total)) . '</span>',
            'status' => view('partials.order-status', ['status' => $statusValue ?? ''])->render() . $acceptanceDeadlineHtml,
            'actions' => $actionsHtml,
        ];
    }

    private function renderAcceptanceDeadlineForOrder(?Order $order, ?SellerOrder $sellerOrder = null): string
    {
        if (! $order) {
            return '';
        }

        $items = $sellerOrder
            ? $sellerOrder->items->map(fn($item) => $item->orderItem)->filter()
            : $order->items;

        $deadline = $items
            ->map(fn($item) => $this->orderService->acceptanceDeadlineForOrderItem($item, $order))
            ->filter()
            ->sortBy(fn($deadline) => $deadline->getTimestamp())
            ->first();

        return $deadline
            ? view('partials.order-acceptance-deadline', ['deadline' => $deadline])->render()
            : '';
    }

    private function renderAcceptanceDeadlineForItem(?OrderItem $item, ?Order $order = null): string
    {
        if (! $item) {
            return '';
        }

        $deadline = $this->orderService->acceptanceDeadlineForOrderItem($item, $order);

        return $deadline
            ? view('partials.order-acceptance-deadline', ['deadline' => $deadline])->render()
            : '';
    }

    private function resolveOrderDisplayStatus($order): ?string
    {
        if (! $order) {
            return null;
        }

        if ($order instanceof SellerOrder) {
            $itemStatuses = $order->relationLoaded('items')
                ? $order->items->map(fn($item) => $item->orderItem?->status->value ?? $item->orderItem?->status ?? null)->filter()->all()
                : OrderItem::whereIn('id', $order->items()->select('order_item_id'))->pluck('status')->all();
        } else {
            $itemStatuses = $order->relationLoaded('items')
                ? $order->items->pluck('status')->all()
                : $order->items()->pluck('status')->all();
        }

        if ($order instanceof SellerOrder && ! empty($itemStatuses)) {
            $terminalStatuses = [
                OrderItemStatusEnum::CANCELLED(),
                OrderItemStatusEnum::REJECTED(),
                OrderItemStatusEnum::RETURNING_TO_STORE(),
                OrderItemStatusEnum::DELIVERY_FAILED(),
                OrderItemStatusEnum::FAILED(),
            ];
            $activeItems = array_filter($itemStatuses, fn($s) => ! in_array($s, $terminalStatuses, true));
            if (empty($activeItems)) {
                return app(OrderService::class)->determineSellerOrderStatus($itemStatuses);
            }

            return $order->order->status->value ?? $order->order?->status;
        }

        $status = $order->status->value ?? $order->status;

        if (! empty($itemStatuses)) {
            $terminalStatuses = [
                OrderItemStatusEnum::CANCELLED(),
                OrderItemStatusEnum::REJECTED(),
                OrderItemStatusEnum::RETURNING_TO_STORE(),
                OrderItemStatusEnum::DELIVERY_FAILED(),
                OrderItemStatusEnum::FAILED(),
            ];
            $activeItems = array_filter($itemStatuses, fn($s) => ! in_array($s, $terminalStatuses, true));
            if (empty($activeItems)) {
                $failedCount = count(array_filter($itemStatuses, fn($s) => $s === OrderItemStatusEnum::FAILED()));
                if ($failedCount === count($itemStatuses)) {
                    return OrderStatusEnum::FAILED();
                }
                $rejectedCount = count(array_filter($itemStatuses, fn($s) => $s === OrderItemStatusEnum::REJECTED()));
                if ($rejectedCount === count($itemStatuses)) {
                    return OrderStatusEnum::REJECTED_BY_SELLER();
                }

                return OrderStatusEnum::CANCELLED();
            }
        }

        if ($status !== OrderStatusEnum::PARTIALLY_ACCEPTED()) {
            return $status;
        }

        if (
            in_array(OrderItemStatusEnum::AWAITING_STORE_RESPONSE(), $itemStatuses, true)
            && in_array(OrderItemStatusEnum::REJECTED(), $itemStatuses, true)
        ) {
            return OrderStatusEnum::PARTIALLY_REJECTED();
        }

        return $status;
    }

    /**
     * Returns the items belonging to an order, scoped per-panel, for the
     * inline expand row in the orders-grouped listing tab.
     */
    public function getOrderItemsForExpand(int $id): JsonResponse
    {
        try {
            $isSeller = $this->getPanel() === 'seller';

            if ($isSeller) {
                $seller = auth()->user()?->seller();
                if (! $seller) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
                }
                $sellerOrder = SellerOrder::with([
                    'order',
                    'items.orderItem.store',
                    'items.orderItem.addons.addonGroup',
                    'items.orderItem.addons.addonItem',
                    'items.product',
                    'items.variant',
                ])
                    ->where('id', $id)
                    ->where('seller_id', $seller->id)
                    ->firstOrFail();

                $this->authorize('viewAny', $sellerOrder);

                $items = $sellerOrder->items->map(fn($item) => $this->mapExpandItem($item->orderItem, $item->product, $item->variant));
                $isScheduledRoute = str_contains(request()->route()?->getName() ?? '', 'scheduled_orders');
                $detailRoute = route($isScheduledRoute ? 'seller.scheduled_orders.show' : 'seller.orders.show', $sellerOrder->id);
                $orderUuid = $sellerOrder->order?->uuid;
                $isReschedulePending = $sellerOrder->order?->reschedule_status === 'needs_reschedule';
            } else {
                $order = Order::with([
                    'items.product',
                    'items.variant',
                    'items.store',
                    'items.addons.addonGroup',
                    'items.addons.addonItem',
                    'sellerOrders',
                ])->findOrFail($id);

                $this->authorize('viewAny', $order);

                $items = $order->items->map(fn($item) => $this->mapExpandItem($item, $item->product, $item->variant));
                $isScheduledRoute = str_contains(request()->route()?->getName() ?? '', 'scheduled_orders');
                $detailRoute = route($isScheduledRoute ? 'admin.scheduled_orders.show' : 'admin.orders.show', $order->id);
                $orderUuid = $order->uuid;
                $isReschedulePending = $order->reschedule_status === 'needs_reschedule';
            }

            $isPickup = false;
            if ($isSeller) {
                $isPickup = ($sellerOrder->delivery_type?->value ?? $sellerOrder->delivery_type) === 'pickup';
            } else {
                $firstSellerOrder = $order->sellerOrders->first();
                $isPickup = $firstSellerOrder && (($firstSellerOrder->delivery_type?->value ?? $firstSellerOrder->delivery_type) === 'pickup');
            }

            $html = view('partials.order-items-expand', [
                'items' => $items,
                'panel' => $this->getPanel(),
                'editPermission' => $isSeller && $this->editPermission,
                'detailRoute' => $detailRoute,
                'orderUuid' => $orderUuid,
                'currencyService' => $this->currencyService,
                'isPickup' => $isPickup,
                'isReschedulePending' => $isReschedulePending,
            ])->render();

            return ApiResponseType::sendJsonResponse(true, 'labels.success', ['html' => $html]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    private function mapExpandItem($orderItem, $product, $variant): array
    {
        $image = ! empty($variant?->image) ? $variant->image : ($product?->main_image ?? null);
        $addons = $orderItem?->addons ?? collect();

        $attachments = [];
        if ($orderItem) {
            foreach ($orderItem->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS()) as $media) {
                $attachments[] = $media->getUrl();
            }
        }

        return [
            'id' => $orderItem?->id,
            'image' => $image,
            'product_title' => $product?->getTranslation('title', 'en', false) ?? '—',
            'variant_title' => $variant?->title ?? null,
            'sku' => $orderItem?->sku,
            'quantity' => $orderItem?->quantity ?? 0,
            'subtotal' => $orderItem?->subtotal ?? 0,
            'status' => $orderItem?->status,
            'store' => $orderItem?->store?->name,
            'addons' => $addons,
            'attachments' => $attachments,
        ];
    }

    /**
     * Get orders datatable data
     */
    public function getOrders(Request $request): JsonResponse
    {
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';
        $status = $request->get('status');
        $statusGroup = $this->normalizeStatusGroup($request->get('status_group'));
        $paymentType = $request->get('payment_type');
        $dateRange = $request->get('range');
        $orderType = $request->get('order_type');
        $deliveryZoneId = $request->get('delivery_zone_id');
        $storeId = $request->get('store_id');

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';

        $columns = ['id', 'order_id', 'price', 'status', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = SellerOrderItem::with([
            'sellerOrder.order',
            'orderItem',
            'orderItem.store',
            'orderItem.addons.addonGroup',
            'orderItem.addons.addonItem',
            'variant',
            'product',
        ])
            ->whereHas('product', function ($q) {
                $q->whereNotNull('id');
            });

        if ($orderType === 'pos') {
            $query->whereHas('sellerOrder.order', fn($q) => $q->where('created_by', OrderCreatedByEnum::SELLER()));
        } elseif ($orderType === 'regular') {
            $query->whereHas('sellerOrder.order', fn($q) => $q->where(fn($qq) => $qq->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER())))
                ->whereHas('sellerOrder', fn($q) => $q->where('delivery_type', '!=', OrderDeliveryTypeEnum::PICKUP()));
        } elseif ($orderType === 'pickup') {
            $query->whereHas('sellerOrder', fn($q) => $q->where('delivery_type', OrderDeliveryTypeEnum::PICKUP()));
        }

        if ($deliveryZoneId !== null && $deliveryZoneId !== '') {
            $query->whereHas('sellerOrder.order', fn($q) => $q->where('delivery_zone_id', (int) $deliveryZoneId));
        }

        if ($storeId !== null && $storeId !== '') {
            $query->whereHas('orderItem', fn($q) => $q->where('store_id', (int) $storeId));
        }

        $isScheduled = $request->boolean('is_scheduled') || str_contains($request->route()?->getName() ?? '', 'scheduled_orders');
        if ($isScheduled) {
            $query->whereHas('sellerOrder.order', fn($q) => $q->where(fn($qq) => $qq->whereNotNull('scheduled_date')->orWhereNotNull('delivery_time_slot_id'))->whereDate('scheduled_date', '>', Carbon::today()));
        } else {
            $query->whereHas('sellerOrder.order', fn($q) => $q->where(fn($qq) => $qq->whereNull('scheduled_date')->whereNull('delivery_time_slot_id'))->orWhere(fn($qq) => $qq->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<=', Carbon::today())));
        }

        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $seller = $user?->seller();

            if (! $seller) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_not_found'),
                    data: []
                );
            }

            $query->whereHas('sellerOrder', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            });
            $query->whereHas('orderItem', function ($q) {
                $q->where('status', '!=', OrderItemStatusEnum::PENDING());
            });
        }
        $totalRecords = $query->count();

        // A card group takes precedence over an exact status filter.
        if ($statusGroup !== null) {
            $this->applyInsightStatusGroup($query, 'orderItem', $statusGroup);
        } elseif ($status !== null && $status !== '') {
            $query->whereHas('orderItem', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }
        // Filter by status if provided
        if ($paymentType !== null && $paymentType !== '') {
            $query->whereHas('sellerOrder', function ($q) use ($paymentType) {
                $q->whereHas('order', function ($q) use ($paymentType) {
                    $q->where('payment_method', $paymentType);
                });
            });
        }

        // Filter by date range if provided
        if ($dateRange !== null && $dateRange !== '') {

            $fromDate = $this->getDateRange($dateRange);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }
        }

        // Search functionality
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('seller_order_id', 'like', "%$searchValue%")
                    ->orWhereHas('sellerOrder', function ($orderQuery) use ($searchValue) {
                        $orderQuery->where('total_price', 'like', "%$searchValue%")
                            ->orWhereHas('order', function ($orderQuery) use ($searchValue) {
                                $orderQuery->where('shipping_name', 'like', "%$searchValue%");
                                $orderQuery->orWhere('id', 'like', "%$searchValue%");
                            });
                    })
                    ->orWhereHas('orderItem', function ($orderItemQuery) use ($searchValue) {
                        $orderItemQuery->where('status', 'like', "%$searchValue%")
                            ->orWhere('id', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($searchValue) {
                        $productQuery->whereEnglishTextLike($searchValue);
                    })
                    ->orWhereHas('variant', function ($variantQuery) use ($searchValue) {
                        $variantQuery->where('title', 'like', "%$searchValue%");
                    });
            });
        }
        $filteredRecords = $query->count();

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($sellerOrderItem) {
                return $this->getOrderReturnData($sellerOrderItem);
            });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    private function getOrderReturnData($sellerOrderItem): array
    {
        $variantTitle = $sellerOrderItem->product->type === ProductTypeEnum::SIMPLE() ? '' : ($sellerOrderItem->variant->title ?? '');
        $storeName = $sellerOrderItem->orderItem->store ? $sellerOrderItem->orderItem->store->name : 'N/A';
        $orderNote = ! empty($sellerOrderItem->sellerOrder->order->order_note) ? "<textarea class='form-control' rows='1' readonly disabled>order note:- {$sellerOrderItem->sellerOrder->order->order_note}</textarea>" : null;
        $addonsMarkup = $this->renderAddonsSnippet($sellerOrderItem->orderItem);
        $attachmentsMarkup = $this->renderAttachmentsBadge($sellerOrderItem->orderItem);
        $status = $sellerOrderItem->orderItem->status->value ?? $sellerOrderItem->orderItem->status;
        $orderCreatedBy = $sellerOrderItem->sellerOrder->order->created_by ?? null;
        $isPos = $orderCreatedBy instanceof OrderCreatedByEnum
            ? $orderCreatedBy === OrderCreatedByEnum::SELLER
            : ($orderCreatedBy === OrderCreatedByEnum::SELLER());
        $posBadge = $isPos ? ' <span class="badge bg-purple-lt">' . e(__('labels.pos')) . '</span>' : '';

        $isScheduledRoute = request()->boolean('is_scheduled') || str_contains(request()->route()?->getName() ?? '', 'scheduled_orders');
        $isPrepLocked = false;
        $prepStartTimeStr = '';
        $scheduledForHtml = '—';
        $preparationStartsAtHtml = '—';
        $order = $sellerOrderItem->sellerOrder->order ?? null;
        $isReschedulePending = $order?->reschedule_status === 'needs_reschedule';
        $rescheduleBadge = $isReschedulePending
            ? ' <span class="badge bg-warning-lt text-warning ms-1"><i class="ti ti-calendar-exclamation me-1"></i>' . e(__('labels.needs_reschedule')) . '</span>'
            : '';
        if ($order && ($order->scheduled_date || $order->delivery_time_slot_id)) {
            $scheduledDateStr = $order->scheduled_date?->format('d M, Y') ?? '—';
            $slotLabel = $this->resolveScheduledSlotLabel($order);
            $scheduledForHtml = '<span class="badge bg-blue-lt mb-1"><i class="ti ti-calendar-time me-1"></i>' . $scheduledDateStr . '</span>' . $rescheduleBadge . '<br><small class="text-muted">' . $slotLabel . '</small>';

            $store = $sellerOrderItem->orderItem->store ?? null;
            $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');
            $prepStartTime = $this->resolveScheduledPreparationStartTime($order, $store);

            if ($prepStartTime) {
                $now = Carbon::now($storeTz);
                if ($now->lt($prepStartTime)) {
                    $isPrepLocked = true;
                    $prepStartTimeStr = $prepStartTime->format('d M, h:i A');
                    $preparationStartsAtHtml = '<span class="badge bg-warning-lt" title="' . e(__('labels.prep_unlocks_at', ['time' => $prepStartTimeStr])) . '"><i class="ti ti-lock me-1"></i>' . $prepStartTimeStr . '</span>';
                } else {
                    $preparationStartsAtHtml = '<span class="badge bg-success-lt"><i class="ti ti-lock-open me-1"></i>' . $prepStartTime->format('d M, h:i A') . '</span>';
                }
            }
        }

        return [
            'id' => $sellerOrderItem->order_item_id,
            'order_date' => "<div><p class='m-0 fw-medium'>" . $sellerOrderItem->created_at->diffForHumans() . "</p>
                        {$sellerOrderItem->created_at->format('Y-m-d H:i:s')}
                        </div>",
            'scheduled_for' => $scheduledForHtml,
            'preparation_starts_at' => $preparationStartsAtHtml,
            'order_details' => "<div class='d-flex justify-content-start align-items-center'><div class='pe-2'>" .
                view('partials.image', [
                    'image' => ! empty($sellerOrderItem->variant->image) ? $sellerOrderItem->variant->image : $sellerOrderItem->product->main_image,
                ])->render() .
                "</div><div>
                        <p class='m-0 fw-medium text-primary'>" . __('labels.order_id') . ": {$sellerOrderItem->sellerOrder->order_id} {$posBadge}{$rescheduleBadge}</p>
                        <p class='m-0'>" . __('labels.buyer_name') . ': ' . e($sellerOrderItem->sellerOrder->order->shipping_name) . "</p>
                        <p class='m-0'>" . __('labels.payment_method') . ': ' . e($sellerOrderItem->sellerOrder->order->payment_method) . "</p>
                        <p class='m-0'>" . __('labels.is_rush_order') . ': ' . ($sellerOrderItem->sellerOrder->order->is_rush_order ? 'Yes' : 'No') . "</p>
                        <p class='m-0'>" . __('labels.order_status') . ': ' . Str::ucfirst(Str::replace('_', ' ', $status)) . '</p>'
                . $orderNote .
                '</div></div>',
            'product_details' => "<div>
                        <a href='" . route($this->getPanel() . '.products.show', ['id' => $sellerOrderItem->product->id]) . "' class='m-0 fw-medium text-primary'>" . __('labels.product_name') . ': '.e($sellerOrderItem->orderItem?->title ?? '') . "</a>
                        <p class='m-0 fw-medium text-primary'>" . __('labels.variant_name') . ": $variantTitle</p>
                        <p class='m-0 fw-medium text-capitalize'>" . __('labels.store_name') . ": $storeName</p>
                        <p class='m-0'>" . __('labels.sku') . ": {$sellerOrderItem->orderItem->sku}</p>
                        <p class='m-0 fw-medium'>" . __('labels.quantity') . ": {$sellerOrderItem->orderItem->quantity}</p>
                        <p class='m-0 fw-medium'>" . __('labels.item_sub_total') . ': ' . $this->currencyService->format($sellerOrderItem->orderItem->subtotal) . "</p>
                        $addonsMarkup
                        $attachmentsMarkup
                        </div>",
            'status' => view('partials.order-status', [
                'status' => $status,
            ])->render() . $this->renderAcceptanceDeadlineForItem($sellerOrderItem->orderItem, $order),
            'actions' => view('partials.order-actions', [
                'panel' => $this->getPanel(),
                'uuid' => $sellerOrderItem->sellerOrder->order->uuid,
                'id' => $sellerOrderItem->orderItem->id,
                'hierarchy' => OrderItem::getStatusHierarchy(),
                'route' => route($isScheduledRoute ? $this->panelView('scheduled_orders.show') : $this->panelView('orders.show'), $this->getPanel() === 'seller' ? $sellerOrderItem->sellerOrder->id : $sellerOrderItem->sellerOrder->order_id),
                'title' => __('labels.edit_order') . $sellerOrderItem->sellerOrder->id,
                'status' => $sellerOrderItem->orderItem->status,
                'editPermission' => $this->getPanel() === 'admin' ? false : $this->editPermission,
                'isPickup' => ($sellerOrderItem->sellerOrder->delivery_type?->value ?? $sellerOrderItem->sellerOrder->delivery_type) === 'pickup',
                'isPrepLocked' => $isPrepLocked,
                'prepStartTimeStr' => $prepStartTimeStr,
                'isReschedulePending' => $isReschedulePending,
            ])->render(),
        ];
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
     * Display the specified order.
     *
     * @param  int  $id
     */
    public function show($id): View
    {
        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $seller = $user?->seller();

            if (! $seller) {
                abort(404, __('labels.seller_not_found'));
            }
            $order = SellerOrder::where('id', $id)
                ->with([
                    'order',
                    'items.product',
                    'items.variant',
                    'items.orderItem',
                    'items.orderItem.addons.addonGroup',
                    'items.orderItem.addons.addonItem',
                    'order.items.store',
                    'order.deliveryBoy.user',
                    'order.deliveryBoyAssignments',
                    'order.deliveryZone',
                ])
                ->where('seller_id', $seller->id)
                ->firstOrFail();
        } else {
            $order = Order::with([
                'items',
                'items.product',
                'items.variant',
                'items.store',
                // Admin Stores card needs the seller + user behind each store.
                'items.store.seller.user',
                'items.addons.addonGroup',
                'items.addons.addonItem',
                'promoLine',
                // Admin detail page renders rider + zone in the Delivery card,
                // plus persisted assignment earnings for the rider chip & the
                // Force Cancel "pay rider" preview.
                'deliveryBoy.user',
                'deliveryZone',
                'deliveryBoyAssignments',
                'sellerOrders',
            ])
                ->findOrFail($id);

            // Pre-compute the delivery route so getDeliveryBoyEarningsAttribute()
            // can return a live breakdown even when no DeliveryBoyAssignment row
            // exists yet (admin viewing an order before any rider has accepted).
            if ($order->shipping_latitude && $order->shipping_longitude) {
                try {
                    $storeIds = $order->items->pluck('store_id')->unique()->filter()->values()->toArray();
                    if (! empty($storeIds)) {
                        $order->delivery_route = DeliveryZoneService::calculateDeliveryRoute(
                            (float) $order->shipping_latitude,
                            (float) $order->shipping_longitude,
                            $storeIds,
                            $order
                        );
                    }
                } catch (\Throwable $e) {
                    // Route is presentational only — never fail the page render.
                    Log::warning('Admin show delivery_route calc failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        $this->authorize('viewAny', $order);
        // Transform the order data using the resource
        $orderData = new OrderResource($order);

        $orderId = $order instanceof Order ? $order->id : ($order->order_id ?? null);
        $auditLogs = collect();
        $itemStatusReasons = [];
        if ($orderId) {
            $reasonLogs = OrderAuditLog::query()
                ->where('order_id', $orderId)
                ->whereNotNull('order_item_id')
                ->whereNotNull('reason')
                ->whereIn('action', [
                    'admin_status_update',
                    'force_cancel',
                    'force_status',
                    'auto_rejected_unaccepted_order',
                ])
                ->orderByDesc('created_at')
                ->get();

            foreach ($reasonLogs as $log) {
                $itemStatusReasons[(int) $log->order_item_id] ??= $log->reason;
            }

            if ($this->getPanel() === 'admin') {
                $auditLogs = OrderAuditLog::with('admin')
                    ->where('order_id', $orderId)
                    ->orderByDesc('created_at')
                    ->get();
            }
        }

        // Build valid-next-status map for each item so the inline
        // status dropdown only shows transitions the hierarchy allows.
        $itemTransitions = [];
        if ($this->getPanel() === 'admin' && $this->editPermission) {
            $transitionMap = [
                OrderItemStatusEnum::AWAITING_STORE_RESPONSE() => [
                    ['value' => 'accept', 'label' => __('labels.accept'), 'acting_as' => 'seller'],
                    ['value' => 'reject', 'label' => __('labels.reject'), 'acting_as' => 'seller'],
                ],
                OrderItemStatusEnum::ACCEPTED() => [
                    ['value' => 'preparing', 'label' => __('labels.preparing'), 'acting_as' => 'seller'],
                ],
                OrderItemStatusEnum::PREPARING() => [
                    ['value' => 'collected', 'label' => __('labels.collected'), 'acting_as' => 'rider'],
                ],
                OrderItemStatusEnum::COLLECTED() => [
                    ['value' => 'delivered', 'label' => __('labels.delivered'), 'acting_as' => 'rider'],
                    ['value' => 'delivery_failed', 'label' => __('labels.delivery_failed'), 'acting_as' => 'rider'],
                ],
                OrderItemStatusEnum::RETURNING_TO_STORE() => [
                    ['value' => 'confirm_return', 'label' => __('labels.confirm_return'), 'acting_as' => 'seller'],
                ],
            ];

            $rawOrder = $order instanceof Order ? $order : ($order->order ?? null);
            $items = $rawOrder ? $rawOrder->items : collect();
            $firstSellerOrder = $rawOrder ? $rawOrder->sellerOrders->first() : null;
            $isPickupOrder = $firstSellerOrder && (($firstSellerOrder->delivery_type?->value ?? $firstSellerOrder->delivery_type) === 'pickup');

            foreach ($items as $item) {
                $transitions = $transitionMap[$item->status] ?? [];
                if ($isPickupOrder) {
                    $transitions = array_values(array_filter($transitions, function ($t) {
                        return $t['acting_as'] !== 'rider' && $t['value'] !== 'preparing';
                    }));
                }
                $itemTransitions[$item->id] = $transitions;
            }
        }

        // Seller panel sees only seller-side transitions; rider/admin-only
        // actions are filtered out so the bulk modal cannot surface them.
        if ($this->getPanel() === 'seller') {
            $sellerTransitionMap = [
                OrderItemStatusEnum::AWAITING_STORE_RESPONSE() => [
                    ['value' => 'accept', 'label' => __('labels.accept'), 'acting_as' => 'seller'],
                    ['value' => 'reject', 'label' => __('labels.reject'), 'acting_as' => 'seller'],
                ],
                OrderItemStatusEnum::ACCEPTED() => [
                    ['value' => 'preparing', 'label' => __('labels.preparing'), 'acting_as' => 'seller'],
                ],
                OrderItemStatusEnum::RETURNING_TO_STORE() => [
                    ['value' => 'confirm_return', 'label' => __('labels.confirm_return'), 'acting_as' => 'seller'],
                ],
            ];

            // For the seller panel the show() route returns a SellerOrder; its
            // items collection is already seller-scoped, no extra filtering needed.
            $sellerItems = $order->items ?? collect();
            $isPickupOrder = ($order->delivery_type?->value ?? $order->delivery_type) === 'pickup';
            $isReschedulePending = $order->order?->reschedule_status === 'needs_reschedule';
            foreach ($sellerItems as $sellerItem) {
                $oi = $sellerItem->orderItem ?? null;
                if (! $oi) {
                    continue;
                }
                $transitions = $isReschedulePending ? [] : ($sellerTransitionMap[$oi->status] ?? []);
                if ($isPickupOrder) {
                    $transitions = array_values(array_filter($transitions, function ($t) {
                        return $t['value'] !== 'preparing';
                    }));
                }
                $itemTransitions[$oi->id] = $transitions;
            }
        }

        $hasEligiblePickupReturnItems = false;
        $refunds = collect();
        if ($this->getPanel() === 'seller') {
            $isPickupOrder = ($order->delivery_type?->value ?? $order->delivery_type) === 'pickup';
            if ($isPickupOrder) {
                foreach ($order->items as $sellerItem) {
                    $oi = $sellerItem->orderItem ?? null;
                    if ($oi && $oi->isEligibleForPickupReturn()) {
                        $hasEligiblePickupReturnItems = true;
                        break;
                    }
                }

                $refunds = PosRefund::where('order_id', $order->order_id)
                    ->where('store_id', $order->items->first()?->orderItem?->store_id)
                    ->with(['lines.orderItem', 'refundedBy'])
                    ->orderByDesc('id')
                    ->get();
            }
        }

        return view($this->panelView('orders.show'), [
            'order' => $orderData->toArray(request()),
            'editPermission' => $this->editPermission,
            'auditLogs' => $auditLogs,
            'itemStatusReasons' => $itemStatusReasons,
            'itemTransitions' => $itemTransitions,
            'acceptanceDeadlines' => $this->buildAcceptanceDeadlineMap($order),
            'hasEligiblePickupReturnItems' => $hasEligiblePickupReturnItems,
            'refunds' => $refunds,
        ]);
    }

    /**
     * @return array<int, \Carbon\Carbon>
     */
    private function buildAcceptanceDeadlineMap($order): array
    {
        $map = [];
        $parentOrder = $order instanceof SellerOrder ? $order->order : $order;
        $items = $order instanceof SellerOrder
            ? $order->items->map(fn($item) => $item->orderItem)->filter()
            : ($order->items ?? collect());

        foreach ($items as $item) {
            $deadline = $this->orderService->acceptanceDeadlineForOrderItem($item, $parentOrder);
            if ($deadline) {
                $map[$item->id] = $deadline;
            }
        }

        return $map;
    }

    /**
     * Update the order status.
     */
    public function updateStatus(int $id, string $status): JsonResponse
    {
        try {
            // Admin path — Phase 3. Previously this method crashed for admins
            // because it called auth()->user()->seller() (BUG-6).
            if ($this->getPanel() === 'admin') {
                $orderItem = OrderItem::find($id);
                if (! $orderItem) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
                }
                $this->authorize('forceUpdateStatus', $orderItem);

                $result = $this->orderService->updateOrderStatusByAdmin(
                    orderItemId: $id,
                    newStatus: $status,
                    adminId: (int) auth()->id(),
                    reason: request('reason', __('labels.admin_force_status_default_reason')),
                );

                return ApiResponseType::sendJsonResponse(
                    success: $result['success'],
                    message: $result['message'],
                    data: $result['data'] ?? [],
                );
            }

            // Seller path (unchanged).
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
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_item_not_found'),
                    data: []
                );
            }

            $this->authorize('updateStatus', $orderItem);

            $result = $this->orderService->updateOrderStatusBySeller($id, $status, $seller->id);
            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $result['message'],
                    data: $result['data'],
                );
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.unauthorized_action'),
                data: []
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.order_status_update_failed'),
                data: []
            );
        }
    }

    /**
     * Cancel an item the seller had already accepted. Pre-collect only —
     * post-collect cancellations go through the rider/admin return-to-store
     * flow.
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

            $orderItem = SellerOrderItem::where('order_item_id', $id)
                ->whereHas('sellerOrder', function ($q) use ($seller) {
                    $q->where('seller_id', $seller->id);
                })
                ->first();
            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }

            $this->authorize('updateStatus', $orderItem);
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
        } catch (\Exception $e) {
            Log::error('Seller cancel-item endpoint failed', [
                'order_item_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Seller confirms physical receipt of items returning to the store.
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

            $orderItem = SellerOrderItem::where('order_item_id', $id)
                ->whereHas('sellerOrder', function ($q) use ($seller) {
                    $q->where('seller_id', $seller->id);
                })
                ->first();
            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }

            $this->authorize('updateStatus', $orderItem);

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
        } catch (\Exception $e) {
            Log::error('Seller confirm-return endpoint failed', [
                'order_item_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    // =====================================================================
    // Phase 3 — admin override endpoints. Admin-only. Each writes an audit
    // log row via the service. Refunds + restock fire via the existing pipeline
    // where applicable.
    // =====================================================================

    public function adminForceCancel(AdminForceCancelRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $orderItem = OrderItem::find($id);
            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }
            $this->authorize('forceCancel', $orderItem);

            $result = $this->orderService->cancelOrderItemByAdmin(
                orderItemId: $id,
                adminId: (int) auth()->id(),
                reason: $request->validated('reason'),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin force-cancel endpoint failed', ['order_item_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    public function adminForceRefund(AdminForceRefundRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $order = Order::find($id);
            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }
            $this->authorize('forceRefund', $order);

            $result = $this->orderService->forceRefundByAdmin(
                orderId: $id,
                amount: (float) $request->validated('amount'),
                adminId: (int) auth()->id(),
                reason: $request->validated('reason'),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin force-refund endpoint failed', ['order_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    public function adminReassignRider(AdminReassignRiderRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $order = Order::find($id);
            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }
            $this->authorize('reassignRider', $order);

            $result = $this->orderService->reassignRiderByAdmin(
                orderId: $id,
                newDeliveryBoyId: $request->validated('delivery_boy_id'),
                adminId: (int) auth()->id(),
                reason: $request->validated('reason'),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin reassign-rider endpoint failed', ['order_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    public function adminAddNote(AdminAddOrderNoteRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $order = Order::find($id);
            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }
            $this->authorize('addNote', $order);

            $result = $this->orderService->addOrderNoteByAdmin(
                orderId: $id,
                adminId: (int) auth()->id(),
                note: $request->validated('note'),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin add-note endpoint failed', ['order_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Settle (approve or reject) the rider's earnings on a CANCELLED_BY_ADMIN
     * assignment. The Delivery card on admin order detail surfaces the
     * decision buttons when the assignment is in CANCELLED_BY_ADMIN + PENDING
     * state. Required remark is captured in the audit log.
     */
    public function adminSettleRiderEarnings(SettleRiderEarningsRequest $request, int $assignmentId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            // Reuse the existing forceRefund policy ability — same admin
            // ORDER_EDIT permission gates every monetary admin override.
            $assignment = DeliveryBoyAssignment::find($assignmentId);
            if (! $assignment) {
                return ApiResponseType::sendJsonResponse(false, __('labels.assignment_not_found'), []);
            }
            $order = Order::find($assignment->order_id);
            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }
            $this->authorize('forceRefund', $order);

            $result = $this->orderService->settleRiderEarnings(
                assignmentId: $assignmentId,
                decision: $request->validated('decision'),
                reason: $request->validated('reason'),
                adminId: (int) auth()->id(),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin settle-rider-earnings endpoint failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Update an order item status on behalf of the seller or rider.
     */
    public function adminUpdateItemStatus(Request $request, int $itemId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $orderItem = OrderItem::find($itemId);
            if (! $orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }

            $this->authorize('forceUpdateStatus', $orderItem);

            $validated = $request->validate([
                'status' => 'required|string|in:accept,reject,preparing,collected,delivered,delivery_failed,confirm_return',
                'remark' => 'nullable|string|max:1000',
                'delivery_fail_reason' => 'nullable|string|in:customer_unavailable,customer_refused,wrong_address,unsafe_location',
            ]);

            $result = $this->orderService->updateOrderItemStatusByAdmin(
                orderItemId: $itemId,
                targetStatus: $validated['status'],
                adminId: (int) auth()->id(),
                remark: $validated['remark'] ?? null,
                deliveryFailReason: $validated['delivery_fail_reason'] ?? null,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin update-item-status endpoint failed', [
                'order_item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Bulk variant of adminUpdateItemStatus — admin applies the same on-behalf
     * status transition to many items in one request. Items that fail (invalid
     * transition, wrong role context, etc.) are reported per-item without
     * blocking the rest.
     *
     * @param  int  $id  Order ID — scopes the items.
     */
    public function adminBulkUpdateItemStatus(AdminBulkUpdateItemStatusRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $order = Order::find($id);
            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            $validated = $request->validated();
            $rawIds = collect($validated['item_ids'])->map(fn($v) => (int) $v)->unique()->values();

            // Scope check — every submitted item must belong to this order so
            // an admin can't sneak cross-order writes through one request.
            $belonging = OrderItem::whereIn('id', $rawIds)
                ->where('order_id', $order->id)
                ->pluck('id')
                ->all();
            if (count($belonging) !== $rawIds->count()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.bulk_update_items_not_in_order'),
                    data: [],
                );
            }

            // Authorize against the first item — the policy gate is "admin can
            // edit orders" so all items inherit the same decision.
            $first = OrderItem::find($rawIds->first());
            if ($first) {
                $this->authorize('forceUpdateStatus', $first);
            }

            $result = $this->orderService->bulkUpdateOrderItemStatusByAdmin(
                orderItemIds: $belonging,
                targetStatus: $validated['status'],
                adminId: (int) auth()->id(),
                remark: $validated['remark'] ?? null,
                deliveryFailReason: $validated['delivery_fail_reason'] ?? null,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin bulk update-item-status endpoint failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Seller variant — apply the same status transition to many items in one
     * request. Scoped to items the authenticated seller owns under the given
     * SellerOrder id. Returns the same aggregated shape as the admin bulk
     * endpoint so both panels can share the modal flow.
     *
     * @param  int  $id  SellerOrder id (matches the show route).
     */
    public function sellerBulkUpdateItemStatus(SellerBulkUpdateItemStatusRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'seller') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $sellerOrder = SellerOrder::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();
            if (! $sellerOrder) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            $validated = $request->validated();
            $rawIds = collect($validated['item_ids'])->map(fn($v) => (int) $v)->unique()->values();

            // Item-level scoping — every submitted id must be a SellerOrderItem
            // under this SellerOrder. Stops cross-seller writes.
            $belonging = SellerOrderItem::where('seller_order_id', $sellerOrder->id)
                ->whereIn('order_item_id', $rawIds)
                ->pluck('order_item_id')
                ->all();
            if (count($belonging) !== $rawIds->count()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.bulk_update_items_not_in_order'),
                    data: [],
                );
            }

            $result = $this->orderService->bulkUpdateOrderItemStatusBySeller(
                orderItemIds: $belonging,
                targetStatus: $validated['status'],
                sellerId: $seller->id,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (\Exception $e) {
            Log::error('Seller bulk update-item-status endpoint failed', [
                'seller_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Mark order payment as received — moves PENDING → AWAITING_STORE_RESPONSE.
     *
     * @param  int  $id  Order ID
     */
    public function adminMarkPaymentReceived(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $order = Order::find($id);
            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            $this->authorize('forceUpdateStatus', $order);

            $validated = $request->validate([
                'remark' => 'nullable|string|max:1000',
            ]);

            $result = $this->orderService->markPaymentReceivedByAdmin(
                orderId: $id,
                adminId: (int) auth()->id(),
                remark: $validated['remark'] ?? null,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin mark-payment-received failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Build the small addons list that hangs under the product details cell
     * in the orders datatable. Returns an empty string when the order item
     * has no addons — keeps the DOM clean for non-addon orders.
     *
     * Output shape (HTML, escaped):
     *   <div class="mt-1">
     *     <span>Add-ons:</span>
     *     <ul>
     *       <li>Group — Item · $1.50</li>
     *       ...
     *     </ul>
     *   </div>
     */
    private function renderAddonsSnippet(?OrderItem $orderItem): string
    {
        if (! $orderItem) {
            return '';
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        if (! $addons || $addons->isEmpty()) {
            return '';
        }

        $rows = $addons->map(function ($addon) {
            $groupTitle = $addon->addonGroup?->title;
            $itemTitle = $addon->addonItem?->title ?? '—';
            $price = $this->currencyService->format((float) $addon->price);
            $label = $groupTitle ? e($groupTitle) . ' — ' . e($itemTitle) : e($itemTitle);

            return "<li>{$label} <span class='text-muted'>· {$price}</span></li>";
        })->implode('');

        return "<div class='mt-1'>
                    <span class='fw-medium'>" . __('labels.addons') . ":</span>
                    <ul class='list-unstyled mb-0 ps-3 small'>{$rows}</ul>
                </div>";
    }

    /**
     * Compact attachment badge for the orders datatable. Renders one visible
     * "Prescription (N)" chip plus N-1 hidden anchors so all attachments form
     * a single fslightbox gallery scoped to the order item, not the whole page.
     * Returns '' when the item has no attachments.
     */
    private function renderAttachmentsBadge(?OrderItem $orderItem): string
    {
        if (! $orderItem) {
            return '';
        }

        $media = $orderItem->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
        if ($media->isEmpty()) {
            return '';
        }

        $count = $media->count();
        $gallery = 'order-item-attachments-' . (int) $orderItem->id;
        $label = __('labels.prescription') . ' (' . $count . ')';

        $first = $media->shift();
        $html = '<a href="' . e($first->getUrl()) . '" data-fslightbox="' . e($gallery) . '" class="badge bg-orange-lt mt-2 me-1" target="_blank">'
            . '<i class="ti ti-paperclip me-1"></i>' . e($label)
            . '</a>';
        foreach ($media as $m) {
            $html .= '<a href="' . e($m->getUrl()) . '" data-fslightbox="' . e($gallery) . '" hidden></a>';
        }

        return $html;
    }

    public function orderInvoice(Request $request): View
    {
        try {
            $orderId = $request->input('id');
            $sellerOrder = SellerOrder::with(
                'order',
                'seller',
                'order.promoLine',
                'items.product',
                'items.orderItem.store',
                'items.variant',
                'items.orderItem',
                // Pull the addon snapshot + related labels so the invoice
                // can render "Add-on: Toppings / Extra cheese — $1.50"
                // under each line without extra queries.
                'items.orderItem.addons.addonGroup',
                'items.orderItem.addons.addonItem',
            )
                ->whereHas('order', function ($q) use ($orderId) {
                    $q->where('uuid', $orderId);
                })
                //                ->where('order_id', $orderId)
                ->get();
            if (count($sellerOrder) === 0) {
                abort(404, __('labels.order_not_found'));
            }

            $orderData = $sellerOrder[0]['order'];
            if (! $request->hasValidSignature() && ! $request->hasValidSignature(false)) {
                $this->authorize('viewInvoice', $orderData);
            }

            if ($this->getPanel() === 'seller') {
                $user = auth()->user();
                $seller = $user?->seller();
                if (! $seller) {
                    abort(404, __('labels.seller_not_found'));
                }
                $sellerOrder = $sellerOrder->where('seller_id', $seller->id);
                if ($sellerOrder->isEmpty()) {
                    abort(403, __('messages.unauthorized_action'));
                }
            }

            // Attach seller authorized signature image URL for each seller
            foreach ($sellerOrder as $so) {
                if ($so->seller) {
                    $so->seller->authorized_signature = $so->seller->getFirstMediaUrl(SpatieMediaCollectionName::AUTHORIZED_SIGNATURE()) ?? null;
                }
            }

            return view('layouts.order-invoice', [
                'order' => $orderData,
                'sellerOrder' => $sellerOrder,
            ]);
        } catch (AuthorizationException) {
            abort(403, __('messages.unauthorized_action'));
        }
    }

    public function sendInvoiceWhatsApp(
        Request $request,
        WhatsAppService $whatsAppService
    ): JsonResponse {
        try {
            $validated = $request->validate([
                'id' => ['required', 'string'],
            ]);

            $order = Order::with('user')->where('uuid', $validated['id'])->firstOrFail();
            $this->authorize('viewInvoice', $order);

            if ($this->getPanel() === 'seller') {
                $seller = auth()->user()?->seller();
                $sellerCanViewOrder = $seller
                    ? SellerOrder::where('order_id', $order->id)->where('seller_id', $seller->id)->exists()
                    : false;

                abort_unless($sellerCanViewOrder, 403, __('messages.unauthorized_action'));
            }

            $invoiceUrl = URL::signedRoute('order-invoice', ['id' => $order->uuid]);
            $message = $whatsAppService->queueModuleMessage(
                module: WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
                mobile: $order->user?->mobile ?: $order->billing_phone ?: $order->shipping_phone,
                data: [
                    'customer_name' => (string) ($order->user?->name ?: $order->billing_name ?: $order->shipping_name ?: 'Customer'),
                    'order_number' => (string) $order->uuid,
                    'total_amount' => (string) ($order->final_total ?? $order->total_payable ?? ''),
                    'invoice_url' => $invoiceUrl,
                ],
                relations: [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                ],
            );

            if ($message->status === 'skipped') {
                return ApiResponseType::sendJsonResponse(false, (string) $message->error, [
                    'message_id' => $message->id,
                ], 422);
            }

            return ApiResponseType::sendJsonResponse(true, 'Invoice WhatsApp message queued successfully.', [
                'message_id' => $message->id,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('messages.unauthorized_action'), [], 403);
        }
    }

    /**
     * Return live-tracking payload for an order's delivery.
     */
    public function liveTracking(int $id): JsonResponse
    {
        try {
            $order = Order::with([
                'items.store',
            ])->findOrFail($id);

            $this->authorize('viewAny', $order);

            $activeStatuses = [
                OrderStatusEnum::ASSIGNED(),
                OrderStatusEnum::COLLECTED(),
                OrderStatusEnum::OUT_FOR_DELIVERY(),
            ];

            if (! in_array($order->status, $activeStatuses, true)) {
                return ApiResponseType::sendJsonResponse(false, 'labels.tracking_not_available', null);
            }

            // Rider current location
            $riderLocation = null;
            if ($order->delivery_boy_id) {
                $loc = DeliveryBoyLocation::where('delivery_boy_id', $order->delivery_boy_id)->first();
                if ($loc) {
                    $riderLocation = [
                        'latitude' => (float) $loc->latitude,
                        'longitude' => (float) $loc->longitude,
                        'recorded_at' => $loc->recorded_at,
                    ];
                }
            }

            // Build route starting from the rider's current position
            $storeIds = $order->items->pluck('store_id')->unique()->toArray();
            $deliveryRoute = DeliveryZoneService::calculateDeliveryRoute(
                (float) $order->shipping_latitude,
                (float) $order->shipping_longitude,
                $storeIds,
                $order,
                $riderLocation ? (float) $riderLocation['latitude'] : null,
                $riderLocation ? (float) $riderLocation['longitude'] : null,
            );

            // Filter out the customer entry (store_id === null) and map to frontend shape
            $routeStores = array_filter(
                $deliveryRoute['route_details'] ?? [],
                fn($detail) => ! empty($detail['store_id'])
            );
            $stores = array_values(array_map(fn($detail) => [
                'id' => $detail['store_id'],
                'name' => $detail['store_name'] ?? '',
                'latitude' => (float) ($detail['latitude'] ?? 0),
                'longitude' => (float) ($detail['longitude'] ?? 0),
                'is_collected' => (bool) ($detail['is_collected'] ?? false),
            ], $routeStores));

            $data = [
                'rider' => $riderLocation,
                'stores' => $stores,
                'customer' => [
                    'latitude' => (float) $order->shipping_latitude,
                    'longitude' => (float) $order->shipping_longitude,
                    'name' => $order->shipping_name ?? '',
                ],
                'order_status' => $order->status,
            ];

            return ApiResponseType::sendJsonResponse(true, 'labels.success', $data);
        } catch (\Throwable $e) {
            Log::error('Order live tracking error', ['order_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null);
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

            $sellerOrder = SellerOrder::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();

            if (! $sellerOrder) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $this->authorize('pickupManage', $sellerOrder);

            $result = app(PickupService::class)->markReadyForPickup($sellerOrder);

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

            $sellerOrder = SellerOrder::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();

            if (! $sellerOrder) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $this->authorize('pickupManage', $sellerOrder);

            $result = app(PickupService::class)->verifyPickupOtp($sellerOrder, $request->validated('otp'));

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
     * Preview refund / return for self-pickup order items.
     */
    public function refundPreview(Request $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $sellerOrder = SellerOrder::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();

            if (! $sellerOrder || ($sellerOrder->delivery_type?->value ?? $sellerOrder->delivery_type) !== 'pickup') {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', null, 404);
            }

            $this->authorize('update', $sellerOrder);

            $order = $sellerOrder->order;
            $items = OrderItem::where('order_id', $order->id)
                ->whereHas('store', fn($q) => $q->where('seller_id', $seller->id))
                ->with(['addons.addonItem:id,title'])
                ->get();

            $discountRatio = $this->discountRatio($order);

            $alreadyRefundedByItem = PosRefundLine::whereIn('order_item_id', $items->pluck('id'))
                ->selectRaw('order_item_id, SUM(quantity) as qty, SUM(amount) as amt')
                ->groupBy('order_item_id')
                ->get()
                ->keyBy('order_item_id');

            $rows = [];
            $alreadyTotal = 0.0;
            foreach ($items as $item) {
                $alreadyQty = (int) ($alreadyRefundedByItem[$item->id]->qty ?? 0);
                $alreadyAmt = (float) ($alreadyRefundedByItem[$item->id]->amt ?? 0);
                $alreadyTotal += $alreadyAmt;

                if (! $item->isEligibleForPickupReturn()) {
                    continue;
                }

                $maxRefundable = $item->getRemainingRefundableQuantity();
                if ($maxRefundable <= 0) {
                    continue;
                }

                $perUnit = (int) $item->quantity > 0
                    ? round(((float) $item->subtotal * $discountRatio) / (int) $item->quantity, 2)
                    : 0.0;

                $addonSummary = $item->addons->isNotEmpty()
                    ? $item->addons->pluck('addonItem.title')->filter()->implode(', ') ?: null
                    : null;

                $rows[] = [
                    'order_item_id' => $item->id,
                    'title' => $item->title,
                    'variant_title' => $item->variant_title,
                    'quantity' => (int) $item->quantity,
                    'already_refunded' => $alreadyQty,
                    'max_refundable' => $maxRefundable,
                    'per_unit_amount' => $perUnit,
                    'line_total' => round($perUnit * $maxRefundable, 2),
                    'addon_summary' => $addonSummary,
                ];
            }

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'refundable' => $rows,
                'already_refunded_total' => round($alreadyTotal, 2),
                'currency_code' => $order->currency_code,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Create return refund for self-pickup order.
     */
    public function createRefund(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.order_item_id' => ['required', 'integer', 'min:1'],
                'lines.*.quantity' => ['required', 'integer', 'min:1'],
                'reason' => ['nullable', 'string', 'max:500'],
                'method' => ['nullable', 'string', Rule::in(PosRefund::METHODS)],
                'method_note' => ['nullable', 'string', 'max:200'],
            ]);

            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $sellerOrder = SellerOrder::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();

            if (! $sellerOrder || ($sellerOrder->delivery_type?->value ?? $sellerOrder->delivery_type) !== 'pickup') {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', null, 404);
            }

            $this->authorize('update', $sellerOrder);

            $order = $sellerOrder->order;

            $lineQtys = [];
            foreach ($request->input('lines', []) as $line) {
                $itemId = (int) $line['order_item_id'];
                $qty = (int) $line['quantity'];

                $orderItem = OrderItem::where('id', $itemId)
                    ->where('order_id', $order->id)
                    ->first();

                if (! $orderItem) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.pos_order_items_not_owned'), null, 422);
                }

                if (! $orderItem->isEligibleForPickupReturn()) {
                    return ApiResponseType::sendJsonResponse(false, 'Item is not eligible for return.', null, 422);
                }

                $maxRefundable = $orderItem->getRemainingRefundableQuantity();
                if ($qty > $maxRefundable) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.pos_refund_qty_exceeds_remaining'), null, 422);
                }

                $lineQtys[$itemId] = $qty;
            }

            $methodMeta = null;
            if ($note = trim((string) $request->input('method_note', ''))) {
                $methodMeta = ['note' => $note];
            }

            try {
                $refund = app(PosRefundService::class)->create(
                    order: $order,
                    lineQtys: $lineQtys,
                    reason: $request->input('reason'),
                    by: $user,
                    sellerId: (int) $seller->id,
                    method: (string) ($request->input('method') ?: 'cash'),
                    methodMeta: $methodMeta,
                );
            } catch (\InvalidArgumentException $e) {
                return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
            }

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_refund_recorded', [
                'refund_id' => $refund->id,
                'total_amount' => (float) $refund->total_amount,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            Log::error('Pickup order return failed', ['seller_order_id' => $id, 'err' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Get return refund history for self-pickup order.
     */
    public function refundHistory(Request $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $sellerOrder = SellerOrder::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();

            if (! $sellerOrder || ($sellerOrder->delivery_type?->value ?? $sellerOrder->delivery_type) !== 'pickup') {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', null, 404);
            }

            $this->authorize('update', $sellerOrder);

            $order = $sellerOrder->order;

            $refunds = PosRefund::where('order_id', $order->id)
                ->where('store_id', $sellerOrder->items()->first()?->orderItem?->store_id)
                ->with(['lines.orderItem:id,title,variant_title', 'refundedBy:id,name'])
                ->orderByDesc('id')
                ->get();

            $payload = $refunds->map(function (PosRefund $r) {
                return [
                    'id' => $r->id,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'total_amount' => (float) $r->total_amount,
                    'method' => $r->refund_method,
                    'method_label' => PosRefund::methodLabel((string) $r->refund_method),
                    'method_note' => $r->refund_method_meta['note'] ?? null,
                    'reason' => $r->reason,
                    'refunded_by' => $r->refundedBy?->name,
                    'lines' => $r->lines->map(fn($l) => [
                        'order_item_id' => $l->order_item_id,
                        'title' => $l->orderItem?->title,
                        'variant_title' => $l->orderItem?->variant_title,
                        'quantity' => (int) $l->quantity,
                        'amount' => (float) $l->amount,
                    ])->values(),
                ];
            });

            $totalRefunded = (float) $refunds->sum('total_amount');

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'order_id' => $order->id,
                'order_total' => (float) $sellerOrder->total_price,
                'currency_code' => $order->currency_code,
                'total_refunded' => $totalRefunded,
                'refunds' => $payload,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Reused helper to calculate discount ratio.
     */
    protected function discountRatio(Order $order): float
    {
        $gross = (float) $order->subtotal;
        if ($gross <= 0) {
            return 1.0;
        }

        $charged = max(0.0, $gross - (float) $order->promo_discount);

        return min(1.0, $charged / $gross);
    }

    protected function resolveScheduledPreparationStartTime(Order $order, ?Store $store): ?Carbon
    {
        $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');

        if ($store && (int) ($store->scheduled_delivery_buffer_minutes ?? 60) === 0) {
            return null;
        }

        if ($order->preparation_scheduled_at) {
            return Carbon::parse($order->preparation_scheduled_at, $storeTz);
        }

        if (! $order->scheduled_date) {
            return null;
        }

        if ($order->scheduled_slot_start_time) {
            $slotStartTime = $order->scheduled_slot_start_time;
        } elseif ($order->deliveryTimeSlot) {
            $slotStartTime = $order->deliveryTimeSlot->start_time;
        } else {
            $dayName = strtolower($order->scheduled_date->format('l'));
            $slotStartTime = $store?->metadata['operating_hours'][$dayName]['open_time'] ?? '09:00:00';
        }

        $slotStart = Carbon::parse($order->scheduled_date->format('Y-m-d') . ' ' . $slotStartTime, $storeTz);
        $order->loadMissing(['items.product', 'items.store']);
        $prepDuration = app(PreparationSchedulerService::class)->getOrderPrepTime($order);

        return $slotStart->subMinutes($prepDuration);
    }

    protected function resolveScheduledSlotLabel(Order $order): string
    {
        if (! empty($order->scheduled_slot_start_time) && ! empty($order->scheduled_slot_end_time)) {
            return e($order->scheduled_slot_start_time . ' - ' . $order->scheduled_slot_end_time);
        }

        if (! empty($order->scheduled_time_slot_label)) {
            return e($order->scheduled_time_slot_label);
        }

        if ($order->deliveryTimeSlot) {
            return e($order->deliveryTimeSlot->start_time . ' - ' . $order->deliveryTimeSlot->end_time);
        }

        return e('Date Only');
    }
}
