<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CreatePosOrderRequest;
use App\Http\Requests\Pos\QuickRegisterCustomerRequest;
use App\Http\Requests\Pos\SearchPosProductRequest;
use App\Http\Resources\Seller\Pos\PosCustomerResource;
use App\Http\Resources\Seller\Pos\PosProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosParkedSale;
use App\Models\PosRefund;
use App\Models\PosRefundLine;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CartService;
use App\Services\PosCustomerService;
use App\Services\PosOrderService;
use App\Services\PosPaymentSessionService;
use App\Services\PosProductCatalogService;
use App\Services\PosRefundService;
use App\Services\SubscriptionUsageService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Seller-panel POS page (web).
 *
 * Renders the in-store sale UI. Heavy lifting (product picker, cart, customer
 * step, checkout) lives client-side and talks to the existing POS API
 * endpoints under /api/seller/pos/*.
 */
class PosController extends Controller
{
    use ChecksPermissions;

    protected bool $viewPermission = false;

    protected bool $createOrderPermission = false;

    protected bool $refundPermission = false;

    protected bool $parkSalePermission = false;

    protected bool $paymentSessionPermission = false;

    protected bool $posSubscriptionActive = false;

    public function __construct(
        protected PosOrderService $orderService,
        protected PosProductCatalogService $catalogService,
        protected PosCustomerService $customerService,
        protected PosRefundService $refundService,
        protected PosPaymentSessionService $paymentSessionService,
    ) {
        $user = auth()->user();
        if ($user) {
            $seller = $user->seller();
            $this->posSubscriptionActive = $seller
                ? app(SubscriptionUsageService::class)->hasPosAccess($seller->id)
                : false;

            $hasRole = $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->viewPermission = $this->posSubscriptionActive
                && ($this->hasPermission(SellerPermissionEnum::POS_VIEW()) || $hasRole);
            $this->createOrderPermission = $this->posSubscriptionActive
                && ($this->hasPermission(SellerPermissionEnum::POS_CREATE_ORDER()) || $hasRole);
            $this->refundPermission = $this->posSubscriptionActive
                && ($this->hasPermission(SellerPermissionEnum::POS_REFUND()) || $hasRole);
            $this->parkSalePermission = $this->posSubscriptionActive
                && ($this->hasPermission(SellerPermissionEnum::POS_PARK_SALE()) || $hasRole);
            $this->paymentSessionPermission = $this->posSubscriptionActive
                && ($this->hasPermission(SellerPermissionEnum::POS_PAYMENT_SESSION()) || $hasRole);
        }
    }

