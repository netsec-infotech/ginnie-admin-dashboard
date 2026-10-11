<?php

namespace App\Http\Controllers;

use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderReturnActorEnum;
use App\Http\Requests\Returns\DecideReturnRequest;
use App\Http\Requests\Returns\StoreConfirmReturnRequest;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Setting;
use App\Services\ReturnRequestService;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ReturnRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ReturnRequestService $returnRequestService) {}

    /**
     * Display seller returns list page with filter bar.
     */
    public function index(): View
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $columns = [
            ['title' => __('labels.id'), 'data' => 'id'],
            ['title' => __('labels.order_date'), 'data' => 'order_date'],
            ['title' => __('labels.order_details'), 'data' => 'order_details'],
            ['title' => __('labels.refund_amount'), 'data' => 'refund_amount'],
            ['title' => __('labels.return_status'), 'data' => 'return_status', 'orderable' => false],
            ['title' => __('labels.progress'), 'data' => 'pipeline', 'orderable' => false, 'searchable' => false],
            ['title' => __('labels.waiting'), 'data' => 'age', 'orderable' => false, 'searchable' => false],
            ['title' => __('labels.actions'), 'data' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        $seller = auth()->user()?->seller();
        $sellerId = $seller?->id ?? 0;

        $returnInsights = [
            'total' => OrderItemReturn::where('seller_id', $sellerId)->count(),
            'approved' => OrderItemReturn::where('seller_id', $sellerId)->where(function ($q) {
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
            ])->count(),
            'refund_processed' => OrderItemReturn::where('seller_id', $sellerId)->where(function ($q) {
                $q->whereIn('return_status', [
                    OrderItemReturnStatusEnum::REFUND_PROCESSED(),
                    OrderItemReturnStatusEnum::COMPLETED(),
                ])->orWhereNotNull('refund_processed_at');
            })->count(),
            'under_review' => OrderItemReturn::where('seller_id', $sellerId)->where(function ($q) {
                $q->whereIn('return_status', [
                    OrderItemReturnStatusEnum::REQUESTED(),
                    OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
                ])->orWhere(function ($q2) {
                    $q2->where('return_status', OrderItemReturnStatusEnum::DISPUTED())
                        ->whereNull('resolved_by_admin_at');
                });
            })->count(),
        ];

        return view('seller.returns.index', compact('columns', 'returnInsights'));
    }

    /**
     * Datatable endpoint for seller returns.
     *
     * Filter params: status, date_from, date_to, refund_min, refund_max.
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

        $columns = ['id', 'created_at', 'order_details', 'refund_amount', 'return_status', 'pipeline', 'age', 'actions'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        $sortable = ['id', 'created_at', 'refund_amount', 'return_status'];
        $effectiveSort = in_array($orderColumn, $sortable, true) ? $orderColumn : 'created_at';

        $seller = $request->user()->seller();
        if (empty($seller)) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.seller_not_found'),
                data: []
            );
        }

        $query = OrderItemReturn::with([
            'order',
            'orderItem.product',
            'orderItem.variant',
            'orderItem.addons.addonGroup',
            'orderItem.addons.addonItem',
            'store',
            'user',
            'decidedBy',
        ])
            ->where('seller_id', $seller->id)
            ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED());

        $this->applyFilters($query, $request);

        $totalRecords = $query->count();

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('reason', 'like', "%{$searchValue}%")
                    ->orWhere('seller_comment', 'like', "%{$searchValue}%")
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
     * Detail page for a single return (seller panel).
     */
    public function show(int $id): View
    {
        $orderReturn = OrderItemReturn::findOrFail($id);
        $this->authorize('view', $orderReturn);

        $data = $this->returnRequestService->getDetail($orderReturn, 'seller');

        return view('seller.returns.show', $data);
    }

    /**
     * Approve / reject decision endpoint (seller panel).
     */
    public function decision(DecideReturnRequest $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $user = Auth::user();
        $seller = $user->seller();

        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), [], 404);
        }

        $orderReturn = OrderItemReturn::where('id', $id)
            ->where('seller_id', $seller->id)
            ->first();

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
                actorType: OrderReturnActorEnum::SELLER,
                comment: $request->validated('seller_comment'),
            );
        } catch (RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            Log::error('Return decision failed', ['return_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), [], 500);
        }

        $message = $action === 'approve'
            ? __('messages.return_approved_successfully')
            : __('messages.return_rejected_successfully');

        return ApiResponseType::sendJsonResponse(true, $message, $this->formatReturnRow($orderReturn), 200);
    }

    /**
     * Store receipt confirmation endpoint (when item is delivered to seller).
     */
    public function storeConfirm(StoreConfirmReturnRequest $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        // ==========================================================
        // Return Dispute System
        // Phase 3
        // Seller Store Confirmation Controller Integration
        // ==========================================================
        $user = Auth::user();
        $seller = $user?->seller();

        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), [], 404);
        }

        $orderReturn = OrderItemReturn::where('id', $id)
            ->where('seller_id', $seller->id)
            ->first();

        if (! $orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), [], 404);
        }

        $this->authorize('decide', $orderReturn);

        $action = $request->validated('action');
        $options = [
            'remarks' => $request->validated('remarks'),
            'dispute_reason' => $request->validated('dispute_reason'),
            'inspection_photos' => $request->file('inspection_photos'),
        ];

        try {
            $orderReturn = $this->returnRequestService->confirmStoreReceipt(
                return: $orderReturn,
                action: $action,
                options: $options,
                actor: $user
            );
        } catch (RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            Log::error('Return store confirmation failed', ['return_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), ['error' => $e->getMessage()], 500);
        }

        $message = $action === 'approve'
            ? (__('messages.return_receipt_confirmed_successfully') ?? 'Return item receipt confirmed and refund processed.')
            : 'Return dispute escalated to admin successfully.';

        return ApiResponseType::sendJsonResponse(true, $message, $this->formatReturnRow($orderReturn), 200);
    }

    /**
     * Apply filter params from the datatable request to the query.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($status = $request->get('status')) {
            if ($status === 'awaiting_inspection') {
                $query->where('return_status', OrderItemReturnStatusEnum::RECEIVED_BY_SELLER());
            } elseif ($status === 'inspection_due_today') {
                $slaHours = (int) (app(SettingService::class)->getSettingValues('seller')['return_seller_inspection_sla_hours'] ?? 48);
                $query->where('return_status', OrderItemReturnStatusEnum::RECEIVED_BY_SELLER())
                    ->whereNotNull('received_at')
                    ->where('received_at', '>', Carbon::now()->subHours($slaHours))
                    ->where('received_at', '<=', Carbon::now()->subHours($slaHours - 12));
            } elseif ($status === 'inspection_expired') {
                $slaHours = (int) (app(SettingService::class)->getSettingValues('seller')['return_seller_inspection_sla_hours'] ?? 48);
                $query->where('return_status', OrderItemReturnStatusEnum::RECEIVED_BY_SELLER())
                    ->whereNotNull('received_at')
                    ->where('received_at', '<=', Carbon::now()->subHours($slaHours));
            } elseif ($status === 'under_admin_review') {
                $query->where('return_status', OrderItemReturnStatusEnum::DISPUTED());
            } elseif ($status === 'completed') {
                $query->whereIn('return_status', [OrderItemReturnStatusEnum::COMPLETED(), OrderItemReturnStatusEnum::REFUND_PROCESSED(), 'completed']);
            } else {
                $query->where('return_status', $status);
            }
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
            ? e($r->orderItem->product->title)
            : __('labels.n_a');

        $variantTitle = $r->orderItem && $r->orderItem->variant ? e($r->orderItem->variant->title) : '';
        $images = '';
        if (! empty($r->images)) {
            foreach ($r->images as $image) {
                $images .= view('partials.image', ['image' => $image])->render();
            }
        }

        $addonsHtml = $this->renderReturnAddons($r->orderItem);

        $detailUrl = route('seller.returns.show', $r->id);
        $linkedTitle = "<a href='{$detailUrl}' class='link-primary fw-medium'>{$productTitle}</a>";

        $orderDetails = "<div>
                <p class='m-0'>{$linkedTitle} ".($variantTitle ? "<small class='text-muted'>{$variantTitle}</small>" : '')."</p>
                <small class='text-muted'>".__('labels.order_id')." - {$r->order_id}</small> |
                <small class='text-muted'>".__('labels.order_item_id')." - {$r->order_item_id}</small>
                {$addonsHtml}
                <div class='mt-2 d-flex align-items-center gap-1'>{$images}</div>
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

        $requiresAction = is_null($r->seller_approved_at) && $statusVal === (string) OrderItemReturnStatusEnum::REQUESTED();
        $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="'.e(__('labels.action_required') ?? 'Action Required').'"></span>' : '';
        $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">'.$actionDot.'<span class="text-muted">#'.$r->id.'</span></div>' : "<span class='text-muted'>#{$r->id}</span>";

        return [
            'id' => $formattedId,
            'order_date' => $orderDateHtml,
            'order_details' => $orderDetails,
            'refund_amount' => $refundAmount,
            'return_status' => $returnStatus,
            'pipeline' => $pipeline,
            'age' => $age,
            'actions' => $actions,
        ];
    }

    /**
     * Render the actions cell — Approve/Reject quick buttons while the request
     * is pending, or store confirm/reject when item is received at store.
     */
    private function renderActions(OrderItemReturn $r): string
    {
        $detailUrl = route('seller.returns.show', $r->id);
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
                <button class='btn btn-success me-1' data-bs-toggle='modal' data-bs-target='#acceptModel' data-id='{$r->id}'>"
                .__('labels.approve')."</button>
                <button class='btn btn-outline-danger' data-bs-toggle='modal' data-bs-target='#rejectModel' data-id='{$r->id}'>"
                .__('labels.reject').'</button>
            </div>';
        }

        // CodeCanyon release flow: store confirm/reject is hidden; rider delivery now auto-refunds.
        $isPendingStoreReceipt = false;

        if ($isPendingStoreReceipt) {
            return "<div class='d-flex align-items-center'>
                {$eyeBtn}
                <button class='btn btn-success me-1' data-bs-toggle='modal' data-bs-target='#storeConfirmModel' data-id='{$r->id}'>"
                .(__('labels.confirm_receipt') ?? 'Confirm Receipt')."</button>
                <button class='btn btn-outline-danger' data-bs-toggle='modal' data-bs-target='#storeRejectModel' data-id='{$r->id}'>"
                .(__('labels.reject_receipt') ?? 'Reject Item').'</button>
            </div>';
        }

        return "<div class='d-flex align-items-center'>{$eyeBtn}</div>";
    }

    /**
     * Age badge — red when a return has been REQUESTED for more than 24h
     * with no seller action. Helps sellers see what needs attention now.
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
     * datatable. Empty string when the order item has no addon snapshot —
     * keeps the row tidy for product-only returns.
     *
     * Falls back to a lazy query when the `addons` relation isn't eager-
     * loaded (e.g. when `formatReturnRow` is called after `decision()` with
     * a freshly loaded model — the decision path does not eager-load).
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
}
