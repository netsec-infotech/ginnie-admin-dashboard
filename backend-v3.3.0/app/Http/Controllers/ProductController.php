<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Http\Requests\Product\StoreUpdateProductRequest;
use App\Interfaces\TranslationBulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use App\Services\CatalogLanguageService;
use App\Services\CategoryService;
use App\Services\GlobalAttributeService;
use App\Services\ProductService;
use App\Services\SubscriptionUsageService;
use App\Services\Visibility\ProductVisibilityInspector;
use App\Services\ZonePreviewService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Traits\SubscriptionLimitGuard;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;
    use SubscriptionLimitGuard;

    public float $sellerId;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $createPermission = false;

    protected bool $viewPermission = false;

    protected bool $updateStatusPermission = false;

    public function __construct()
    {
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;

        if ($this->getPanel() === 'seller') {
            $this->viewPermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->editPermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->deletePermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->createPermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        } elseif ($this->getPanel() === 'admin') {
            $this->viewPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_CREATE());
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_DELETE());
            $this->updateStatusPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_STATUS_UPDATE());
        }
    }

    /**
     * Resolve the seller this request is acting on:
     *   - seller panel: authenticated seller
     *   - admin panel:  seller_id submitted in the form (or already set on the product when editing)
     */
    protected function resolveSellerIdForWrite(?Product $product, $request): int
    {
        if ($this->getPanel() === 'admin') {
            $submitted = (int) ($request->input('seller_id') ?? 0);
            if ($submitted > 0) {
                return $submitted;
            }

            return (int) ($product?->seller_id ?? 0);
        }

        return (int) (auth()->user()?->seller()?->id ?? 0);
    }

    public static function normalizeStatusGroup(?string $statusGroup): ?string
    {
        if (! $statusGroup) {
            return null;
        }

        return in_array($statusGroup, ['approved', 'pending', 'rejected'], true) ? $statusGroup : null;
    }

    public static function applyInsightStatusGroup(Builder $query, string $statusGroup): Builder
    {
        return match ($statusGroup) {
            'approved' => $query->where('verification_status', ProductVarificationStatusEnum::APPROVED()),
            'pending' => $query->where('verification_status', ProductVarificationStatusEnum::PENDING()),
            'rejected' => $query->where('verification_status', ProductVarificationStatusEnum::REJECTED()),
            default => $query,
        };
    }

    /**
     * Display a listing of the resource.
     */
    public function index(?Request $request = null): View
    {
        $this->authorize('viewAny', Product::class);
        $request = $request ?? request();

        $columns = [
            ['data' => 'select', 'name' => 'select', 'title' => '<input type="checkbox" id="select-all-products" class="form-check-input bulk-delete-select-all">', 'orderable' => false, 'searchable' => false],
            ['data' => 'expand', 'name' => 'expand', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'product_details', 'name' => 'product_details', 'title' => __('labels.product_details')],
            ['data' => 'admin_approval_status', 'name' => 'admin_approval_status', 'title' => __('labels.admin_approval_status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $viewPermission = $this->viewPermission;
        $productCreateLimitReached = false;
        $productCreateLimitMessage = null;

        if (
            $this->getPanel() === 'seller'
            && $createPermission
            && Setting::isSystemVendorTypeMultiple()
            && Setting::isSubscriptionEnabled()
        ) {
            $limitKey = SubscriptionPlanKeyEnum::PRODUCT_LIMIT();
            $usageService = app(SubscriptionUsageService::class);
            $limit = $usageService->getLimit((int) $this->sellerId, $limitKey);
            $used = $usageService->getUsage((int) $this->sellerId, $limitKey);

            if ($limit >= 0 && $used >= $limit) {
                $productCreateLimitReached = true;
                $productCreateLimitMessage = __('labels.subscription_limit_exceeded', [
                    'key' => Str::ucfirst(Str::replace('_', ' ', $limitKey)),
                    'limit' => $limit,
                    'used' => $used,
                    'remaining' => max(0, $limit - $used),
                ]);
            }
        }

        if ($this->getPanel() === 'seller') {
            $sellerId = (int) $this->sellerId;
            $productInsights = [
                'total' => Product::where('seller_id', $sellerId)->count(),
                'approved' => Product::where('seller_id', $sellerId)->where('verification_status', ProductVarificationStatusEnum::APPROVED())->count(),
                'pending' => Product::where('seller_id', $sellerId)->where('verification_status', ProductVarificationStatusEnum::PENDING())->count(),
                'rejected' => Product::where('seller_id', $sellerId)->where('verification_status', ProductVarificationStatusEnum::REJECTED())->count(),
            ];
        } else {
            $productInsights = [
                'total' => Product::count(),
                'approved' => Product::where('verification_status', ProductVarificationStatusEnum::APPROVED())->count(),
                'pending' => Product::where('verification_status', ProductVarificationStatusEnum::PENDING())->count(),
                'rejected' => Product::where('verification_status', ProductVarificationStatusEnum::REJECTED())->count(),
            ];
        }

        $activeStatusGroup = self::normalizeStatusGroup($request->query('status_group'));

        return view($this->panelView('products.index'), compact(
            'columns',
            'editPermission',
            'deletePermission',
            'createPermission',
            'viewPermission',
            'productCreateLimitReached',
            'productCreateLimitMessage',
            'productInsights',
            'activeStatusGroup'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = CategoryService::getCategoriesWithParent();
        if ($this->getPanel() === 'admin') {
            $attributes = collect();
        } else {
            $attributes = GlobalAttributeService::getAttributesWithValue($this->sellerId);
        }

        $categories = json_encode($categories->toArray());
        $attributes = json_encode($attributes->toArray());
        $catalogLanguages = app(CatalogLanguageService::class)->active();
        $aiTranslationEnabled = Setting::isAiDescriptionEnabled();
        $brandCreatePermission = auth()->user()?->can('create', Brand::class) ?? false;

        return view($this->panelView('products.form'), compact('categories', 'attributes', 'catalogLanguages', 'aiTranslationEnabled', 'brandCreatePermission'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateProductRequest $request, ProductService $productService): JsonResponse
    {
        try {
            $this->authorize('create', Product::class);

            $validated = $request->validated();
            $validated['seller_id'] = $this->resolveSellerIdForWrite(null, $request);
            if ($validated['seller_id'] <= 0) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'labels.seller_required', data: []);
            }
            // Pre-check subscription usage before creating (multivendor only)
            if ($error = $this->ensureCanUseOrError($validated['seller_id'], SubscriptionPlanKeyEnum::PRODUCT_LIMIT())) {
                return $error;
            }

            // Create product now that limit check passed
            $result = $productService->storeProduct($validated, $request);

            // Update usage after successful creation (multivendor only)
            $this->recordUsageIfMultivendor($validated['seller_id'], SubscriptionPlanKeyEnum::PRODUCT_LIMIT());

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_created_successfully', data: [
                'product_id' => $result['product']->id,
                'product_uuid' => $result['product']->uuid,
                'redirect_url' => route($this->getPanel().'.products.index'),
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (\Exception $e) {
            Log::error('Error while creating product =>'.$e->getMessage());

            return ApiResponseType::sendJsonResponse(success: false, message: $e->getMessage(), data: $e->getMessage());
        }
    }

    /**
     * Return the visibility inspection for a product — what gates it passes
     * and which zones it's reachable in.
     */
    public function visibility($id, ProductVisibilityInspector $inspector, ZonePreviewService $zoneSvc): JsonResponse
    {
        try {
            $product = Product::find($id);
            if (! $product) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.product_not_found',
                    data: [],
                );
            }
            $this->authorize('view', $product);

            // Sellers see only the zones their own stores cover. Admins see all active zones.
            $zoneScope = null;
            if ($this->getPanel() === 'seller' && $this->sellerId) {
                $zoneScope = $zoneSvc->sellerZoneIds((int) $this->sellerId);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.fetched_successfully',
                data: $inspector->inspect($product, $zoneScope),
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ProductController@visibility error: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: [],
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = ProductService::getProductWithVariants($id);
        if (! $product) {
            abort(404, 'Product Not Found');
        }
        $this->authorize('viewAny', $product);

        // Load relationships
        $product->load(['faqs', 'category', 'brand', 'productCondition']);

        // Get media
        $product->product_video = $product->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_VIDEO());

        // Get store-wise pricing data for variants
        $storeVariantPricing = [];
        $variants = ProductVariant::where('product_id', $id)->get();

        foreach ($variants as $variant) {
            // Load variant attributes
            $variant->load(['attributes.attribute', 'attributes.attributeValue']);

            // Get store-specific pricing for this variant
            $storePricing = StoreProductVariant::where('product_variant_id', $variant->id)
                ->with('store')
                ->get()
                ->map(function ($item) {
                    return [
                        'store_id' => $item->store_id,
                        'store_name' => $item->store->name ?? '',
                        'currency_code' => $item->store->currency_code ?? null,
                        'price' => $item->price_exclude_tax,
                        'special_price' => $item->special_price_exclude_tax,
                        'cost' => $item->cost,
                        'stock' => $item->stock,
                        'sku' => $item->sku,
                    ];
                });

            $storeVariantPricing[$variant->id] = [
                'variant_id' => $variant->id,
                'title' => $variant->getTranslation('title', 'en', false),
                'attributes' => $variant->attributes->map(function ($attr) {
                    return [
                        'attribute_name' => $attr->attribute?->getTranslation('title', 'en', false) ?? '',
                        'attribute_value' => $attr->attributeValue?->getTranslation('title', 'en', false) ?? '',
                    ];
                }),
                'store_pricing' => $storePricing,
            ];
        }
        $updateStatusPermission = $this->updateStatusPermission;

        // Build the "addon attachments" matrix the show view renders underneath
        // the store-wise pricing card. One attachment row = (store, variant,
        // group, item); we group them by variant → store → group → items and
        // fold in per-store pricing/stock from `store_addon_items` in a single
        // batch query to avoid N+1.
        $variantAddons = $this->buildProductVariantAddonMatrix($variants);

        return view($this->panelView('products.show'), compact(
            'product',
            'storeVariantPricing',
            'updateStatusPermission',
            'variantAddons',
        ));
    }

    /**
     * Build a nested view-model describing every addon attachment on a
     * product's variants, grouped by variant → store → addon group, with
     * per-store price + stock + availability folded in for each addon item.
     *
     * Shape returned (keyed by variant_id):
     *   [
     *     42 => [
     *       'variant_id'    => 42,
     *       'variant_title' => '14 inch',
     *       'stores' => [
     *         [
     *           'store_id'   => 7,
     *           'store_name' => 'Downtown',
     *           'groups' => [
     *             [
     *               'group_id'       => 3,
     *               'group_title'    => 'Toppings',
     *               'selection_type' => 'multiple',
     *               'is_required'    => false,
     *               'items' => [
     *                 [
     *                   'item_id'      => 10,
     *                   'item_title'   => 'Extra cheese',
     *                   'indicator'    => 'veg',
     *                   'price'        => 1.50,  // from store_addon_items
     *                   'stock'        => 50,    // from store_addon_items, null if no row
     *                   'is_available' => true,
     *                 ],
     *               ],
     *             ],
     *           ],
     *         ],
     *       ],
     *     ],
     *   ]
     *
     * Returns an empty array when no attachments exist — the blade can
     * short-circuit and skip rendering the card entirely.
     *
     * @param  Collection<int,ProductVariant>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function buildProductVariantAddonMatrix($variants): array
    {
        $variantIds = collect($variants)->pluck('id')->filter()->all();
        if (empty($variantIds)) {
            return [];
        }

        $attachments = StoreProductVariantAddon::with(['store', 'addonGroup', 'addonItem'])
            ->whereIn('product_variant_id', $variantIds)
            ->get();

        if ($attachments->isEmpty()) {
            return [];
        }

        // Batch-fetch the (store_id, addon_item_id) inventory rows in one
        // query. A belongsTo can't model the compound key safely, so this is
        // the canonical pattern (mirrors ProductVariantResource::lookupStoreAddonItems).
        $inventoryByPair = StoreAddonItem::query()
            ->whereIn('store_id', $attachments->pluck('store_id')->unique()->all())
            ->whereIn('addon_item_id', $attachments->pluck('addon_item_id')->unique()->all())
            ->get()
            ->keyBy(fn (StoreAddonItem $row) => ((int) $row->store_id).':'.((int) $row->addon_item_id));

        $variantTitles = collect($variants)->keyBy('id');

        $matrix = [];

        foreach ($attachments->groupBy('product_variant_id') as $variantId => $variantAttachments) {
            $stores = [];

            foreach ($variantAttachments->groupBy('store_id') as $storeId => $storeAttachments) {
                $firstStore = $storeAttachments->first();

                $groups = [];
                foreach ($storeAttachments->groupBy('addon_group_id') as $groupId => $groupAttachments) {
                    $firstGroup = $groupAttachments->first()->addonGroup ?? null;

                    $items = $groupAttachments->map(function ($row) use ($inventoryByPair) {
                        $inventory = $inventoryByPair->get(((int) $row->store_id).':'.((int) $row->addon_item_id));
                        $catalog = $row->addonItem;

                        return [
                            'item_id' => (int) $row->addon_item_id,
                            'item_title' => $catalog?->title ?? '—',
                            'indicator' => $catalog?->indicator?->value,
                            'price' => $inventory
                                ? (float) $inventory->price
                                : (float) ($catalog?->price ?? 0),
                            'stock' => $inventory ? (int) $inventory->stock : null,
                            'is_available' => $inventory
                                ? (bool) $inventory->is_available
                                : (bool) ($catalog?->is_available ?? true),
                        ];
                    })->values()->all();

                    $groups[] = [
                        'group_id' => (int) $groupId,
                        'group_title' => $firstGroup?->title ?? '—',
                        'selection_type' => $firstGroup?->selection_type?->value,
                        'is_required' => (bool) ($firstGroup?->is_required ?? false),
                        'items' => $items,
                    ];
                }

                $stores[] = [
                    'store_id' => (int) $storeId,
                    'store_name' => $firstStore->store?->name ?? '—',
                    'groups' => $groups,
                ];
            }

            $matrix[(int) $variantId] = [
                'variant_id' => (int) $variantId,
                'variant_title' => $variantTitles->get($variantId)?->getTranslation('title', 'en', false) ?? '—',
                'stores' => $stores,
            ];
        }

        return $matrix;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $product = ProductService::getProductWithVariants($id);
        if (! $product) {
            abort(404, 'Product Not Found');
        }
        $this->authorize('update', $product);
        $productVariants = null;
        $singleProductVariant = null;
        if ($product->type === ProductTypeEnum::VARIANT()) {
            foreach ($product->variants as $key => $variant) {
                $product->variants[$key]->image = $variant->image;
                $product->variants[$key]->title_translations = $variant->getTranslations('title') ?: [];
            }
            $productVariants = $product->variants;
        } else {
            $singleProductVariant = $product->variants->first();
        }
        $product->product_video = $product->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_VIDEO());
        $categories = CategoryService::getCategoriesWithParent();

        $effectiveSellerId = ($this->getPanel() === 'admin') ? $product->seller_id : $this->sellerId;
        $attributes = GlobalAttributeService::getAttributesWithValue($effectiveSellerId);
        $categories = json_encode($categories->toArray());
        $attributes = json_encode($attributes->toArray());
        $catalogLanguages = app(CatalogLanguageService::class)->active();
        $aiTranslationEnabled = Setting::isAiDescriptionEnabled();
        $brandCreatePermission = auth()->user()?->can('create', Brand::class) ?? false;

        return view($this->panelView('products.form'), compact('product', 'productVariants', 'singleProductVariant', 'categories', 'attributes', 'catalogLanguages', 'aiTranslationEnabled', 'brandCreatePermission'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateProductRequest $request, string $id, ProductService $productService): JsonResponse
    {
        try {
            // Find the product
            $product = Product::findOrFail($id);

            // Authorize the user
            $this->authorize('update', $product);

            $validated = $request->validated();

            // Add seller_id to validated data — admin uses form-picker, seller uses auth.
            $validated['seller_id'] = $this->resolveSellerIdForWrite($product, $request);
            if ($validated['seller_id'] <= 0) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'labels.seller_required', data: []);
            }

            // Update the product
            $result = $productService->updateProduct($product, $validated, $request);

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_updated_successfully', data: [
                'product_id' => $result['product']->id,
                'product_uuid' => $result['product']->uuid,
                'redirect_url' => route($this->getPanel().'.products.index'),
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        } catch (\Exception $e) {
            Log::error('Error while creating product =>'.$e->getMessage());

            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.failed_to_update_product: '.$e->getMessage(), data: []);
        }
    }

    /**
     * Get product pricing data for a specific product
     */
    public function getProductPricing(string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            // Authorize the user
            $this->authorize('view', $product);

            $variants = ProductVariant::where('product_id', $id)->get();

            $variantPricing = [];

            foreach ($variants as $variant) {
                $storePricing = StoreProductVariant::where('product_variant_id', $variant->id)
                    ->with('store.seller.user')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'store_id' => $item->store_id,
                            'store_name' => $item->store->name ?? '',
                            'seller_name' => $item->store?->seller?->user?->name ?? '',
                            'price' => $item->price_exclude_tax,
                            'special_price' => $item->special_price_exclude_tax,
                            'cost' => $item->cost,
                            'stock' => $item->stock,
                            'sku' => $item->sku,
                        ];
                    });

                $variantPricing[$variant->id] = [
                    'variant_id' => $variant->id,
                    'title' => $variant->getTranslation('title', 'en', false),
                    'store_pricing' => $storePricing,
                ];
            }

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_pricing_fetched_successfully', data: [
                'product_id' => $product->id,
                'variant_pricing' => $variantPricing,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.failed_to_fetch_product_pricing: '.$e->getMessage(), data: []);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            // Authorize the user
            $this->authorize('delete', $product);

            if ($product->hasPendingOrders()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.product_cannot_be_deleted_pending_orders_exist',
                    data: [],
                    status: 422
                );
            }

            $sellerId = $product->seller_id;
            $product->delete();

            // Reduce subscription usage for product_limit only when multivendor
            if ($sellerId) {
                $this->reduceUsageIfMultivendor((int) $sellerId, SubscriptionPlanKeyEnum::PRODUCT_LIMIT());
            }

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_deleted_successfully', data: []);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['required', 'integer', 'distinct'],
            ]);

            $deletedCount = 0;
            $skipped = [];
            $failed = [];

            foreach ($validated['ids'] as $productId) {
                $product = Product::find($productId);

                if (! $product) {
                    $skipped[] = [
                        'id' => (int) $productId,
                        'reason' => __('labels.product_not_found'),
                    ];

                    continue;
                }

                try {
                    $this->authorize('delete', $product);

                    if ($product->hasPendingOrders()) {
                        $skipped[] = [
                            'id' => (int) $product->id,
                            'title' => $product->getTranslation('title', 'en', false),
                            'reason' => __('labels.product_cannot_be_deleted_pending_orders_exist'),
                        ];

                        continue;
                    }

                    $sellerId = $product->seller_id;
                    $product->delete();

                    if ($sellerId) {
                        $this->reduceUsageIfMultivendor((int) $sellerId, SubscriptionPlanKeyEnum::PRODUCT_LIMIT());
                    }

                    $deletedCount++;
                } catch (AuthorizationException) {
                    $skipped[] = [
                        'id' => (int) $product->id,
                        'title' => $product->getTranslation('title', 'en', false),
                        'reason' => __('labels.permission_denied'),
                    ];
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id' => (int) $product->id,
                        'title' => $product->getTranslation('title', 'en', false),
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.bulk_delete_completed'),
                data: [
                    'deleted_count' => $deletedCount,
                    'skipped' => $skipped,
                    'failed' => $failed,
                ]
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        }
    }

    /**
     * Get products for datatable
     */
    public function getProducts(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Product::class);

            [$draw, $start, $length, $searchValue, $filters, $orderColumn, $orderDirection] = $this->extractRequestParams($request);
            $selectedIds = $this->selectedIdsFromRequest($request);

            $query = $this->buildBaseQuery();

            $totalRecords = $query->count();

            $query = $this->applyFilters($query, $searchValue, $filters);
            $filteredRecords = $query->count();

            $products = $query
                ->when(! empty($selectedIds), fn ($q) => $this->orderSelectedFirst($q, $selectedIds, 'products.id'))
                ->when(
                    $orderColumn === 'products.title',
                    fn ($q) => $q->orderByEnglishTitle($orderDirection),
                    fn ($q) => $q->orderBy($orderColumn, $orderDirection),
                )
                ->skip($start)
                ->take($length)
                ->get();

            $data = $products->map(fn ($product) => $this->formatProductData($product))->toArray();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.failed_to_fetch_products: '.$e->getMessage(), []);
        }
    }

    private function extractRequestParams(Request $request): array
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $filters = [
            'product_type' => $request->get('product_type'),
            'product_status' => $request->get('product_status'),
            'verification_status' => $request->get('verification_status'),
            'status_group' => $request->get('status_group'),
            'product_filter' => $request->get('product_filter'),
            'badge_id' => $request->get('badge_id'),
            'category_id' => $request->get('category_id'),
            'store_id' => $request->get('store_id'),
            'seller_id' => $request->get('seller_id'),
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 5);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc'));
        $requestedColumn = (string) $request->input("columns.{$orderColumnIndex}.data", 'created_at');
        $sortableColumns = [
            'id' => 'products.id',
            'product_details' => 'products.title',
            'admin_approval_status' => 'products.verification_status',
            'created_at' => 'products.created_at',
        ];

        $orderColumn = $sortableColumns[$requestedColumn] ?? 'products.created_at';
        if (! in_array($orderDirection, ['asc', 'desc'], true) || ! array_key_exists($requestedColumn, $sortableColumns)) {
            $orderDirection = 'desc';
        }

        return [$draw, $start, $length, $searchValue, $filters, $orderColumn, $orderDirection];
    }

    private function buildBaseQuery(): Builder
    {
        $query = Product::with(['category', 'seller', 'badge'])
            ->addSelect([
                'has_store_pricing' => StoreProductVariant::query()
                    ->join('product_variants', 'product_variants.id', '=', 'store_product_variants.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('1')
                    ->limit(1),
            ]);

        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $this->sellerId);
        }

        return $query;
    }

    private function applyFilters($query, string $searchValue, array $filters)
    {
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->whereEnglishTextLike($searchValue)
                    ->orWhere('id', 'like', "%{$searchValue}%")
                    ->orWhere('type', 'like', "%{$searchValue}%")
                    ->orWhere('verification_status', 'like', "%{$searchValue}%")
                    ->orWhereHas('category', fn ($q) => $q->whereEnglishTextLike($searchValue));
            });
        }

        if (! empty($filters['product_type'])) {
            $query->where('type', $filters['product_type']);
        }

        if (! empty($filters['product_status'])) {
            $query->where('status', $filters['product_status']);
        }

        if (! empty($filters['verification_status'])) {
            $status = $filters['verification_status'];
            if ($status === 'pending') {
                $status = ProductVarificationStatusEnum::PENDING();
            }
            $query->where('verification_status', $status);
        } elseif (! empty($filters['status_group'])) {
            if ($normalizedGroup = self::normalizeStatusGroup($filters['status_group'])) {
                self::applyInsightStatusGroup($query, $normalizedGroup);
            }
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['badge_id'])) {
            $query->where('badge_id', (int) $filters['badge_id']);
        }

        if (! empty($filters['store_id'])) {
            $query->whereHas('variants.storeProductVariants', fn ($q) => $q->where('store_id', $filters['store_id']));
        }

        if (! empty($filters['seller_id']) && $this->getPanel() === 'admin') {
            $query->where('seller_id', $filters['seller_id']);
        }

        // Apply reusable product specific filters via model scope (featured, low_stock, out_of_stock)
        if (! empty($filters['product_filter'])) {
            $query->applyProductFilter($filters['product_filter']);
        }

        return $query;
    }

    private function selectedIdsFromRequest(Request $request): array
    {
        $ids = $request->input('selected_ids', $request->input('ids', []));

        return collect(is_array($ids) ? $ids : [$ids])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function orderSelectedFirst($query, array $selectedIds, string $column)
    {
        $ids = implode(',', array_map('intval', $selectedIds));

        return $query->orderByRaw("CASE WHEN {$column} IN ({$ids}) THEN 0 ELSE 1 END");
    }

    private function formatProductData(Product $product): array
    {
        $englishTitle = $product->getTranslation('title', 'en', false);
        $productType = '<span class="badge '.
            ($product->type == ProductTypeEnum::VARIANT() ? 'bg-danger-lt' : 'bg-info-lt').
            '">'.$product->type.'</span>';
        $status = view('partials.status', ['status' => $product->status ?? ''])->render();
        $expand = $product->has_store_pricing
            ? '<button type="button" class="btn btn-icon btn-sm btn-ghost-secondary expand-pricing" data-product-id="'.(int) $product->id.'" aria-label="'.e(__('labels.store_wise_pricing')).'" title="'.e(__('labels.store_wise_pricing')).'"><i class="ti ti-chevron-right"></i></button>'
            : '';

        return [
            'select' => '<input type="checkbox" class="form-check-input product-row-checkbox bulk-delete-row-checkbox" value="'.$product->id.'" data-title="'.e($englishTitle).'">',
            'expand' => $expand,
            'id' => $product->id,
            'product_details' => "<div class='d-flex justify-content-start align-items-center'><div class='pe-2'>".
                view('partials.image', [
                    'image' => $product->main_image ?? '',
                ])->render().
                "</div><div>
                        <p class='m-0 fw-medium text-primary'>".__('labels.title').': '.e($englishTitle)."</p>
                        <p class='m-0'>".__('labels.category').": {$product->category?->getTranslation('title', 'en', false)}</p>
                        <p class='m-0'>".__('labels.brand').": {$product->brand?->getTranslation('title', 'en', false)}</p>
                        <p class='m-0'>".__('labels.featured').': '.($product->featured ? 'Yes' : 'No').'</p>
                        '.($product->badge ? "<span class='badge' style='background-color:".e($product->badge->bg_color).';color:'.e($product->badge->text_color).';'.($product->badge->border_color ? 'border:1px solid '.e($product->badge->border_color).';' : '')."padding:2px 8px;border-radius:4px;font-size:0.7rem;'>".e($product->badge->label).'</span>' : '')."
                        <div class='d-flex gap-1 mt-1'>".$productType.' '.$status.'</div>'.

                '</div></div>',
            'admin_approval_status' => view('partials.status', ['status' => $product->verification_status ?? ''])->render(),
            'created_at' => $product->created_at->format('Y-m-d'),
            'action' => view('partials.product-actions', [
                'modelName' => 'product',
                'id' => $product->id,
                'title' => $englishTitle,
                'status' => $product->status,
                'badge' => $product->badge,
                'showBadgeAction' => $this->getPanel() === 'admin',
                'mode' => 'page_view',
                'route' => route($this->panelView('products.edit'), ['id' => $product->id]),
                'viewRoute' => route($this->panelView('products.show'), ['id' => $product->id]),
                'editPermission' => $this->editPermission,
                'deletePermission' => $this->deletePermission,
                'viewPermission' => $this->viewPermission,
            ])->render(),
        ];
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('search');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');
        $sellerId = $request->input('seller_id');
        $categoryId = $request->integer('category_id');

        if ($findId) {
            $products = Product::where('id', $findId)
                ->select('id', 'title')
                ->with('media')
                ->get();
        } else {
            $products = Product::select('id', 'title')
                ->whereEnglishTextLike((string) $query)
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->when($categoryId > 0, function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                })
                ->when($this->getPanel() === 'seller', function ($q) {
                    // On the seller panel always scope to the authenticated seller.
                    $q->where('seller_id', $this->sellerId);
                })
                ->when($this->getPanel() === 'admin' && $sellerId, function ($q) use ($sellerId) {
                    // On the admin panel scope by the optionally supplied seller_id.
                    $q->where('seller_id', $sellerId);
                })
                ->with('media')
                ->limit(10)
                ->get();
        }
        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'value' => $product->id,
                'text' => $product->getTranslation('title', 'en', false),
                'image_url' => $product->main_image,
            ];
        });

        return response()->json($results);
    }

    /**
     * Return attributes for a given seller (AJAX, admin panel only).
     */
    public function getAttributesBySeller(Request $request): JsonResponse
    {
        $sellerId = (int) $request->input('seller_id', 0);
        if ($sellerId <= 0) {
            return response()->json([]);
        }

        $attributes = GlobalAttributeService::getAttributesWithValue($sellerId);

        return response()->json($attributes);
    }

    public function updateVerificationStatus(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $this->authorize('verifyProduct', $product);

            $request->validate([
                'verification_status' => ['required', new Enum(ProductVarificationStatusEnum::class)],
                'rejection_reason' => 'required_if:verification_status,'.ProductVarificationStatusEnum::REJECTED().'|max:500',
            ]);

            $status = $request->input('verification_status');
            if ($status === ProductVarificationStatusEnum::REJECTED() && empty($request->input('rejection_reason'))) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Rejection reason is required', data: []);
            }

            $product->verification_status = $status;
            $newStatus = $status === ProductVarificationStatusEnum::APPROVED()
                ? ProductStatusEnum::ACTIVE()
                : ProductStatusEnum::DRAFT();

            $product->status = $newStatus;
            $product->rejection_reason = $status === ProductVarificationStatusEnum::REJECTED()
                ? $request->input('rejection_reason')
                : null;
            $product->save();

            return ApiResponseType::sendJsonResponse(success: true, message: 'Verification status updated successfully', data: [
                'id' => $product->id,
                'verification_status' => $product->verification_status,
                'rejection_reason' => $product->rejection_reason,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: $e->getMessage(), data: $e->errors());
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.something_went_wrong', data: ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update product status (draft/active)
     */
    public function updateStatus(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $this->authorize('update', $product);

            // Toggle status between ACTIVE and DRAFT
            $newStatus = $product->status === ProductStatusEnum::ACTIVE()
                ? ProductStatusEnum::DRAFT()
                : ProductStatusEnum::ACTIVE();

            $product->status = $newStatus;
            $product->save();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: "Product status updated to {$newStatus} successfully",
                data: [
                    'id' => $product->id,
                    'status' => $product->status,
                ]
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.something_went_wrong', data: ['error' => $e->getMessage()]);
        }
    }

    /**
     * Show bulk upload page for products (panel-aware, seller focus).
     */
    public function bulkUploadPage(BulkUploadModuleRegistry $registry): View
    {
        $this->authorize('create', Product::class);
        $editPermission = $this->editPermission;
        $createPermission = $this->createPermission;
        $moduleConfig = $registry->get('products');
        $importer = app($moduleConfig->importerClass);
        $availableFields = collect($importer->availableFields())
            ->reject(fn (array $field) => $this->getPanel() === 'seller' && $field['key'] === 'seller_id')
            ->values()
            ->all();
        $availableMatchFields = $importer->availableMatchFields();
        $activeJob = BulkUploadJob::query()
            ->forType(Product::class)
            ->where('user_id', auth()->id())
            ->runnable()
            ->orderByDesc('id')
            ->first();
        $defaultValues = $this->getPanel() === 'seller' ? ['seller_id' => (int) $this->sellerId] : [];
        $catalogLanguages = app(CatalogLanguageService::class)->active();
        $supportsTranslation = $importer instanceof TranslationBulkImportable;
        $canTranslate = auth()->user()?->can('update', new Product($defaultValues)) ?? false;

        return view($this->panelView('products.bulk_upload'), compact(
            'editPermission',
            'createPermission',
            'moduleConfig',
            'availableFields',
            'availableMatchFields',
            'activeJob',
            'defaultValues',
            'catalogLanguages',
            'supportsTranslation',
            'canTranslate',
        ));
    }

    public function exportCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        [$filters, $selectedIds] = $this->exportFiltersFromRequest($request);
        $query = $this->exportQuery($filters, $selectedIds);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.fetched_successfully',
            data: ['count' => $query->count()],
        );
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        [$filters, $selectedIds] = $this->exportFiltersFromRequest($request);
        $query = $this->exportQuery($filters, $selectedIds)
            ->with([
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
                'variants.storeProductVariants',
            ]);

        // Import-format exports intentionally ignore visible DataTable columns and use bulk-upload headers.
        $datatableColumns = $request->query('export_format') === 'import'
            ? []
            : $this->productDatatableExportColumns((array) $request->query('columns', []));
        $columns = ! empty($datatableColumns) ? array_keys($datatableColumns) : $this->productCsvColumns();
        $filename = $this->getPanel().'_products_export_'.now()->format('Y-m-d_Hi').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($query, $columns, $datatableColumns) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ! empty($datatableColumns) ? array_values($datatableColumns) : $columns);

            $query->orderBy('products.id')->chunk(200, function ($products) use ($output, $columns, $datatableColumns) {
                foreach ($products as $product) {
                    if (! empty($datatableColumns)) {
                        $payload = $this->productDatatableExportRow($product);
                        fputcsv($output, array_map(fn ($column) => $payload[$column] ?? '', $columns));

                        continue;
                    }

                    foreach ($this->productExportRows($product, $columns) as $row) {
                        fputcsv($output, $row);
                    }
                }
            });

            fclose($output);
        }, $filename, $headers);
    }

    private function exportFiltersFromRequest(Request $request): array
    {
        $filters = [
            'product_type' => $request->query('product_type'),
            'product_status' => $request->query('product_status'),
            'verification_status' => $request->query('verification_status'),
            'status_group' => $request->query('status_group'),
            'product_filter' => $request->query('product_filter'),
            'badge_id' => $request->query('badge_id'),
            'category_id' => $request->query('category_id'),
            'store_id' => $request->query('store_id'),
            'seller_id' => $request->query('seller_id'),
        ];

        return [$filters, $this->selectedIdsFromRequest($request)];
    }

    private function exportQuery(array $filters, array $selectedIds): Builder
    {
        $query = $this->buildBaseQuery();

        if (! empty($selectedIds)) {
            return $query->whereIn('products.id', $selectedIds);
        }

        return $this->applyFilters($query, '', $filters);
    }

    private function productExportRows(Product $product, array $columns): array
    {
        $rows = [];
        $variants = $product->variants->isNotEmpty() ? $product->variants : collect([null]);
        $isFirstProductRow = true;

        foreach ($variants as $variant) {
            $storeRows = $variant?->storeProductVariants?->isNotEmpty()
                ? $variant->storeProductVariants
                : collect([null]);

            foreach ($storeRows as $storeProductVariant) {
                $payload = $this->productExportPayload($product, $variant, $storeProductVariant, $isFirstProductRow);
                $rows[] = array_map(fn ($column) => $payload[$column] ?? '', $columns);
                $isFirstProductRow = false;
            }
        }

        return $rows;
    }

    private function productExportPayload(Product $product, ?ProductVariant $variant, ?StoreProductVariant $storeProductVariant, bool $includeProductFields): array
    {
        [$customTitles, $customValues] = $this->customFieldExportValues($product);
        $options = $this->variantOptionExportValues($variant);
        $metadata = is_array($product->metadata) ? $product->metadata : [];
        $seoKeywords = $metadata['seo_keywords'] ?? [];
        $tags = is_string($product->tags) ? json_decode($product->tags, true) : $product->tags;

        return [
            'id' => $product->id,
            'handle' => $product->slug ?: $product->id,
            'title' => $includeProductFields ? $product->getTranslation('title', 'en', false) : '',
            'seller_id' => $includeProductFields ? $product->seller_id : '',
            'category_id' => $includeProductFields ? $product->category_id : '',
            'brand_id' => $includeProductFields ? $product->brand_id : '',
            'type' => $includeProductFields ? $product->type : '',
            'image_fit' => $includeProductFields ? $product->image_fit : '',
            // Keep exported image URLs blank so re-imports do not unintentionally replace existing media.
            'image_src' => '',
            'short_description' => $includeProductFields ? $product->getTranslation('short_description', 'en', false) : '',
            'description' => $includeProductFields ? $product->getTranslation('description', 'en', false) : '',
            'base_prep_time' => $includeProductFields ? $product->base_prep_time : '',
            'minimum_order_quantity' => $includeProductFields ? $product->minimum_order_quantity : '',
            'quantity_step_size' => $includeProductFields ? $product->quantity_step_size : '',
            'total_allowed_quantity' => $includeProductFields ? $product->total_allowed_quantity : '',
            'is_returnable' => $includeProductFields ? (int) $product->is_returnable : '',
            'returnable_days' => $includeProductFields ? $product->returnable_days : '',
            'is_cancelable' => $includeProductFields ? (int) $product->is_cancelable : '',
            'cancelable_till' => $includeProductFields ? $product->cancelable_till : '',
            'is_attachment_required' => $includeProductFields ? (int) $product->is_attachment_required : '',
            'featured' => $includeProductFields ? (int) $product->featured : '',
            'requires_otp' => $includeProductFields ? (int) $product->requires_otp : '',
            'video_type' => $includeProductFields ? $product->video_type : '',
            'video_link' => $includeProductFields ? $product->video_link : '',
            'warranty_period' => $includeProductFields ? $product->warranty_period : '',
            'guarantee_period' => $includeProductFields ? $product->guarantee_period : '',
            'made_in' => $includeProductFields ? $product->made_in : '',
            'hsn_code' => $includeProductFields ? $product->hsn_code : '',
            'tags' => $includeProductFields && is_array($tags) ? implode(',', $tags) : '',
            'custom_fields_title' => $includeProductFields ? $customTitles : '',
            'custom_fields_value' => $includeProductFields ? $customValues : '',
            'option1_name' => $options[0]['name'] ?? '',
            'option1_value' => $options[0]['value'] ?? '',
            'option2_name' => $options[1]['name'] ?? '',
            'option2_value' => $options[1]['value'] ?? '',
            'option3_name' => $options[2]['name'] ?? '',
            'option3_value' => $options[2]['value'] ?? '',
            'store_id' => $storeProductVariant?->store_id,
            'variant_sku' => $storeProductVariant?->sku,
            'variant_price' => $storeProductVariant?->price_exclude_tax,
            'variant_special_price' => $storeProductVariant?->special_price_exclude_tax,
            'variant_cost' => $storeProductVariant?->cost,
            'variant_stock' => $storeProductVariant?->stock,
            'variant_barcode' => $variant?->barcode,
            'variant_weight' => $variant?->weight,
            'variant_height' => $variant?->height,
            'variant_length' => $variant?->length,
            'variant_breadth' => $variant?->breadth,
            'variant_availability' => $variant ? ($variant->availability ? 'yes' : 'no') : '',
            'variant_is_default' => $variant ? ($variant->is_default ? 'on' : 'off') : '',
            'seo_title' => $includeProductFields ? ($metadata['seo_title'] ?? '') : '',
            'seo_keywords' => $includeProductFields && is_array($seoKeywords) ? implode(',', $seoKeywords) : '',
            'seo_description' => $includeProductFields ? ($metadata['seo_description'] ?? '') : '',
        ];
    }

    private function customFieldExportValues(Product $product): array
    {
        $customFields = is_array($product->custom_fields) ? $product->custom_fields : [];

        return [
            implode(',', array_keys($customFields)),
            implode(',', array_values($customFields)),
        ];
    }

    private function variantOptionExportValues(?ProductVariant $variant): array
    {
        if (! $variant) {
            return [];
        }

        return $variant->attributes
            ->take(3)
            ->map(fn ($attribute) => [
                'name' => $attribute->attribute?->getTranslation('title', 'en', false) ?? '',
                'value' => $attribute->attributeValue?->getTranslation('title', 'en', false) ?? '',
            ])
            ->values()
            ->all();
    }

    private function productDatatableExportColumns(array $requested): array
    {
        $allowed = [
            'id' => __('labels.id'),
            'product_details' => __('labels.product_details'),
            'admin_approval_status' => __('labels.admin_approval_status'),
            'created_at' => __('labels.created_at'),
        ];

        return collect($requested)
            ->filter(fn ($column) => is_string($column) && isset($allowed[$column]))
            ->mapWithKeys(fn ($column) => [$column => $allowed[$column]])
            ->all();
    }

    private function productDatatableExportRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'product_details' => implode(' | ', array_filter([
                __('labels.title').': '.$product->getTranslation('title', 'en', false),
                __('labels.category').': '.($product->category?->getTranslation('title', 'en', false) ?? ''),
                __('labels.brand').': '.($product->brand?->getTranslation('title', 'en', false) ?? ''),
                __('labels.featured').': '.($product->featured ? 'Yes' : 'No'),
                __('labels.product_type').': '.$product->type,
                __('labels.status').': '.$product->status,
            ])),
            'admin_approval_status' => $product->verification_status,
            'created_at' => optional($product->created_at)->format('Y-m-d'),
        ];
    }

    private function productCsvColumns(): array
    {
        $columns = [
            'id',
            'handle',
            'title',
            'seller_id',
            'category_id',
            'brand_id',
            'type',
            'image_fit',
            'image_src',
            'short_description',
            'description',
            'base_prep_time',
            'minimum_order_quantity',
            'quantity_step_size',
            'total_allowed_quantity',
            'is_returnable',
            'returnable_days',
            'is_cancelable',
            'cancelable_till',
            'is_attachment_required',
            'featured',
            'requires_otp',
            'video_type',
            'video_link',
            'warranty_period',
            'guarantee_period',
            'made_in',
            'hsn_code',
            'tags',
            'custom_fields_title',
            'custom_fields_value',
            'option1_name',
            'option1_value',
            'option2_name',
            'option2_value',
            'option3_name',
            'option3_value',
            'store_id',
            'variant_sku',
            'variant_price',
            'variant_special_price',
            'variant_cost',
            'variant_stock',
            'variant_barcode',
            'variant_weight',
            'variant_height',
            'variant_length',
            'variant_breadth',
            'variant_availability',
            'variant_is_default',
            'seo_title',
            'seo_keywords',
            'seo_description',
        ];

        return $this->getPanel() === 'seller'
            ? array_values(array_filter($columns, fn (string $column) => $column !== 'seller_id'))
            : $columns;
    }

    /**
     * Download CSV template using a Shopify-like format (no JSON fields),
     * with multi-row variant representation and explicit variant columns.
     */
    public function downloadTemplate()
    {
        $this->authorize('viewAny', Product::class);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product_template.csv"',
        ];

        $columns = $this->productCsvColumns();

        // Simple product: single row, default option
        $simpleRow = [
            '',                           // id (blank for create, fill for update)
            'classic-t-shirt',            // handle
            'Classic T-Shirt',            // title
            144,                            // category_id
            '',                            // brand_id
            'simple',                     // type
            'cover',                      // image_fit
            'https://example.com/products/classic-t-shirt.jpg',
            'A timeless classic t-shirt.',
            'High-quality cotton t-shirt suitable for everyday wear.',
            10,                           // base_prep_time (minutes)
            1,                            // minimum_order_quantity
            1,                            // quantity_step_size
            0,                            // total_allowed_quantity (0 = unlimited)
            1,                            // is_returnable
            7,                            // returnable_days
            1,                            // is_cancelable
            'preparing',                  // cancelable_till
            0,                            // is_attachment_required
            1,                            // featured
            0,                            // requires_otp
            '',                           // video_type
            '',                           // video_link
            '6 months',                   // warranty_period
            '',                           // guarantee_period
            'India',                      // made_in
            '6109',                       // hsn_code
            'tshirt,classic,cotton',      // tags

            // custom fields examples (comma-separated pairs by position)
            'Material, Care Instructions',
            'Cotton, Machine wash cold',

            // options (default title pattern)
            'Title',
            'Default Title',
            '',
            '',
            '',
            '',

            // store + variant data
            1,                            // store_id
            'SIMP-RED-001',               // variant_sku
            999.00,                       // variant_price
            899.00,                       // variant_special_price
            600.00,                       // variant_cost
            100,                          // variant_stock
            'BAR-SIMPLE-001',             // variant_barcode
            0.25,                         // variant_weight (kg)
            2,                            // variant_height (cm)
            30,                           // variant_length (cm)
            25,                           // variant_breadth (cm)
            'no',                        // variant_availability
            'on',                         // variant_is_default
            'Classic T-Shirt',            // seo_title
            'tshirt,classic,cotton',      // seo_keywords
            'Premium everyday cotton t-shirt.',
        ];

        // Variant product: multiple rows sharing
        // the same handle
        $variantRow1 = [
            '',                           // id (blank for create, fill for update)
            'premium-polo',               // handle
            'Premium Polo',               // title (only on first row of product)
            145,                            // category_id
            200,                            // brand_id
            'variant',                    // type
            'contain',                    // image_fit
            'https://example.com/products/premium-polo.jpg',
            'A premium polo with multiple variants.',
            'Soft and breathable premium polo shirt available in various sizes and colors.',
            15,                           // base_prep_time
            1,                            // minimum_order_quantity
            1,                            // quantity_step_size
            0,                            // total_allowed_quantity
            1,                            // is_returnable
            10,                           // returnable_days
            1,                            // is_cancelable
            'accepted',                   // cancelable_till
            0,                            // is_attachment_required
            0,                            // featured
            0,                            // requires_otp
            '',                           // video_type
            '',                           // video_link
            '1 year',                     // warranty_period
            '',                           // guarantee_period
            'India',                      // made_in
            '6109',                       // hsn_code
            'polo,premium,cotton',        // tags

            // custom fields examples (comma-separated pairs by position)
            'Material, Care Instructions',
            'Polyester Blend, Do not bleach',

            // options
            'Size',                       // option1_name
            'S',                          // option1_value
            'Color',                      // option2_name
            'Red',                        // option2_value
            '',                           // option3_name
            '',                           // option3_value

            // store + variant data
            1,                            // store_id
            'TS-S-RED-001',               // variant_sku
            1099.00,                      // variant_price
            999.00,                       // variant_special_price
            700.00,                       // variant_cost
            50,                           // variant_stock
            'BAR-VAR-RED-S',              // variant_barcode
            0.22,                         // variant_weight
            2,                            // variant_height
            28,                           // variant_length
            24,                           // variant_breadth
            'yes',                        // variant_availability
            'on',                         // variant_is_default
            'Premium Polo',               // seo_title
            'polo,premium,cotton',        // seo_keywords
            'Premium polo shirt with size and color options.',
        ];

        $variantRow2 = [
            '',                           // id (same product id when updating by id)
            'premium-polo',               // handle (same product)
            '',                           // title (blank on subsequent rows)
            '',                           // category_id
            '',                           // brand_id
            '',                           // type
            '',                           // image_fit
            '',                           // image_src
            '',                           // short_description
            '',                           // description
            '',                           // base_prep_time
            '',                           // minimum_order_quantity
            '',                           // quantity_step_size
            '',                           // total_allowed_quantity
            '',                           // is_returnable
            '',                           // returnable_days
            '',                           // is_cancelable
            '',                           // cancelable_till
            '',                           // is_attachment_required
            '',                           // featured
            '',                           // requires_otp
            '',                           // video_type
            '',                           // video_link
            '',                           // warranty_period
            '',                           // guarantee_period
            '',                           // made_in
            '',                           // hsn_code
            '',                           // tags

            // custom fields left blank on subsequent rows
            '',
            '',

            // options
            'Size',                       // option1_name
            'M',                          // option1_value
            'Color',                      // option2_name
            'Blue',                       // option2_value
            '',                           // option3_name
            '',                           // option3_value

            // store + variant data
            1,                            // store_id
            'TS-M-BLU-001',               // variant_sku
            1199.00,                      // variant_price
            '',                           // variant_special_price (no discount)
            750.00,                       // variant_cost
            40,                           // variant_stock
            'BAR-VAR-BLU-M',              // variant_barcode
            0.24,                         // variant_weight
            2,                            // variant_height
            29,                           // variant_length
            24,                           // variant_breadth
            'yes',                        // variant_availability
            'off',                        // variant_is_default
            '',                           // seo_title
            '',                           // seo_keywords
            '',                           // seo_description
        ];

        // Admin templates expose seller_id, while seller templates resolve seller ownership from the session.
        if ($this->getPanel() === 'admin') {
            array_splice($simpleRow, 3, 0, 1);
            array_splice($variantRow1, 3, 0, 1);
            array_splice($variantRow2, 3, 0, '');
        }

        $callback = function () use ($columns, $simpleRow, $variantRow1, $variantRow2) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns);
            fputcsv($output, $simpleRow);
            fputcsv($output, $variantRow1);
            fputcsv($output, $variantRow2);
            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