    public function index(): Factory|ViewContract
    {
        try {
            if (! $this->posSubscriptionActive) {
                return view('seller.pos.upgrade');
            }

            if (! $this->viewPermission) {
                abort(403);
            }

            $user = auth()->user();
            $seller = $user?->seller();

            $stores = $seller->stores()
                ->where('verification_status', 'approved')
                ->where('visibility_status', 'visible')
                ->get(['id', 'name', 'currency_code', 'pos_upi_vpa', 'pos_upi_payee_name', 'pos_payment_config']);

            $categories = $stores->isNotEmpty()
                ? $this->catalogService->topLevelCategoriesForStore($stores->first())
                : collect();

            return view('seller.pos.index', [
                'seller' => $seller,
                'stores' => $stores,
                'categories' => $categories,
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            abort(500);
        }
    }

    /**
     * Session-authenticated JSON endpoint used by the seller-panel POS UI to
     * search the seller's own inventory.
     */
    public function searchProducts(SearchPosProductRequest $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $store = Store::where('id', (int) $request->input('store_id'))
                ->where('seller_id', $seller->id)
                ->first();
            if (! $store) {
                return ApiResponseType::sendJsonResponse(false, 'labels.store_not_found', null, 404);
            }

            $results = $this->catalogService->searchInStore(
                store: $store,
                q: trim((string) $request->input('q', '')),
                perPage: (int) $request->input('per_page', 24),
                includeOutOfStock: (bool) $request->boolean('include_out_of_stock', false),
                categoryId: $request->input('category_id') ? (int) $request->input('category_id') : null,
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.products_fetched_successfully',
                data: [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'products' => PosProductResource::collection($results->getCollection()),
                ]
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Barcode / SKU lookup for the toolbar scanner field.
     */
    public function lookupByBarcode(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'store_id' => ['required', 'integer'],
                'code' => ['required', 'string', 'max:128'],
            ]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $store = Store::where('id', (int) $request->input('store_id'))
                ->where('seller_id', $seller->id)
                ->first();
            if (! $store) {
                return ApiResponseType::sendJsonResponse(false, 'labels.store_not_found', null, 404);
            }

            $product = $this->catalogService->findByBarcode($store, (string) $request->input('code'));
            if (! $product) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_no_product_barcode_match', null, 404);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.pos_success',
                data: ['product' => new PosProductResource($product)],
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Returns top-level category chips for the cashier's filter row.
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate(['store_id' => ['required', 'integer']]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $store = Store::where('id', (int) $request->input('store_id'))
                ->where('seller_id', $seller->id)
                ->first();
            if (! $store) {
                return ApiResponseType::sendJsonResponse(false, 'labels.store_not_found', null, 404);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.pos_categories_fetched',
                data: ['categories' => $this->catalogService->topLevelCategoriesForStore($store)->values()]
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Search existing customers for the cashier's customer-step.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'q' => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            if (! auth()->user()?->seller()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $results = $this->customerService->search(
                query: trim((string) $request->input('q', '')),
                perPage: (int) $request->input('per_page', 10),
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.customers_fetched_successfully',
                data: [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'customers' => PosCustomerResource::collection($results),
                ]
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Quick-register a customer from the POS counter.
     */
    public function registerCustomer(QuickRegisterCustomerRequest $request): JsonResponse
    {
        try {
            if (! $this->createOrderPermission) {
                return $this->unauthorizedResponse();
            }

            if (! auth()->user()?->seller()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->customerService->quickRegister($request->validated());

            if (! $result['success']) {
                $data = $result['data'];
                if (isset($data['customer']) && $data['customer'] instanceof User) {
                    $data['customer'] = new PosCustomerResource($data['customer']);
                }

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $result['message'],
                    data: $data,
                    status: isset($result['data']['reason']) && $result['data']['reason'] === 'duplicate' ? 409 : 422
                );
            }

            $data = $result['data'];
            if (isset($data['customer']) && $data['customer'] instanceof User) {
                $data['customer'] = new PosCustomerResource($data['customer']);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $result['message'],
                data: $data,
                status: 201
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Create a POS order from the seller-panel UI.
     */
    public function createOrder(CreatePosOrderRequest $request): JsonResponse
    {
        try {
            if (! $this->createOrderPermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->orderService->create($seller, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? null,
                status: $result['success'] ? 201 : 422
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Validate a promo code against a POS cart subtotal.
     */
    public function validatePromo(Request $request, CartService $cartService): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'promo_code' => ['required', 'string', 'max:64'],
                'cart_amount' => ['required', 'numeric', 'min:0.01'],
                'customer_id' => ['nullable', 'integer'],
            ]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $customerId = (int) $request->input('customer_id', 0);
            $user = $customerId > 0 ? User::find($customerId) : null;
            if (! $user) {
                $user = app(PosCustomerService::class)->ensureWalkinPlaceholder();
            }

            $result = $cartService->validatePromoCode(
                promoCode: (string) $request->input('promo_code'),
                user: $user,
                cartTotal: (float) $request->input('cart_amount'),
                deliveryCharge: 0.0,
            );

            $promo = $result['promo'] ?? null;

            return ApiResponseType::sendJsonResponse(
                success: (bool) ($result['success'] ?? false),
                message: (string) ($result['message'] ?? ''),
                data: [
                    'discount' => (float) ($result['discount'] ?? 0),
                    'promo_code' => (string) ($promo?->code ?? $request->input('promo_code')),
                    'discount_type' => $promo?->discount_type,
                    'discount_amount' => $promo ? (float) $promo->discount_amount : null,
                    'max_discount_value' => $promo?->max_discount_value !== null ? (float) $promo->max_discount_value : null,
                ],
                status: ($result['success'] ?? false) ? 200 : 422,
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    // ── Customer-Facing Display (CFD) ─────────────────────────────

    private const CFD_TTL_MINUTES = 240;

    private function cfdCacheKey(string $token): string
    {
        return 'pos_cfd:'.$token;
    }

    /**
     * Push current cart state from the cashier POS to the cache.
     */
    public function cfdPush(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'token' => ['required', 'string', 'size:36'],
                'state' => ['required', 'array'],
            ]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $state = (array) $request->input('state');
            $state['__seller_id'] = $seller->id;
            $state['__pushed_at'] = now()->toIso8601String();

            Cache::put(
                $this->cfdCacheKey((string) $request->input('token')),
                $state,
                now()->addMinutes(self::CFD_TTL_MINUTES)
            );

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', null);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Public pull endpoint for the CFD page. No auth: the token is the secret.
     */
    public function cfdPull(string $token): JsonResponse
    {
        try {
            $state = Cache::get($this->cfdCacheKey($token));
            if (! $state) {
                return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                    'active' => false,
                    'state' => null,
                ]);
            }
            unset($state['__seller_id']);

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'active' => true,
                'state' => $state,
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Public CFD page — full-screen, customer-friendly.
     */
    public function cfdShow(string $token): ViewContract
    {
        return view('pos.cfd', [
            'token' => $token,
            'pullUrl' => route('pos.public.cfd.pull', ['token' => $token]),
        ]);
    }

    /**
     * Read-only wallet snapshot for the cashier UI.
     */
    public function customerWallet(Request $request, int $customerId): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $wallet = Wallet::where('user_id', $customerId)->first();

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'balance' => (float) ($wallet->balance ?? 0),
                'currency_code' => $wallet->currency_code ?? null,
                'name' => User::where('id', $customerId)->value('name'),
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    // ── Held / parked sales ────────────────────────────────────────

    public function listParkedSales(Request $request): JsonResponse
    {
        try {
            if (! $this->parkSalePermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $rows = PosParkedSale::where('seller_id', $seller->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'parked' => $rows->map(fn ($p) => [
                    'id' => $p->id,
                    'store_id' => $p->store_id,
                    'label' => $p->label,
                    'amount' => (float) $p->amount,
                    'item_count' => count($p->payload['items'] ?? []),
                    'created_at' => $p->created_at?->toIso8601String(),
                    'payload' => $p->payload,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    public function parkSale(Request $request): JsonResponse
    {
        try {
            if (! $this->parkSalePermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'store_id' => ['required', 'integer'],
                'label' => ['nullable', 'string', 'max:120'],
                'amount' => ['required', 'numeric', 'min:0'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.store_product_variant_id' => ['required', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $store = Store::where('id', (int) $request->input('store_id'))
                ->where('seller_id', $seller->id)
                ->first();
            if (! $store) {
                return ApiResponseType::sendJsonResponse(false, 'labels.store_not_found', null, 404);
            }

            $payload = $request->except(['_token']);

            $row = PosParkedSale::create([
                'seller_id' => $seller->id,
                'store_id' => $store->id,
                'label' => $request->input('label') ?: null,
                'amount' => (float) $request->input('amount'),
                'payload' => $payload,
            ]);

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_sale_held', ['id' => $row->id], 201);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    public function updateParkedSale(Request $request, int $id): JsonResponse
    {
        try {
            if (! $this->parkSalePermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'label' => ['nullable', 'string', 'max:120'],
                'amount' => ['required', 'numeric', 'min:0'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.store_product_variant_id' => ['required', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $row = PosParkedSale::where('id', $id)
                ->where('seller_id', $seller->id)
                ->first();
            if (! $row) {
                return ApiResponseType::sendJsonResponse(false, 'labels.model_not_found', null, 404);
            }

            $payload = $request->except(['_token']);

            $row->update([
                'label' => $request->input('label') ?: null,
                'amount' => (float) $request->input('amount'),
                'payload' => $payload,
            ]);

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_sale_held', ['id' => $row->id]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    public function deleteParkedSale(Request $request, int $id): JsonResponse
    {
        try {
            if (! $this->parkSalePermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            PosParkedSale::where('id', $id)->where('seller_id', $seller->id)->delete();

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_parked_sale_removed', null);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Return the seller's most recent POS sales for receipt reprint.
     */
    public function recentOrders(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                return $this->unauthorizedResponse();
            }

            $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
                'q' => ['nullable', 'string', 'max:120'],
                'sort' => ['nullable', Rule::in(['id', 'created_at', 'final_total'])],
                'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            ]);

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $limit = (int) $request->input('limit', 50);
            $q = trim((string) $request->input('q', ''));
            $sort = (string) $request->input('sort', 'id');
            $direction = (string) $request->input('direction', 'desc');

            $storeIds = $seller->stores()->pluck('id')->all();
            if (empty($storeIds)) {
                return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                    'orders' => [],
                    'total' => 0,
                    'limit' => $limit,
                ]);
            }

            $orderIds = OrderItem::whereIn('store_id', $storeIds)
                ->whereHas('order', fn ($qb) => $qb->where('created_by', OrderCreatedByEnum::SELLER()))
                ->pluck('order_id')
                ->unique()
                ->values()
                ->all();

            $sellerOrders = SellerOrder::whereIn('order_id', $orderIds)
                ->where('seller_id', $seller->id)
                ->get(['id', 'order_id'])
                ->keyBy('order_id');

            $base = Order::whereIn('id', $orderIds);
            if ($q !== '') {
                $needle = $q;
                $needleN = str_replace([',', ' ', '_'], '', $q);
                $base->where(function ($qb) use ($needle, $needleN) {
                    $qb->where('id', $needle)
                        ->orWhere('walkin_customer_name', 'like', "%{$needle}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$needle}%"))
                        ->orWhere('payment_method', 'like', "%{$needle}%");
                    if (is_numeric($needleN)) {
                        $qb->orWhere('final_total', (float) $needleN);
                    }
                });
            }

            $total = (clone $base)->count();

            $orders = $base->with('user:id,name')
                ->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc')
                ->limit($limit)
                ->get(['id', 'user_id', 'created_at', 'final_total', 'currency_code', 'payment_method', 'payment_status', 'walkin_customer_name']);

            $shownOrderIds = $orders->pluck('id')->all();
            $itemQtyByOrder = OrderItem::whereIn('order_id', $shownOrderIds)
                ->selectRaw('order_id, SUM(quantity) as qty')
                ->groupBy('order_id')
                ->pluck('qty', 'order_id');
            $refundedQtyByOrder = PosRefundLine::join('pos_refunds', 'pos_refunds.id', '=', 'pos_refund_lines.pos_refund_id')
                ->whereIn('pos_refunds.order_id', $shownOrderIds)
                ->selectRaw('pos_refunds.order_id as order_id, SUM(pos_refund_lines.quantity) as qty')
                ->groupBy('pos_refunds.order_id')
                ->pluck('qty', 'order_id');

            $payload = $orders->map(function (Order $o) use ($sellerOrders, $itemQtyByOrder, $refundedQtyByOrder) {
                $sellerOrderId = $sellerOrders->get($o->id)?->id;
                $totalQty = (int) ($itemQtyByOrder[$o->id] ?? 0);
                $refundedQty = (int) ($refundedQtyByOrder[$o->id] ?? 0);
                $refundable = $totalQty > 0 && $refundedQty < $totalQty;

                return [
                    'id' => $o->id,
                    'created_at' => $o->created_at?->toIso8601String(),
                    'final_total' => (float) $o->final_total,
                    'currency_code' => $o->currency_code,
                    'payment_label' => match ($o->payment_method) {
                        'pos_upi' => 'UPI',
                        'cod' => 'Cash',
                        'razorpayPayment' => 'Razorpay',
                        'stripePayment' => 'Stripe',
                        'paystackPayment' => 'Paystack',
                        'flutterwavePayment' => 'Flutterwave',
                        default => strtoupper((string) $o->payment_method),
                    },
                    'payment_status' => $o->payment_status,
                    'customer' => $this->resolveRecentOrderCustomer($o),
                    'receipt_url' => route('seller.pos.orders.receipt', ['id' => $o->id]),
                    'detail_url' => $sellerOrderId ? route('seller.orders.show', ['id' => $sellerOrderId]) : null,
                    'refundable' => $refundable,
                    'refunded_qty' => $refundedQty,
                    'has_refunds' => $refundedQty > 0,
                ];
            });

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'orders' => $payload,
                'total' => (int) $total,
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Resolve the display name for a recent POS sale, mirroring the receipt:
     * walk-in capture name first, then the attached real customer, finally the
     * walk-in placeholder label. Relies on the eager-loaded `user` relation.
     */
    private function resolveRecentOrderCustomer(Order $order): string
    {
        if (! empty($order->walkin_customer_name)) {
            return $order->walkin_customer_name;
        }

        if (! $order->isAttachedToWalkinPlaceholder()) {
            return $order->user?->name ?: __('labels.customer');
        }

        return __('labels.pos_walkin_customer');
    }

    /**
     * Returns refundable order_items for the cashier's refund modal.
     */
    public function refundPreview(Request $request, int $orderId): JsonResponse
    {
        try {
            if (! $this->refundPermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $order = Order::find($orderId);
            if (! $order || ! $order->isPosOrder()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_order_not_found', null, 404);
            }

            $isOwn = OrderItem::where('order_id', $order->id)
                ->whereHas('store', fn ($q) => $q->where('seller_id', $seller->id))
                ->exists();
            if (! $isOwn) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_order_not_found', null, 404);
            }

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.pos_success',
                $this->refundService->preview($order, (int) $seller->id)
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Create a POS refund.
     *
     * @param  int  $orderId  The order to refund against.
     */
    public function createRefund(Request $request, int $orderId): JsonResponse
    {
        try {
            if (! $this->refundPermission) {
                return $this->unauthorizedResponse();
            }

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
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $order = Order::find($orderId);
            if (! $order || ! $order->isPosOrder()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_order_not_found', null, 404);
            }

            $isOwn = OrderItem::where('order_id', $order->id)
                ->whereHas('store', fn ($q) => $q->where('seller_id', $seller->id))
                ->exists();
            if (! $isOwn) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_order_not_found', null, 404);
            }

            $lineQtys = [];
            foreach ($request->input('lines', []) as $line) {
                $lineQtys[(int) $line['order_item_id']] = (int) $line['quantity'];
            }

            $methodMeta = null;
            if ($note = trim((string) $request->input('method_note', ''))) {
                $methodMeta = ['note' => $note];
            }

            try {
                $refund = $this->refundService->create(
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
        } catch (\Throwable $e) {
            Log::error('POS refund failed', ['order_id' => $orderId, 'err' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.pos_refund_failed', null, 500);
        }
    }

    /**
     * Returns the full refund audit trail for one order.
     */
    public function refundHistory(Request $request, int $orderId): JsonResponse
    {
        try {
            if (! $this->refundPermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $order = Order::find($orderId);
            if (! $order || ! $order->isPosOrder()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_order_not_found', null, 404);
            }

            $isOwn = OrderItem::where('order_id', $order->id)
                ->whereHas('store', fn ($q) => $q->where('seller_id', $seller->id))
                ->exists();
            if (! $isOwn) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_order_not_found', null, 404);
            }

            $refunds = PosRefund::where('order_id', $order->id)
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
                    'lines' => $r->lines->map(fn ($l) => [
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
                'order_total' => (float) $order->final_total,
                'currency_code' => $order->currency_code,
                'total_refunded' => $totalRefunded,
                'refunds' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Render the printable POS receipt for an order.
     */
    public function receipt(Request $request, int $orderId): ViewContract
    {
        try {
            if (! $this->viewPermission) {
                abort(403);
            }

            $seller = auth()->user()?->seller();
            if (! $seller) {
                throw new NotFoundHttpException;
            }

            $order = Order::with(['items.addons.addonGroup', 'items.addons.addonItem'])->find($orderId);
            if (! $order || ! method_exists($order, 'isPosOrder') || ! $order->isPosOrder()) {
                throw new NotFoundHttpException;
            }

            $firstItem = $order->items->first();
            $store = $firstItem
                ? Store::where('id', $firstItem->store_id)->where('seller_id', $seller->id)->first()
                : null;
            if (! $store) {
                throw new NotFoundHttpException;
            }

            $customerName = __('labels.pos_walkin_customer');
            $customerMobile = null;
            if (! empty($order->walkin_customer_name)) {
                $customerName = $order->walkin_customer_name;
                $customerMobile = $order->walkin_customer_mobile;
            } elseif (! $order->isAttachedToWalkinPlaceholder()) {
                $orderUser = User::find($order->user_id);
                if ($orderUser) {
                    $customerName = $orderUser->name ?? __('labels.customer');
                    $customerMobile = $orderUser->mobile;
                }
            }

            return view('seller.pos.receipt', [
                'order' => $order,
                'store' => $store,
                'customerName' => $customerName,
                'customerMobile' => $customerMobile,
                'footerNote' => $this->resolveFooterNote($store),
                'currencySymbol' => app(\App\Services\CurrencyService::class)->getSymbol(),
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            abort(500, 'labels.something_went_wrong');
        }
    }

    private function resolveFooterNote(Store $store): string
    {
        $perStore = $store->receipt_template['footer_note'] ?? null;
        if (is_string($perStore) && $perStore !== '') {
            return $perStore;
        }

        $values = app(\App\Services\SettingService::class)->getSettingValues('pos_settings');
        $defaultTemplate = $values['default_receipt_template'] ?? null;
        if (is_array($defaultTemplate) && ! empty($defaultTemplate['footer_note'])) {
            return (string) $defaultTemplate['footer_note'];
        }

        return __('labels.pos_receipt_footer_default');
    }
}
