<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Events\DeliveryBoy\DeliveryBoyVerificationStatusUpdated;
use App\Http\Requests\DeliveryBoy\BlockDeliveryBoyRequest;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryFeedback;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\DeliveryBoyService;
use App\Services\DeliveryZoneService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class DeliveryBoyController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $viewPermission = false;

    protected bool $blockPermission = false;

    public function __construct(protected DeliveryBoyService $deliveryBoyService)
    {
        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::DELIVERY_BOY_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::DELIVERY_BOY_DELETE());
            $this->viewPermission = $this->hasPermission(AdminPermissionEnum::DELIVERY_BOY_VIEW());
            $this->blockPermission = $this->hasPermission(AdminPermissionEnum::DELIVERY_BOY_BLOCK());
        }
    }

    /**
     * Display a listing of the delivery boys.
     */
    public function index(): View
    {
        try {
            $this->authorize('viewAny', DeliveryBoy::class);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        }

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'full_name', 'name' => 'full_name', 'title' => __('labels.full_name')],
            ['data' => 'email', 'name' => 'email', 'title' => __('labels.email')],
            ['data' => 'mobile', 'name' => 'mobile', 'title' => __('labels.mobile')],
            ['data' => 'delivery_zone', 'name' => 'delivery_zone', 'title' => __('labels.delivery_zone')],
            ['data' => 'vehicle_type', 'name' => 'vehicle_type', 'title' => __('labels.vehicle_type')],
            ['data' => 'verification_status', 'name' => 'verification_status', 'title' => __('labels.verification_status')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $viewPermission = $this->viewPermission;

        $verificationStatuses = DeliveryBoyVerificationStatusEnum::values();

        $deliveryBoyInsights = [
            'total' => DeliveryBoy::count(),
            'verified' => DeliveryBoy::where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())->count(),
            'active' => DeliveryBoy::where('status', 'active')->count(),
            'pending' => DeliveryBoy::where('verification_status', DeliveryBoyVerificationStatusEnum::PENDING())->count(),
        ];

        return view($this->panelView('delivery_boys.index'), compact(
            'columns',
            'editPermission',
            'deletePermission',
            'viewPermission',
            'verificationStatuses',
            'deliveryBoyInsights'
        ));
    }

    /**
     * Display the specified delivery boy.
     */
    public function show($id): View
    {
        $deliveryBoy = DeliveryBoy::with(['user', 'deliveryZone', 'location', 'wallet', 'referralAsReferred.referrer'])->findOrFail($id);
        $this->authorize('view', $deliveryBoy);

        $verificationStatuses = DeliveryBoyVerificationStatusEnum::values();
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $blockPermission = $this->blockPermission;

        $assignmentStats = $this->deliveryBoyService->getAssignmentStats($deliveryBoy);
        $earningsSummary = $this->deliveryBoyService->getEarningsSummary($deliveryBoy);
        $reviewData = DeliveryFeedback::getDeliveryFeedbackStatistics($deliveryBoy->id);

        $referralsCount = $deliveryBoy->referralAsReferrer()->count();
        $referralEarningsTotal = $deliveryBoy->referralEarnings()->sum('bonus_amount');

        $assignmentColumns = [
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'base_fee', 'name' => 'base_fee', 'title' => __('labels.base_fee'), 'label' => __('labels.base_fee')],
            ['data' => 'total_earnings', 'name' => 'total_earnings', 'title' => __('labels.total_earnings'), 'label' => __('labels.total_earnings')],
            ['data' => 'payment_status', 'name' => 'payment_status', 'title' => __('labels.payment_status'), 'label' => __('labels.payment_status')],
            ['data' => 'cod_collected', 'name' => 'cod_collected', 'title' => __('labels.cod_collected'), 'label' => __('labels.cod_collected')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $walletColumns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id'), 'label' => __('labels.id')],
            ['data' => 'transaction_type', 'name' => 'transaction_type', 'title' => __('labels.type'), 'label' => __('labels.type')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount'), 'label' => __('labels.amount')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'label' => __('labels.payment_method')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description'), 'orderable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $feedbackColumns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id'), 'label' => __('labels.id')],
            ['data' => 'customer', 'name' => 'customer', 'title' => __('labels.customer'), 'label' => __('labels.customer'), 'orderable' => false, 'searchable' => false],
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order'), 'orderable' => false, 'searchable' => false],
            ['data' => 'rating', 'name' => 'rating', 'title' => __('labels.rating'), 'label' => __('labels.rating')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description'), 'orderable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        return view($this->panelView('delivery_boys.view'), compact(
            'deliveryBoy',
            'verificationStatuses',
            'editPermission',
            'deletePermission',
            'blockPermission',
            'assignmentStats',
            'earningsSummary',
            'assignmentColumns',
            'walletColumns',
            'reviewData',
            'referralsCount',
            'referralEarningsTotal',
            'feedbackColumns'
        ));
    }

    /**
     * Server-side datatable for delivery boy assignments.
     */
    public function assignmentsDatatable(Request $request, $id): JsonResponse
    {
        $deliveryBoy = DeliveryBoy::findOrFail($id);
        $this->authorize('view', $deliveryBoy);

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $query = DeliveryBoyAssignment::where('delivery_boy_id', $id)->with(['order:id,uuid']);
        $totalRecords = (clone $query)->count();

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('status', 'like', "%{$searchValue}%")
                    ->orWhere('payment_status', 'like', "%{$searchValue}%")
                    ->orWhereHas('order', function ($orderQuery) use ($searchValue) {
                        $orderQuery->where('uuid', 'like', "%{$searchValue}%")
                            ->orWhere('id', 'like', "%{$searchValue}%");
                    });
            });
        }

        $filteredRecords = $query->count();
        $assignments = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

        $data = $assignments->map(function ($assignment) {
            $statusClass = match ($assignment->status) {
                'completed' => 'bg-success-lt',
                'in_progress' => 'bg-info-lt',
                'assigned' => 'bg-primary-lt',
                'canceled' => 'bg-warning-lt',
                'dropped' => 'bg-danger-lt',
                default => 'bg-secondary-lt',
            };

            $paymentStatus = $assignment->payment_status
                ? '<span class="badge '.($assignment->payment_status === 'paid' ? 'bg-success-lt' : 'bg-warning-lt').'">'.e(ucfirst($assignment->payment_status)).'</span>'
                : '<span class="text-muted">-</span>';

            return [
                'order' => $assignment->order
                    ? '<a href="'.route('admin.orders.show', $assignment->order_id).'">#'.e($assignment->order->uuid ?? $assignment->order_id).'</a>'
                    : '#'.e($assignment->order_id),
                'status' => '<span class="badge '.$statusClass.'">'.e(ucfirst(str_replace('_', ' ', $assignment->status))).'</span>',
                'base_fee' => getCurrencySymbol().number_format((float) ($assignment->base_fee ?? 0), 2),
                'total_earnings' => getCurrencySymbol().number_format((float) ($assignment->total_earnings ?? 0), 2),
                'payment_status' => $paymentStatus,
                'cod_collected' => getCurrencySymbol().number_format((float) ($assignment->cod_cash_collected ?? 0), 2),
                'created_at' => $assignment->created_at?->format('d M Y, H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Server-side datatable for delivery boy wallet transactions.
     */
    public function walletDatatable(Request $request, $id): JsonResponse
    {
        $deliveryBoy = DeliveryBoy::with('wallet')->findOrFail($id);
        $this->authorize('view', $deliveryBoy);

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $walletId = $deliveryBoy->wallet?->id;
        if (! $walletId) {
            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $query = WalletTransaction::where('wallet_id', $walletId);
        $totalRecords = (clone $query)->count();

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('transaction_type', 'like', "%{$searchValue}%")
                    ->orWhere('payment_method', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%");
            });
        }

        $filteredRecords = $query->count();
        $transactions = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

        $data = $transactions->map(function ($transaction) {
            $statusClass = $transaction->status === 'completed' ? 'bg-success-lt' : 'bg-warning-lt';

            return [
                'id' => $transaction->id,
                'transaction_type' => '<span class="badge bg-secondary-lt">'.e(ucfirst(str_replace('_', ' ', $transaction->transaction_type ?? ''))).'</span>',
                'amount' => getCurrencySymbol().number_format((float) $transaction->amount, 2),
                'payment_method' => e(ucfirst($transaction->payment_method ?? '-')),
                'status' => '<span class="badge '.$statusClass.'">'.e(ucfirst($transaction->status ?? 'pending')).'</span>',
                'description' => e(\Str::limit($transaction->description, 150)),
                'created_at' => $transaction->created_at?->format('d M Y, H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Server-side datatable for delivery boy feedback.
     */
    public function feedbackDatatable(Request $request, $id): JsonResponse
    {
        $deliveryBoy = DeliveryBoy::findOrFail($id);
        $this->authorize('view', $deliveryBoy);

        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $sortableColumns = ['id', 'rating', 'title', 'created_at'];
        $orderColumn = $sortableColumns[$orderColumnIndex] ?? 'created_at';

        $query = DeliveryFeedback::where('delivery_boy_id', $id)->with(['user', 'order']);

        $totalRecords = DeliveryFeedback::where('delivery_boy_id', $id)->count();

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($uq) use ($searchValue) {
                        $uq->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%")
                            ->orWhere('mobile', 'like', "%{$searchValue}%");
                    });
            });
        }

        $filteredRecords = $query->count();

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($feedback) {
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    $stars .= $i <= $feedback->rating
                        ? '<i class="ti ti-star-filled text-warning"></i>'
                        : '<i class="ti ti-star text-muted"></i>';
                }

                return [
                    'id' => $feedback->id,
                    'customer' => '<div><strong>'.e($feedback->user?->name ?? 'N/A').'</strong>'
                        .($feedback->user?->email ? '<br><small class="text-muted">'.e($feedback->user->email).'</small>' : '')
                        .($feedback->user?->mobile ? '<br><small class="text-muted">'.e($feedback->user->mobile).'</small>' : '')
                        .'</div>',
                    'order' => $feedback->order
                        ? '<a href="'.route('admin.orders.show', $feedback->order_id).'">#'.($feedback->order->uuid ?? $feedback->order_id).'</a>'
                        : '-',
                    'rating' => '<div class="d-flex align-items-center">'.$stars.'<span class="ms-2 fw-bold">'.$feedback->rating.'</span></div>',
                    'title' => e($feedback->title ?? '-'),
                    'description' => e(\Str::limit($feedback->description ?? '-', 60)),
                    'created_at' => $feedback->created_at?->format('d M Y, H:i') ?? '-',
                ];
            });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Update the verification status of the delivery boy.
     */
    public function updateVerificationStatus(Request $request, $id): JsonResponse
    {
        try {
            $deliveryBoy = DeliveryBoy::findOrFail($id);
            $this->authorize('update', $deliveryBoy);

            $validated = $request->validate([
                'verification_status' => ['required', new Enum(DeliveryBoyVerificationStatusEnum::class)],
                'verification_remark' => 'nullable|string|max:1000',
            ]);

            // $previousStatus = $deliveryBoy->verification_status->value;
            // if ($previousStatus === DeliveryBoyVerificationStatusEnum::VERIFIED()) {
            //     return ApiResponseType::sendJsonResponse(
            //         success: false,
            //         message: __('labels.once_delivery_boy_verified_cant_be_changed'),
            //         data: $deliveryBoy
            //     );
            // }

            $deliveryBoy->update([
                'verification_status' => $validated['verification_status'],
                'verification_remark' => $validated['verification_remark'] ?? null,
            ]);

            // Dispatch the event
            event(new DeliveryBoyVerificationStatusUpdated(
                $deliveryBoy,
                auth()->user(),
                // $previousStatus,
                $validated['verification_status'],
                $validated['verification_remark'] ?? null
            ));

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.verification_status_updated_successfully',
                data: $deliveryBoy
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: []
            );
        }
    }

    /**
     * Remove the specified delivery boy from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $deliveryBoy = DeliveryBoy::findOrFail($id);
            $this->authorize('delete', $deliveryBoy);
            DB::beginTransaction();
            $deliveryBoy->delete();
            $deliveryBoy->user?->delete();
            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.delivery_boy_deleted_successfully',
                data: []
            );
        } catch (AuthorizationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: []
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.error_occurred',
                data: []
            );
        }
    }

    /**
     * Get delivery boys for datatable
     */
    public function getDeliveryBoys(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', DeliveryBoy::class);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied');
        }

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';
        $verificationStatus = $request->get('verification_status');
        $status = $request->get('status');
        $deliveryBoyId = $request->get('delivery_boy_id');

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'full_name', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = DeliveryBoy::query()->with(['user', 'deliveryZone']);

        $totalRecords = DeliveryBoy::count();

        if (! empty($deliveryBoyId)) {
            $query->where('id', $deliveryBoyId);
        }
        // Filters
        if ($verificationStatus !== null) {
            $query->where('verification_status', $verificationStatus);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        // Search filter
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('full_name', 'like', "%{$searchValue}%")
                    ->orWhere('driver_license_number', 'like', "%{$searchValue}%")
                    ->orWhere('vehicle_type', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($userQuery) use ($searchValue) {
                        $userQuery->where('email', 'like', "%{$searchValue}%")
                            ->orWhere('mobile', 'like', "%{$searchValue}%");
                    });
            });
        }
        $filteredRecords = $query->count();

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($deliveryBoy) {
                $verStatus = $deliveryBoy->verification_status?->value ?? (string) $deliveryBoy->verification_status;
                $statusVal = $deliveryBoy->status?->value ?? (string) $deliveryBoy->status;

                $requiresAction = in_array($verStatus, ['pending', 'not_approved'], true);
                $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="'.e(__('labels.action_required') ?? 'Action Required').'"></span>' : '';
                $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">'.$actionDot.'<span>'.e($deliveryBoy->id).'</span></div>' : $deliveryBoy->id;

                return [
                    'id' => $formattedId,
                    'full_name' => '<a href="'.route('admin.delivery-boys.show', $deliveryBoy->id).'"  title="'.__('labels.view').'">'.($deliveryBoy->full_name ?? ' - ').'</a>',
                    'email' => $deliveryBoy->user->email ?? '',
                    'mobile' => $deliveryBoy->user->mobile ?? '',
                    'delivery_zone' => $deliveryBoy->deliveryZone->name ?? '',
                    'vehicle_type' => $deliveryBoy->vehicle_type ?? '',
                    'verification_status' => view('partials.status', [
                        'status' => $deliveryBoy->verification_status->value,
                    ])->render(),
                    'status' => view('partials.status', [
                        'status' => $deliveryBoy->status,
                    ])->render(),
                    'created_at' => $deliveryBoy->created_at->format('Y-m-d'),
                    'action' => view('partials.actions', [
                        'modelName' => 'delivery-boy',
                        'id' => $deliveryBoy->id,
                        'title' => $deliveryBoy->full_name,
                        'mode' => 'page_view',
                        'route' => route('admin.delivery-boys.show', $deliveryBoy->id),
                        'editPermission' => $this->editPermission,
                        'deletePermission' => $this->deletePermission,
                    ])->render(),
                ];
            });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * TomSelect server-side search for delivery boys.
     *
     * Query params:
     *   - search             — free-text against name/email/mobile/full_name
     *   - available=1        — only verified + active + not blocked riders
     *   - delivery_zone_id   — restrict to a specific zone (used by the admin
     *                          Reassign Rider modal so we don't suggest a
     *                          rider outside the order's delivery zone)
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('search', '');
        $availableOnly = (bool) $request->boolean('available');
        $zoneId = $request->input('delivery_zone_id');

        $deliveryBoys = DeliveryBoy::query()
            ->where(function ($q) use ($query) {
                $q->whereHas('user', function ($sub) use ($query) {
                    $sub->where(function ($s) use ($query) {
                        $s->where('name', 'like', "%$query%")
                            ->orWhere('email', 'like', "%$query%")
                            ->orWhere('mobile', 'like', "%$query%");
                    });
                })
                    ->orWhere('full_name', 'like', "%$query%");
            })
            ->when($availableOnly, function ($q) {
                $q->where('status', 'active')
                    ->where('is_blocked', false)
                    ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED());
            })
            ->when(! empty($zoneId), function ($q) use ($zoneId) {
                $q->where('delivery_zone_id', $zoneId);
            })
            ->with('user')
            ->limit(20)
            ->get();

        // TomSelect-friendly shape. The text includes mobile + zone hints when
        // available so admins can pick the right rider without leaving the modal.
        $results = $deliveryBoys->map(function ($deliveryBoy) {
            $bits = [$deliveryBoy->full_name];
            if (! empty($deliveryBoy->user?->email)) {
                $bits[] = $deliveryBoy->user->email;
            }
            if (! empty($deliveryBoy->user?->mobile)) {
                $bits[] = $deliveryBoy->user->mobile;
            }

            return [
                'id' => $deliveryBoy->id,
                'value' => $deliveryBoy->id,
                'text' => implode(' · ', array_filter($bits)),
            ];
        });

        return response()->json($results);
    }

    /**
     * Block a delivery boy. Admin-only. Captures the reason and admin attribution
     * on the row, revokes any active mobile session, and forces the rider
     * inactive so they stop appearing in the rider-pool queries.
     */
    public function block(BlockDeliveryBoyRequest $request, int $id): JsonResponse
    {
        try {
            if (! $this->blockPermission) {
                return $this->unauthorizedResponse();
            }

            $deliveryBoy = DeliveryBoy::findOrFail($id);
            $this->authorize('update', $deliveryBoy);

            $result = $this->deliveryBoyService->block(
                deliveryBoy: $deliveryBoy,
                reason: $request->validated('reason'),
                adminUserId: (int) auth()->id(),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Failed to block delivery boy', [
                'delivery_boy_id' => $id,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Unblock a previously blocked delivery boy. Admin-only.
     */
    public function unblock(int $id): JsonResponse
    {
        try {
            if (! $this->blockPermission) {
                return $this->unauthorizedResponse();
            }

            $deliveryBoy = DeliveryBoy::findOrFail($id);
            $this->authorize('update', $deliveryBoy);

            $result = $this->deliveryBoyService->unblock($deliveryBoy);

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Failed to unblock delivery boy', [
                'delivery_boy_id' => $id,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Full-page live tracking view for all delivery boys.
     */
    public function liveTracking(): View
    {
        try {
            $this->authorize('viewAny', DeliveryBoy::class);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        }

        $webSetting = Setting::find(SettingTypeEnum::WEB());
        $defaultLatitude = $webSetting->value['defaultLatitude'] ?? null;
        $defaultLongitude = $webSetting->value['defaultLongitude'] ?? null;

        return view($this->panelView('delivery_boys.live-tracking.index'), compact(
            'defaultLatitude',
            'defaultLongitude',
        ));
    }

    /**
     * JSON endpoint — riders within the given map bounds.
     */
    public function liveTrackingData(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', DeliveryBoy::class);

            $neLat = (float) $request->query('ne_lat');
            $neLng = (float) $request->query('ne_lng');
            $swLat = (float) $request->query('sw_lat');
            $swLng = (float) $request->query('sw_lng');

            $result = DeliveryZoneService::getRidersByBounds($neLat, $neLng, $swLat, $swLng);

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', []);
        } catch (\Throwable $e) {
            Log::error('Rider live tracking error', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null);
        }
    }
}
