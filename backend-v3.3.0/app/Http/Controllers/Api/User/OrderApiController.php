<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\DateRangeFilterEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Order\CreateItemReturnRequest;
use App\Http\Requests\User\Order\CreateOrderRequest;
use App\Http\Requests\User\Order\PostOrderTipRequest;
use App\Http\Resources\User\OrderPaymentResource;
use App\Http\Resources\User\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\PaymentStatusEnum;
use App\Services\CartService;
use App\Services\OrderService;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('Orders')]
class OrderApiController extends Controller
{
    protected OrderService $orderService;

    protected CartService $cartService;

    protected string $dataFilter;

    public function __construct(OrderService $orderService, CartService $cartService)
    {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
        $this->dataFilter = implode(',', DateRangeFilterEnum::values());
    }

    /**
     * Create a new order
     *
     * Creates a new order from the user's cart with the provided payment and address information.
     */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }
        $result = $this->orderService->createOrder($user, $request->validated());

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] && $result['data'] instanceof Order ? new OrderResource($result['data']) : $result['data']
        );
    }

    /**
     * Get order details
     *
     * Retrieves the details of a specific order by its slug.
     */
    public function getOrder(string $orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->getOrder($user, $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    /**
     * Get user's orders
     *
     * Retrieves all orders for the authenticated user.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of Orders Per Page', type: 'int', default: 1, example: 1)]
    #[QueryParameter('date_range', description: 'Filter orders by date range', type: 'string', example: 'last_30_minutes,last_1_hour,last_5_hours,last_1_day,last_7_days,last_30_days,last_365_days')]
    #[QueryParameter('status', description: 'Filter orders by order-level status. Item-level transient states (delivery_failed, returning_to_store) appear on individual items inside an order rather than at the order level.', type: 'string', example: 'pending,awaiting_store_response,partially_accepted,rejected_by_seller,accepted_by_seller,ready_for_pickup,assigned,preparing,collected,out_for_delivery,delivered,cancelled,failed')]
    #[QueryParameter('order_type', description: 'Filter by order type (regular, pickup, pos)', type: 'string', example: 'pickup')]
    public function getUserOrders(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 15);
        $dateRange = $request->input('date_range');
        $status = $request->input('status');
        $orderType = $request->input('order_type');

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $filters = [
            'date_range' => $dateRange,
            'status' => $status,
            'order_type' => $orderType,
            'is_scheduled' => $request->boolean('is_scheduled') || $request->input('is_scheduled') == '1' || $request->input('scheduled') == '1' || $request->input('future_scheduled') == '1',
        ];
        $result = $this->orderService->getUserOrders(
            user: $user,
            perPage: $perPage,
            filters: $filters
        );

        if (!($result['success'] ?? false) || ! ($result['data'] instanceof LengthAwarePaginator)) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $result['message'] ?? __('labels.something_went_wrong'),
                data: []
            );
        }

        $orders = $result['data'];
        $orders->getCollection()->transform(fn($order) => new OrderResource($order));

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'data' => $orders->items(),
            ]
        );
    }

    /**
     * Get Order Delivery Boy Location
     */
    public function getOrderDeliveryBoyLocation($orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        if (! $orderSlug) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.order_slug_required'),
                []
            );
        }

        $result = $this->orderService->getOrderDeliveryBoyLocation(user: $user, orderSlug: $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

    /**
     * Add or update rider tip for an order (post-placement)
     */
    public function addOrderTip(PostOrderTipRequest $request, string $orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(false, __('labels.user_not_authenticated'), []);
        }

        $order = Order::where(function ($q) use ($orderSlug) {
            $q->where('slug', $orderSlug)
                ->orWhere('uuid', $orderSlug)
                ->orWhere('id', $orderSlug);
        })->first();

        if (! $order || $order->user_id !== $user->id) {
            return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', []);
        }

        $transactionId = $request->validated('transaction_id')
            ?? $request->input('payment_id')
            ?? $request->input('razorpay_payment_id');

        $result = $this->orderService->processPostOrderTip(
            order: $order,
            tipAmount: (float) $request->validated('rider_tip_amount'),
            paymentMethod: (string) $request->validated('payment_method'),
            transactionId: $transactionId ? (string) $transactionId : null
        );

        $freshOrder = $order->fresh();
        // Merge result data (e.g. gateway payment links like invoice_url, status, transaction_id) so online payment gateways like Xendit return initiation details to frontend
        $responseData = array_merge($result['data'] ?? [], [
            'order' => new OrderResource($freshOrder),
            'added_tip' => $result['data']['added_tip'] ?? (float) $request->validated('rider_tip_amount'),
            'total_tip' => $result['data']['total_tip'] ?? (float) $freshOrder->rider_tip_amount,
            'pending_tip_amount' => ($result['data']['status'] ?? null) === 'pending' ? (float) $request->validated('rider_tip_amount') : 0.0,
            'status' => $result['data']['status'] ?? 'completed',
            'transaction_id' => $result['data']['transaction_id'] ?? $transactionId,
        ]);

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['success'] ? $responseData : $result['data']
        );
    }

    /**
     * Cancel an order item
     *
     * Cancels a specific order item if it meets the cancellation criteria.
     */
    public function cancelOrderItem(int $orderItemId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->cancelOrderItem($user, $orderItemId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

    /**
     * Get user's payment transactions
     *
     * Retrieves all payment transactions for the authenticated user.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of Orders Per Page', type: 'int', default: 1, example: 1)]
    #[QueryParameter('payment_status', description: 'Filter by payment status', type: 'string', example: 'completed')]
    #[QueryParameter('date_from', description: 'Filter transactions from date (YYYY-MM-DD)', type: 'string', example: '2026-01-01')]
    #[QueryParameter('date_to', description: 'Filter transactions to date (YYYY-MM-DD)', type: 'string', example: '2026-01-31')]
    #[QueryParameter('search', description: 'Search by transaction reference or other fields', type: 'string', example: 'TX123')]
    public function getTransactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 15);
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $query = $user->OrderPaymentTransactions()->with('order')->latest();

        // Apply payment_status filter
        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        // Apply date range filters
        if ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Apply search filter (e.g. transaction reference or other fields)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%");
            });
        }

        // Paginate
        $transactions = $query->paginate($perPage);

        // Transform using resource collection
        $transactions->getCollection()->transform(function ($transaction) {
            return new OrderPaymentResource($transaction);
        });

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transactions_retrieved_successfully'),
            [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'data' => $transactions->items(),
            ]
        );
    }

    /**
     * Get a specific payment transaction
     *
     * Retrieves details of a specific payment transaction by its ID.
     */
    public function getTransaction($id): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $transaction = $user->OrderPaymentTransactions()->with('order')->where('id', $id)->first();

        if (! $transaction) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.transaction_not_found'),
                []
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transaction_retrieved_successfully'),
            OrderPaymentResource::make($transaction)
        );
    }

    public function getPaymentTransactionStatus(string $transactionUuid): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $transaction = $user->OrderPaymentTransactions()
            ->with('order')
            ->where(function ($query) use ($transactionUuid) {
                $query->where('uuid', $transactionUuid)
                    ->orWhere('transaction_id', $transactionUuid);
            })
            ->latest()
            ->first();

        if (! $transaction) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.transaction_not_found'),
                [],
                404
            );
        }

        $createdOrder = null;
        $paymentStatus = (string) $transaction->payment_status;
        if ($paymentStatus === PaymentStatusEnum::COMPLETED() && $transaction->order_id === null) {
            $createdOrder = $this->orderService->createConfirmedHostedGatewayOrder($transaction);
            if ($createdOrder) {
                $transaction = $transaction->fresh(['order']);
                $paymentStatus = (string) $transaction->payment_status;
            }
        }

        $transaction->loadMissing('order');
        $order = $createdOrder?->fresh() ?? $transaction->order;
        $status = match (true) {
            $order !== null => 'order_created',
            $paymentStatus === 'completed' => 'payment_completed_order_pending',
            $paymentStatus === 'failed' => 'payment_failed',
            default => 'payment_pending',
        };

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transaction_retrieved_successfully'),
            [
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method' => $transaction->payment_method,
                'transaction_uuid' => $transaction->uuid,
                'transaction_id' => $transaction->transaction_id,
                'transaction' => new OrderPaymentResource($transaction),
                'order' => $order ? new OrderResource($order) : null,
            ]
        );
    }

    /**
     * Get Applicable Return Reasons for an Order Item
     */
    public function getItemReturnReasons(int $orderItemId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $orderItem = OrderItem::with('product')->whereHas('order', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->find($orderItemId);

        if (! $orderItem || ! $orderItem->product) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.record_not_found') ?? 'Order item or product not found',
                []
            );
        }

        $reasons = $orderItem->product->getAvailableReturnReasons()->map(function ($reason) {
            return [
                'id' => $reason->id,
                'title' => $reason->title,
                'proof_required' => (bool) $reason->proof_required,
                'otp_required' => (bool) $reason->otp_required,
            ];
        });

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.data_found_successfully') ?? 'Return reasons retrieved successfully',
            $reasons
        );
    }

    /**
     * Return Order Item
     *
     * Return an order item if it meets the return criteria.
     */
    public function returnOrderItem(int $orderItemId, CreateItemReturnRequest $request): JsonResponse
    {
        // Demo Mode Fix: Block action execution when demo mode is enabled
        if (\App\Models\Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.',
                []
            );
        }

        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $validated = $request->validated();
        $validated['order_item_id'] = $orderItemId;

        $result = $this->orderService->returnOrderItem($user, $validated);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }

    /**
     * Cancel Return Request
     */
    public function cancelReturnRequest($orderItemId): JsonResponse
    {
        // Demo Mode Fix: Block action execution when demo mode is enabled
        if (\App\Models\Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.',
                []
            );
        }

        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->cancelReturnRequest(user: $user, orderItemId: $orderItemId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }

    /**
     * Reorder: Add all items from a previous order back into the user's cart.
     *
     * Quantity behavior: Ignores any provided quantities and uses each product's
     * quantity_step_size (default 1) when adding to cart.
     */
    public function reorder(int $orderId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponseType::sendJsonResponse(false, __('labels.user_not_authenticated'), []);
        }

        // Quantities from request are intentionally ignored per requirement
        $result = $this->cartService->reorderFromOrder($user, $orderId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }
}
