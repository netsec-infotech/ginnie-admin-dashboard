<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderReturnActorEnum;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Requests\Returns\DecideReturnRequest;
use App\Http\Requests\Returns\StoreConfirmReturnRequest;
use App\Http\Resources\Seller\Returns\SellerOrderReturnDetailResource;
use App\Http\Resources\Seller\Returns\SellerOrderReturnResource;
use App\Models\OrderItemReturn;
use App\Services\ReturnRequestService;
use App\Support\DemoMode;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

#[Group('Seller Order Returns')]
class SellerOrderReturnApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ReturnRequestService $returnRequestService
    ) {}

    /**
     * List order returns for the authenticated seller.
     */
    #[QueryParameter('status', description: 'Filter by return status code.', type: 'string', example: 'requested')]
    #[QueryParameter('status_group', description: 'Filter by status group: under_review, approved, refund_processed, disputed.', type: 'string', example: 'under_review')]
    #[QueryParameter('search', description: 'Search term for order ID, customer, item title, or reason.', type: 'string', example: 'ORD-2026')]
    #[QueryParameter('date_from', description: 'Filter created after date (YYYY-MM-DD).', type: 'string', example: '2026-01-01')]
    #[QueryParameter('date_to', description: 'Filter created before date (YYYY-MM-DD).', type: 'string', example: '2026-12-31')]
    #[QueryParameter('per_page', description: 'Items per page (max 50).', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $seller = $user?->seller();

        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), null, 404);
        }

        $sellerId = $seller->id;

        // Compute summary insights
        $baseCountQuery = OrderItemReturn::where('seller_id', $sellerId)
            ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED());

        $insights = [
            'total' => (clone $baseCountQuery)->count(),
            'under_review' => ReturnRequestController::applyInsightStatusGroup((clone $baseCountQuery), 'under_review')->count(),
            'approved' => ReturnRequestController::applyInsightStatusGroup((clone $baseCountQuery), 'approved')->count(),
            'refund_processed' => ReturnRequestController::applyInsightStatusGroup((clone $baseCountQuery), 'refund_processed')->count(),
            'disputed' => (clone $baseCountQuery)->where('return_status', OrderItemReturnStatusEnum::DISPUTED())->count(),
        ];

        // Query listing
        $query = OrderItemReturn::with([
            'order',
            'orderItem.product',
            'orderItem.variant',
            'orderItem.addons.addonGroup',
            'orderItem.addons.addonItem',
            'store',
            'user',
            'decidedBy',
            'deliveryBoy.user',
        ])
            ->where('seller_id', $sellerId)
            ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED());

        // Apply Status & Status Group filters
        if ($status = $request->input('status')) {
            if ($status === 'under_admin_review' || $status === 'disputed') {
                $query->where('return_status', OrderItemReturnStatusEnum::DISPUTED());
            } elseif ($status === 'completed') {
                $query->whereIn('return_status', [OrderItemReturnStatusEnum::COMPLETED(), OrderItemReturnStatusEnum::REFUND_PROCESSED(), 'completed']);
            } else {
                $query->where('return_status', $status);
            }
        } elseif ($statusGroup = $request->input('status_group')) {
            if ($statusGroup === 'disputed') {
                $query->where('return_status', OrderItemReturnStatusEnum::DISPUTED());
            } else {
                ReturnRequestController::applyInsightStatusGroup($query, $statusGroup);
            }
        }

        // Date filters
        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Refund amount filters
        if ($min = $request->input('refund_min')) {
            $query->where('refund_amount', '>=', (float) $min);
        }
        if ($max = $request->input('refund_max')) {
            $query->where('refund_amount', '<=', (float) $max);
        }

        // Search
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('seller_comment', 'like', "%{$search}%")
                    ->orWhere('dispute_reason', 'like', "%{$search}%")
                    ->orWhereHas('order', fn($qo) => $qo->where('order_id', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn($qu) => $qu->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"))
                    ->orWhereHas('orderItem', fn($qi) => $qi->where('product_name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $paginated = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.returns_retrieved_successfully') ?? 'Order returns retrieved successfully.',
            [
                'insights' => $insights,
                'current_page' => $paginated->currentPage(),
                'data' => SellerOrderReturnResource::collection($paginated->items()),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ]
        );
    }

    /**
     * Get enum definitions and dispute options for seller returns.
     */
    public function enums(): JsonResponse
    {
        $statuses = array_map(fn($case) => [
            'value' => $case->value,
            'label' => ucfirst(str_replace('_', ' ', strtolower($case->value))),
        ], OrderItemReturnStatusEnum::cases());

        $disputeReasons = [
            'Wrong Product Returned',
            'Different Product / Substituted',
            'Used or Damaged by Customer',
            'Empty Box',
            'Missing Items / Incomplete Return',
            'Tampered Package',
            'Other',
        ];

        return ApiResponseType::sendJsonResponse(true, 'Enums retrieved successfully.', [
            'statuses' => $statuses,
            'dispute_reasons' => $disputeReasons,
            'decision_actions' => ['approve', 'reject'],
            'store_confirm_actions' => ['approve', 'escalate'],
        ]);
    }

    /**
     * Get single return request detail.
     */
    #[PathParameter('id', description: 'Order Item Return ID', type: 'integer', example: 1)]
    public function show(Request $request, int $id): JsonResponse
    {
        $seller = $request->user()?->seller();
        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), null, 404);
        }

        $orderReturn = OrderItemReturn::where('id', $id)
            ->where('seller_id', $seller->id)
            ->first();

        if (! $orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), null, 404);
        }

        $this->authorize('view', $orderReturn);

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.return_details_retrieved_successfully') ?? 'Return details retrieved successfully.',
            new SellerOrderReturnDetailResource($orderReturn)
        );
    }

    /**
     * Initial return review decision: Approve or Reject.
     */
    #[PathParameter('id', description: 'Order Item Return ID', type: 'integer', example: 1)]
    public function decision(DecideReturnRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (DemoMode::isDemoUser($user)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', null, 403);
        }

        $seller = $user?->seller();
        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), null, 404);
        }

        $orderReturn = OrderItemReturn::where('id', $id)
            ->where('seller_id', $seller->id)
            ->first();

        if (! $orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), null, 404);
        }

        $this->authorize('decide', $orderReturn);

        $action = $request->validated('action');

        try {
            $updatedReturn = $this->returnRequestService->decide(
                return: $orderReturn,
                action: $action,
                actor: $user,
                actorType: OrderReturnActorEnum::SELLER,
                comment: $request->validated('seller_comment'),
            );
        } catch (RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Seller API Return decision failed', ['return_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), null, 500);
        }

        $message = $action === 'approve'
            ? __('messages.return_approved_successfully')
            : __('messages.return_rejected_successfully');

        return ApiResponseType::sendJsonResponse(
            true,
            $message,
            new SellerOrderReturnDetailResource($updatedReturn)
        );
    }

    /**
     * Store physical receipt confirmation: Accept return or Escalate Dispute to Admin.
     */
    #[PathParameter('id', description: 'Order Item Return ID', type: 'integer', example: 1)]
    public function storeConfirm(StoreConfirmReturnRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (DemoMode::isDemoUser($user)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', null, 403);
        }

        $seller = $user?->seller();
        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), null, 404);
        }

        $orderReturn = OrderItemReturn::where('id', $id)
            ->where('seller_id', $seller->id)
            ->first();

        if (! $orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), null, 404);
        }

        $this->authorize('decide', $orderReturn);

        $action = $request->validated('action');
        $options = [
            'remarks' => $request->validated('remarks'),
            'dispute_reason' => $request->validated('dispute_reason'),
            'inspection_photos' => $request->file('inspection_photos'),
        ];

        try {
            $updatedReturn = $this->returnRequestService->confirmStoreReceipt(
                return: $orderReturn,
                action: $action,
                options: $options,
                actor: $user
            );
        } catch (RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Seller API Return store confirmation failed', ['return_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), null, 500);
        }

        $message = $action === 'approve'
            ? __('messages.return_confirmed_successfully') ?? 'Return receipt confirmed and refund processed.'
            : __('messages.return_escalated_successfully') ?? 'Return escalated to admin for dispute resolution.';

        return ApiResponseType::sendJsonResponse(
            true,
            $message,
            new SellerOrderReturnDetailResource($updatedReturn)
        );
    }
}
