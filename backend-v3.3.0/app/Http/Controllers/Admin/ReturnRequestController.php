<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\Order\AdminLossBearerEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderReturnActorEnum;
// Comment: Import StoreConfirmReturnRequest to properly validate store rejection reason and action on rejection
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\AdminResolveReturnRequest;
use App\Http\Requests\Returns\AssignReturnRiderRequest;
use App\Http\Requests\Returns\DecideReturnRequest;
use App\Http\Requests\Returns\StoreConfirmReturnRequest;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Services\ReturnPickupService;
use App\Services\ReturnRequestService;
use App\Services\SettingService;
use App\Support\DemoMode;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ReturnRequestController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    public function __construct(
        protected ReturnRequestService $returnRequestService,
        protected ReturnPickupService $returnPickupService,
    ) {}

    /**
     * Admin returns list with filter bar (platform-wide).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $columns = [
            ['title' => __('labels.id'), 'data' => 'id'],
            ['title' => __('labels.order_date'), 'data' => 'order_date'],
            ['title' => __('labels.order_details'), 'data' => 'order_details'],
            ['title' => 'Customer', 'data' => 'customer'],
            ['title' => __('labels.store_seller'), 'data' => 'store_seller'],
            ['title' => __('labels.refund_amount'), 'data' => 'refund_amount'],
            ['title' => __('labels.return_status'), 'data' => 'return_status', 'orderable' => false],
            ['title' => 'Loss Bearer', 'data' => 'loss_bearer', 'orderable' => false],
            ['title' => __('labels.progress'), 'data' => 'pipeline', 'orderable' => false, 'searchable' => false],
            ['title' => __('labels.waiting'), 'data' => 'age', 'orderable' => false, 'searchable' => false],
            ['title' => __('labels.actions'), 'data' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        $returnInsights = [
            'total' => OrderItemReturn::count(),
            'approved' => self::applyInsightStatusGroup(OrderItemReturn::query(), 'approved')->count(),
            'refund_processed' => self::applyInsightStatusGroup(OrderItemReturn::query(), 'refund_processed')->count(),
            'under_review' => self::applyInsightStatusGroup(OrderItemReturn::query(), 'under_review')->count(),
        ];

        $activeStatusGroup = $this->normalizeStatusGroup($request->query('status_group'));

        return view('admin.returns.index', compact('columns', 'returnInsights', 'activeStatusGroup'));
    }

    /**
     * Datatable endpoint — platform-wide, supports the same filter params as
     * the seller list plus seller_id.
     */
    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';

        $columns = ['id', 'created_at', 'order_details', 'customer', 'store_seller', 'refund_amount', 'return_status', 'loss_bearer', 'pipeline', 'age', 'actions'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        $sortable = ['id', 'created_at', 'refund_amount', 'return_status'];
        $effectiveSort = in_array($orderColumn, $sortable, true) ? $orderColumn : 'created_at';

        $query = OrderItemReturn::with([
            'order',
            'orderItem.product',
            'orderItem.variant',
            'orderItem.addons.addonGroup',
            'orderItem.addons.addonItem',
            'store',
            'seller.user',
            'user',
            'decidedBy',
        ])
            ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED());

        $this->applyFilters($query, $request);

        $totalRecords = $query->count();

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('reason', 'like', "%{$searchValue}%")
                    ->orWhere('seller_comment', 'like', "%{$searchValue}%")
                    ->orWhere('admin_comment', 'like', "%{$searchValue}%")
                    ->orWhereHas('order', fn ($qo) => $qo->where('order_id', 'like', "%{$searchValue}%"));
            });
        }

        $filteredRecords = $query->count();

        $results = $query->orderBy($effectiveSort, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $results->map(fn ($ret) => $this->formatReturnRow($ret))->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Admin detail page — shares the same Blade partial as the seller side,
     * with the `panel = 'admin'` flag gating admin-only blocks.
     */
    public function show(int $id): View
    {
        $orderReturn = OrderItemReturn::findOrFail($id);
        $this->authorize('view', $orderReturn);

        $data = $this->returnRequestService->getDetail($orderReturn, 'admin');

        // Rider-assignment context for the Pickup card.
        $data['canAssignRider'] = $this->hasPermission(AdminPermissionEnum::RETURN_ASSIGN_PICKUP());
        $data['storeZoneId'] = $orderReturn->store?->zones()->value('delivery_zones.id');

        return view('admin.returns.show', $data);
    }

    /**
     * Manually assign / reassign / unassign a rider for the return pickup.
     */
    public function assignRider(AssignReturnRiderRequest $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $orderReturn = OrderItemReturn::find($id);
        if (! $orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), [], 404);
        }

        $this->authorize('assignRider', $orderReturn);

        try {
            $result = $this->returnPickupService->assignByAdmin(
                orderReturn: $orderReturn,
                newDeliveryBoyId: $request->validated('delivery_boy_id'),
                reason: $request->validated('reason'),
            );
        } catch (\Throwable $e) {
            Log::error('Admin return assign-rider endpoint failed', ['return_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), [], 500);
        }

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            [],
            $result['success'] ? 200 : 422,
        );
    }

    /**
     * Renders disputed returns view list (Phase 4).
     */
    public function disputedList(Request $request): View
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $columns = [
            ['title' => __('labels.id'), 'data' => 'id'],
            ['title' => 'Order ID', 'data' => 'order_id'],
            ['title' => 'Customer', 'data' => 'customer'],
            ['title' => 'Seller', 'data' => 'seller'],
            ['title' => 'Product', 'data' => 'product'],
            ['title' => 'Escalation Date', 'data' => 'escalation_date'],
            ['title' => 'Dispute Reason', 'data' => 'dispute_reason'],
            ['title' => 'Status', 'data' => 'status', 'orderable' => false],
            ['title' => __('labels.actions'), 'data' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        $disputeInsights = [
            'total' => self::applyDisputeStatusGroup(OrderItemReturn::query(), null)->count(),
            'under_process' => self::applyDisputeStatusGroup(OrderItemReturn::query(), 'under_process')->count(),
            'resolved' => self::applyDisputeStatusGroup(OrderItemReturn::query(), 'resolved')->count(),
        ];

        $activeDisputeStatusGroup = $this->normalizeDisputeStatusGroup($request->query('status_group'));

        return view('admin.returns.disputed', compact('columns', 'disputeInsights', 'activeDisputeStatusGroup'));
    }

    /**
     * Serves JSON datatable payload of disputed returns (Phase 4).
     */
    public function disputedDatatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $query = OrderItemReturn::with([
            'order',
            'orderItem.product',
            'orderItem.variant',
            'store',
            'seller.user',
            'user',
        ]);

        self::applyDisputeStatusGroup(
            $query,
            $this->normalizeDisputeStatusGroup($request->get('status_group')),
        );

        $totalRecords = $query->count();

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('dispute_reason', 'like', "%{$searchValue}%")
                    ->orWhereHas('order', fn ($qo) => $qo->where('order_id', 'like', "%{$searchValue}%"))
                    ->orWhereHas('user', fn ($qu) => $qu->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('store', fn ($qs) => $qs->where('name', 'like', "%{$searchValue}%"));
            });
        }

        $filteredRecords = $query->count();

        $results = $query->orderBy('disputed_at', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $results->map(function ($r) {
            $statusValue = (string) $r->return_status;
            $requiresAction = $statusValue === (string) OrderItemReturnStatusEnum::DISPUTED()
                && is_null($r->resolved_by_admin_at);
            $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="'.e(__('labels.action_required') ?? 'Action Required').'"></span>' : '';
            $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">'.$actionDot.'<span>#'.e($r->id).'</span></div>' : '#'.e($r->id);
            $formattedOrderId = '#'.e($r->order_id);

            $disputedAt = $r->disputed_at;
            $escalationDate = $disputedAt ? "<div><p class='m-0 fw-medium'>{$disputedAt->diffForHumans()}</p>{$disputedAt->format('Y-m-d H:i:s')}</div>" : '—';

            $productTitle = $r->orderItem && $r->orderItem->product
                ? e($r->orderItem->title)
                : __('labels.n_a');
            $variantTitle = $r->orderItem && $r->orderItem->variant ? e($r->orderItem->variant->title) : '';
            $productText = "<div><p class='m-0 fw-medium'>{$productTitle}</p>".($variantTitle ? "<small class='text-muted'>{$variantTitle}</small>" : '').'</div>';

            $customerName = e($r->user?->name ?? '—');
            $customerEmail = e($r->user?->email ?? '');
            $customerText = "<div><p class='m-0 fw-medium'>{$customerName}</p><small class='text-muted'>{$customerEmail}</small></div>";

            $storeName = e($r->store?->name ?? '—');
            $sellerName = e($r->seller?->user?->name ?? '—');
            $sellerText = "<div><p class='m-0 fw-medium'>{$storeName}</p><small class='text-muted'>{$sellerName}</small></div>";

            $disputeReason = e($r->dispute_reason ?? '—');

            $statusLabel = Str::headline($statusValue);
            if ($r->resolved_by_admin_at) {
                $statusText = '<span class="badge bg-success text-white">Resolved: '.e($statusLabel).'</span>';
            } elseif ($statusValue === (string) OrderItemReturnStatusEnum::DISPUTED()) {
                $statusText = '<span class="badge bg-warning text-dark">Under Process</span>';
            } else {
                $statusText = '<span class="badge bg-danger text-white" title="This dispute has no admin resolution timestamp">Needs Review: '.e($statusLabel).'</span>';
            }

            $viewUrl = route('admin.returns.dispute', $r->id);
            $actionBtn = "<a href='{$viewUrl}' class='btn btn-sm btn-primary'>View Dispute</a>";

            return [
                'id' => $formattedId,
                'order_id' => $formattedOrderId,
                'customer' => $customerText,
                'seller' => $sellerText,
                'product' => $productText,
                'escalation_date' => $escalationDate,
                'dispute_reason' => $disputeReason,
                'status' => $statusText,
                'actions' => $actionBtn,
            ];
        })->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    private static function applyDisputeStatusGroup(Builder $query, ?string $statusGroup): Builder
    {
        $query->whereNotNull('disputed_at');

        return match ($statusGroup) {
            'under_process' => $query->whereNull('resolved_by_admin_at'),
            'resolved' => $query->whereNotNull('resolved_by_admin_at'),
            default => $query,
        };
    }

    private function normalizeDisputeStatusGroup(mixed $statusGroup): ?string
    {
        if (! is_string($statusGroup)) {
            return null;
        }

        return in_array($statusGroup, ['under_process', 'resolved'], true) ? $statusGroup : null;
    }

    /**
     * Renders read-only dispute detail page (Phase 4).
     */
    public function disputeDetails(int $id): View
    {
        $orderReturn = OrderItemReturn::findOrFail($id);
        $this->authorize('view', $orderReturn);

        if (! $orderReturn->disputed_at) {
            abort(404, 'Return request has not been disputed.');
        }

        $data = $this->returnRequestService->getDetail($orderReturn, 'admin');

        // Determine if rider assignment exists
        $rider = $orderReturn->deliveryBoy ?? ($orderReturn->order?->deliveryBoy ?? null);
        $data['assignedRider'] = $rider;

        return view('admin.returns.dispute', $data);
    }

    /**
     * Processes form submission to resolve a dispute (Phase 4).
     */
    public function resolveDispute(AdminResolveReturnRequest $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $orderReturn = OrderItemReturn::findOrFail($id);

        $this->authorize('decide', $orderReturn);

        try {
            $resolvedReturn = $this->returnRequestService->resolveDispute(
                return: $orderReturn,
                action: $request->validated('action'),
                options: [
                    'remarks' => $request->validated('remarks'),
                    'resolution_photos' => $request->file('resolution_photos'),
                ],
                admin: Auth::user()
            );

            return ApiResponseType::sendJsonResponse(
                true,
                'Dispute resolved successfully.',
                [
                    'return_id' => $resolvedReturn->id,
                    'status' => (string) $resolvedReturn->return_status,
                ],
                200
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 400);
        } catch (RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 400);
        } catch (\Throwable $e) {
            Log::error('Dispute resolution failed', [
                'return_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponseType::sendJsonResponse(false, $e->getMessage() ?: 'An error occurred during resolution.', [], 500);
        }
    }

    /**
     * Approve / reject decision — admin acting on behalf of the seller.
     */
    public function decision(DecideReturnRequest $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }
        $user = Auth::user();

        $orderReturn = OrderItemReturn::find($id);
        if (! $orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), [], 404);
        }

        $this->authorize('decide', $orderReturn);

        $action = $request->validated('action');

        try {
            $orderReturn = $this->returnRequestService->decide(
                return: $orderReturn,
                action: $action,
                actor: $user,
                actorType: OrderReturnActorEnum::ADMIN,
                comment: $request->validated('admin_comment'),
            );
        } catch (RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            Log::error('Admin return decision failed', ['return_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), [], 500);
        }

        $message = $action === 'approve'
            ? __('messages.return_approved_successfully')
            : __('messages.return_rejected_successfully');

        return ApiResponseType::sendJsonResponse(true, $message, $this->formatReturnRow($orderReturn), 200);
    }

    /**
     * Apply filter params from the datatable request to the query. Admin
     * gets an extra `seller_id` filter on top of the shared shape.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($status = $request->get('status')) {
            if ($status === 'awaiting_inspection') {
                $query->where('return_status', OrderItemReturnStatusEnum::RECEIVED_BY_SELLER());
            } elseif ($status === 'under_admin_review') {
                $query->where('return_status', OrderItemReturnStatusEnum::DISPUTED());
            } elseif ($status === 'completed') {
                $query->whereIn('return_status', [OrderItemReturnStatusEnum::COMPLETED(), OrderItemReturnStatusEnum::REFUND_PROCESSED(), 'completed']);
            } elseif ($status === 'rejected') {
                $query->whereIn('return_status', [OrderItemReturnStatusEnum::REJECTED(), OrderItemReturnStatusEnum::SELLER_REJECTED(), OrderItemReturnStatusEnum::REJECTED_AT_STORE(), 'rejected']);
            } elseif ($status === 'auto_approved_sla') {
                $query->whereIn('return_status', [OrderItemReturnStatusEnum::COMPLETED(), OrderItemReturnStatusEnum::REFUND_PROCESSED(), 'completed'])
                    ->where('seller_comment', 'like', '%SLA expiry%');
            } else {
                $query->where('return_status', $status);
            }
        } elseif ($statusGroup = $this->normalizeStatusGroup($request->get('status_group'))) {
            self::applyInsightStatusGroup($query, $statusGroup);
        }

        if ($sellerId = $request->get('seller_id')) {
            $query->where('seller_id', (int) $sellerId);
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($min = $request->get('refund_min')) {
            $query->where('refund_amount', '>=', (float) $min);
        }

        if ($max = $request->get('refund_max')) {
            $query->where('refund_amount', '<=', (float) $max);
        }
    }

    /**
     * Format a single OrderItemReturn to datatable row (HTML where needed).
     */
    private function formatReturnRow(OrderItemReturn $r): array
    {
        $created = $r->created_at;
        $orderDateHtml = "<div><p class='m-0 fw-medium'>{$created->diffForHumans()}</p>{$created->format('Y-m-d H:i:s')}</div>";

        $productTitle = $r->orderItem && $r->orderItem->product
            ? e($r->orderItem->title)
            : __('labels.n_a');

        $variantTitle = $r->orderItem && $r->orderItem->variant ? e($r->orderItem->variant->title) : '';
        $images = '';
        if (! empty($r->images)) {
            foreach ($r->images as $image) {
                $images .= view('partials.image', ['image' => $image])->render();
            }
        }

        $addonsHtml = $this->renderReturnAddons($r->orderItem);

        $detailUrl = route('admin.returns.show', $r->id);
        $linkedTitle = "<a href='{$detailUrl}' class='link-primary fw-medium'>{$productTitle}</a>";

        $orderDetails = "<div>
                <p class='m-0'>{$linkedTitle} ".($variantTitle ? "<small class='text-muted data-bs-toggle='tooltip' data-bs-placement='top' title='{$variantTitle}'> " . Str::limit($variantTitle, 15) . " </small>" : '')."</p>
                <small class='text-muted'>".__('labels.order_id')." - {$r->order_id}</small> |
                <small class='text-muted'>".__('labels.order_item_id')." - {$r->order_item_id}</small>
                {$addonsHtml}
                <div class='mt-2 d-flex align-items-center gap-1'>{$images}</div>
            </div>";

        $storeName = e($r->store?->name ?? '—');
        $sellerName = e($r->seller?->user?->name ?? '—');
        $storeSeller = "<div>
                <p class='m-0 fw-medium'>{$storeName}</p>
                <small class='text-muted'>{$sellerName}</small>
            </div>";

        $refundAmount = '<div>'.number_format($r->refund_amount, 2).'</div>';

        // Return Dispute System
        // UI Polish
        // Consistent Status Badging Logic
        $statusVal = (string) $r->return_status;
        $pickupVal = (string) $r->pickup_status;

        if ($statusVal === (string) OrderItemReturnStatusEnum::CANCELLED() || $pickupVal === 'cancelled') {
            $statusLabel = 'Cancelled';
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::REQUESTED()) {
            $statusLabel = 'Requested';
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::SELLER_APPROVED()) {
            $statusLabel = 'Pickup Approved';
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::PICKUP_ASSIGNED()) {
            $statusLabel = 'Pickup Assigned';
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::PICKED_UP()) {
            $statusLabel = 'Picked Up';
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER()) {
            $slaHours = (int) (app(SettingService::class)->getSettingValues('seller')['return_seller_inspection_sla_hours'] ?? 48);
            $receivedAt = $r->received_at;
            $deadline = $receivedAt ? $receivedAt->copy()->addHours($slaHours) : null;
            $now = Carbon::now();
            $slaExpired = $deadline ? $now->greaterThanOrEqualTo($deadline) : false;

            if ($slaExpired) {
                $statusLabel = 'Inspection Expired';
            } else {
                $diffInHours = $now->diffInHours($deadline);
                if ($diffInHours < 12) {
                    $statusLabel = 'Inspection Due Today';
                } else {
                    $statusLabel = 'Awaiting Inspection';
                }

                $diffInDays = $now->diffInDays($deadline);
                if ($diffInDays >= 1) {
                    $remHours = $diffInHours % 24;
                    $remainingText = "{$diffInDays}d {$remHours}h left";
                } elseif ($diffInHours >= 1) {
                    $remainingText = "{$diffInHours}h left";
                } else {
                    $diffInMinutes = $now->diffInMinutes($deadline);
                    $remainingText = "{$diffInMinutes}m left";
                }
                $statusLabel .= " ({$remainingText})";
            }
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::DISPUTED()) {
            $statusLabel = 'Under Admin Review';
        } elseif ($statusVal === (string) OrderItemReturnStatusEnum::REFUND_PROCESSED()) {
            $statusLabel = __('labels.refund_processed');
        } elseif (in_array($statusVal, [(string) OrderItemReturnStatusEnum::COMPLETED(), 'completed'], true)) {
            $statusLabel = 'Completed';
            if ((float) $r->refund_amount <= 0) {
                $statusLabel .= ' (No Refund)';
            }
        } elseif (in_array($statusVal, [(string) OrderItemReturnStatusEnum::REJECTED(), (string) OrderItemReturnStatusEnum::SELLER_REJECTED(), (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(), 'rejected'], true)) {
            $statusLabel = 'Rejected';
            if ((float) $r->refund_amount <= 0) {
                $statusLabel .= ' (No Refund)';
            }
        } else {
            $statusLabel = Str::replace('_', ' ', e($statusVal));
        }

        $decidedByValue = $r->decided_by_type instanceof OrderReturnActorEnum
            ? $r->decided_by_type->value
            : (string) $r->decided_by_type;
        $byAdminPill = $decidedByValue === (string) OrderReturnActorEnum::ADMIN()
            ? " <span class='badge bg-info-lt ms-1'>".__('labels.by_support').'</span>'
            : '';
        $returnStatus = view('partials.order-status', [
            'status' => $statusLabel,
        ])->render().$byAdminPill;

        $pipeline = view('returns._pipeline-status', ['return' => $r])->render();

        $age = $this->renderAgeBadge($r);

        $actions = $this->renderActions($r);

        $customerName = e($r->user?->name ?? '—');
        $customerPhone = e($r->user?->mobile ?? '—');
        $customerHtml = "<div><p class='m-0 fw-medium'>{$customerName}</p><small class='text-muted'>{$customerPhone}</small></div>";

        $lossBearerHtml = '—';
        if ($r->admin_resolution) {
            $lossBearerText = match ($r->admin_resolution) {
                AdminLossBearerEnum::CUSTOMER => 'Customer Bears Loss',
                AdminLossBearerEnum::SELLER => 'Seller Bears Loss',
                AdminLossBearerEnum::RIDER => 'Rider Bears Loss',
                AdminLossBearerEnum::PLATFORM => 'Platform Bears Loss',
                default => (string) $r->admin_resolution,
            };
            $lossBearerBadge = match ($r->admin_resolution) {
                AdminLossBearerEnum::CUSTOMER => 'bg-danger-lt text-danger',
                AdminLossBearerEnum::SELLER => 'bg-primary-lt text-primary',
                AdminLossBearerEnum::RIDER => 'bg-warning-lt text-warning-dark',
                AdminLossBearerEnum::PLATFORM => 'bg-success-lt text-success',
                default => 'bg-secondary-lt text-secondary',
            };
            $lossBearerHtml = "<span class='badge {$lossBearerBadge}'>{$lossBearerText}</span>";
        }

        $requiresAction = (is_null($r->seller_approved_at) && $statusVal === (string) OrderItemReturnStatusEnum::REQUESTED())
            || ($statusVal === (string) OrderItemReturnStatusEnum::REQUESTED())
            || ($statusVal === (string) OrderItemReturnStatusEnum::DISPUTED() && is_null($r->resolved_by_admin_at));
        $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="'.e(__('labels.action_required') ?? 'Action Required').'"></span>' : '';
        $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">'.$actionDot.'<span class="text-muted">#'.$r->id.'</span></div>' : "<span class='text-muted'>#{$r->id}</span>";

        return [
            'id' => $formattedId,
            'order_date' => $orderDateHtml,
            'order_details' => $orderDetails,
            'customer' => $customerHtml,
            'store_seller' => $storeSeller,
            'refund_amount' => $refundAmount,
            'return_status' => $returnStatus,
            'loss_bearer' => $lossBearerHtml,
            'pipeline' => $pipeline,
            'age' => $age,
            'actions' => $actions,
        ];
    }

    /**
     * Render the actions cell — Approve/Reject quick buttons while pending,
     * or store confirm/reject when item is received at store.
     */
    private function renderActions(OrderItemReturn $r): string
    {
        $detailUrl = route('admin.returns.show', $r->id);
        $eyeBtn = "<a href='{$detailUrl}' class='btn btn-icon btn-outline-primary me-1' title='".e(__('labels.view'))."' data-bs-toggle='tooltip'><i class='ti ti-eye'></i></a>";

        // Comment: Finalized statuses check prevents buttons when store receipt confirmed or rejected without/with customer refund
        $orderItemStatus = (string) ($r->orderItem?->status ?? '');
        $isFinalizedStatus = ! empty($r->store_rejection_reason) || ! empty($r->action_on_rejection) || ! empty($r->refund_processed_at) || in_array((string) $r->return_status, [
            (string) OrderItemReturnStatusEnum::COMPLETED(),
            (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
            (string) OrderItemReturnStatusEnum::SELLER_REJECTED(),
            (string) OrderItemReturnStatusEnum::CANCELLED(),
            (string) OrderItemReturnStatusEnum::REFUND_PROCESSED(),
            'rejected',
            'completed',
        ], true) || in_array($orderItemStatus, [
            (string) OrderItemStatusEnum::REJECTED(),
            (string) OrderItemStatusEnum::REFUNDED(),
            (string) OrderItemStatusEnum::CANCELLED(),
            'rejected',
            'refunded',
            'cancelled',
        ], true) || in_array((string) $r->pickup_status, [
            (string) OrderItemReturnPickupStatusEnum::CANCELLED(),
            'rejected',
            'cancelled',
        ], true);

        if (! $isFinalizedStatus && (string) $r->return_status === (string) OrderItemReturnStatusEnum::REQUESTED()) {
            return "<div class='d-flex align-items-center'>
                {$eyeBtn}
                <button class='btn btn-success me-1' data-bs-toggle='modal' data-bs-target='#adminAcceptModel' data-id='{$r->id}'>"
                .__('labels.approve')."</button>
                <button class='btn btn-outline-danger' data-bs-toggle='modal' data-bs-target='#adminRejectModel' data-id='{$r->id}'>"
                .__('labels.reject').'</button>
            </div>';
        }

        return "<div class='d-flex align-items-center'>{$eyeBtn}</div>";
    }

    /**
     * Waiting badge — red when REQUESTED for more than 24h with no seller action.
     */
    private function renderAgeBadge(OrderItemReturn $r): string
    {
        $age = $r->created_at?->diffForHumans(['short' => true]) ?? '—';
        $isStale = (string) $r->return_status === (string) OrderItemReturnStatusEnum::REQUESTED()
            && $r->created_at?->lt(Carbon::now()->subDay());

        $class = $isStale ? 'badge bg-danger-lt' : 'badge bg-secondary-lt';
        $warn = $isStale
            ? " <span title='".__('labels.awaiting_decision_over_24h')."'>⚠</span>"
            : '';

        return "<span class='{$class}'>{$age}{$warn}</span>";
    }

    /**
     * Build the "Add-ons" block rendered under each product in the returns
     * datatable. Falls back to a lazy query when the `addons` relation isn't
     * eager-loaded (decision path re-renders rows without eager load).
     */
    private function renderReturnAddons(?OrderItem $orderItem): string
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
            $price = number_format((float) $addon->price, 2);
            $label = $groupTitle ? e($groupTitle).' — '.e($itemTitle) : e($itemTitle);

            return "<li>{$label} <span class='text-muted'>· {$price}</span></li>";
        })->implode('');

        return "<div class='mt-2'>
                    <span class='fw-medium'>".__('labels.addons').":</span>
                    <ul class='list-unstyled mb-0 ps-3 small'>{$rows}</ul>
                </div>";
    }

    public static function applyInsightStatusGroup(Builder $query, ?string $statusGroup): Builder
    {
        return match ($statusGroup) {
            'approved' => $query->where(function (Builder $q) {
                $q->whereIn('return_status', [
                    OrderItemReturnStatusEnum::SELLER_APPROVED(),
                    OrderItemReturnStatusEnum::PICKUP_ASSIGNED(),
                    OrderItemReturnStatusEnum::PICKED_UP(),
                    OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
                    OrderItemReturnStatusEnum::REFUND_PROCESSED(),
                    OrderItemReturnStatusEnum::COMPLETED(),
                ])->orWhereNotNull('seller_approved_at');
            })->whereNotIn('return_status', [
                OrderItemReturnStatusEnum::CANCELLED(),
                OrderItemReturnStatusEnum::DISPUTED(),
            ]),
            'refund_processed' => $query->where(function (Builder $q) {
                $q->whereIn('return_status', [
                    OrderItemReturnStatusEnum::REFUND_PROCESSED(),
                    OrderItemReturnStatusEnum::COMPLETED(),
                ])->orWhereNotNull('refund_processed_at');
            }),
            'under_review' => $query->where(function (Builder $q) {
                $q->whereIn('return_status', [
                    OrderItemReturnStatusEnum::REQUESTED(),
                    OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
                ])->orWhere(function (Builder $q2) {
                    $q2->where('return_status', OrderItemReturnStatusEnum::DISPUTED())
                        ->whereNull('resolved_by_admin_at');
                });
            }),
            default => $query,
        };
    }

    private function normalizeStatusGroup(mixed $statusGroup): ?string
    {
        if (! is_string($statusGroup)) {
            return null;
        }

        return in_array($statusGroup, ['approved', 'refund_processed', 'under_review'], true) ? $statusGroup : null;
    }
}
