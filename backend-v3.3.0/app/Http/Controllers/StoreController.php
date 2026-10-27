<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\BankAccountTypeEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Events\Store\StoreConfigUpdated;
use App\Events\Store\StoreCreated;
use App\Events\Store\StoreUpdated;
use App\Events\Store\StoreVerificationUpdate;
use App\Http\Requests\Seller\StoreStoreConfigurationRequest;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Requests\Store\VerifyStoreRequest;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Services\DeliveryZoneService;
use App\Services\SpatieMediaService;
use App\Services\StoreService;
use App\Services\SubscriptionUsageService;
use App\Support\Currency\CurrencyDisplay;
use App\Support\Currency\ZoneCurrencyResolver;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StoreController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware, SubscriptionLimitGuard;

    protected bool $editPermission = true;

    protected bool $deletePermission = true;

    protected bool $createPermission = true;

    protected bool $viewPermission = true;

    protected bool $verifyPermission = true;

    public int $sellerId;

    /**
     * @throws AuthorizationException
     */
    public function __construct()
    {
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;

        if ($this->getPanel() === 'seller' || Setting::isSystemVendorTypeSingle()) {
            $this->editPermission = $this->hasPermission(SellerPermissionEnum::STORE_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->deletePermission = $this->hasPermission(SellerPermissionEnum::STORE_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->createPermission = $this->hasPermission(SellerPermissionEnum::STORE_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->viewPermission = $this->hasPermission(SellerPermissionEnum::STORE_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        }
        if ($this->getPanel() === 'admin') {
            $this->verifyPermission = $this->hasPermission(AdminPermissionEnum::STORE_VERIFY());
        }
    }

    public function index($id = null, Request $request = null): View
    {
        $this->authorize('viewAny', Store::class);
        $request = $request ?? request();
        $seller = null;
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
            ['data' => 'city', 'name' => 'city', 'title' => __('labels.city')],
            ['data' => 'contact_number', 'name' => 'contact_number', 'title' => __('labels.contact_number')],
            ['data' => 'verification_status', 'name' => 'verification_status', 'title' => __('labels.verification_status')],
            ['data' => 'visibility_status', 'name' => 'visibility_status', 'title' => __('labels.visibility_status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];
        if ($this->getPanel() === 'seller') {
            $columns[] = ['data' => 'store_configuration', 'name' => 'store_configuration', 'title' => __('labels.store_configuration')];
            $columns[] = ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false];
        } else {
            $columns[] = ['data' => 'recommended', 'name' => 'recommended', 'title' => __('labels.recommended')];
            $columns[] = ['data' => 'seller_name', 'name' => 'seller_name', 'title' => __('labels.seller_name')];
            $columns[] = ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions')];
            if ($id != null) {
                $seller = Seller::findOrFail($id);
            }
        }
        $verificationStatus = StoreVerificationStatusEnum::values();
        $visibilityStatus = StoreVisibilityStatusEnum::values();

        // Add additional variables as in your example if needed
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $storeCreateLimitReached = false;
        $storeCreateLimitMessage = null;

        if (
            $this->getPanel() === 'seller'
            && $createPermission
            && Setting::isSystemVendorTypeMultiple()
            && Setting::isSubscriptionEnabled()
        ) {
            $limitKey = SubscriptionPlanKeyEnum::STORE_LIMIT();
            $usageService = app(SubscriptionUsageService::class);
            $limit = $usageService->getLimit((int) $this->sellerId, $limitKey);
            $used = $usageService->getUsage((int) $this->sellerId, $limitKey);

            if ($limit >= 0 && $used >= $limit) {
                $storeCreateLimitReached = true;
                $storeCreateLimitMessage = __('labels.subscription_limit_exceeded', [
                    'key' => Str::ucfirst(Str::replace('_', ' ', $limitKey)),
                    'limit' => $limit,
                    'used' => $used,
                    'remaining' => max(0, $limit - $used),
                ]);
            }
        }

        $panel = $this->getPanel();
        $storeInsights = $this->getStoreInsights($id ? (int) $id : null);

        $activeStatusGroup = $this->normalizeStatusGroup($request->query('status_group'), $panel);

        return view($this->panelView('stores.index'), compact(
            'columns',
            'editPermission',
            'deletePermission',
            'createPermission',
            'seller',
            'verificationStatus',
            'visibilityStatus',
            'storeCreateLimitReached',
            'storeCreateLimitMessage',
            'storeInsights',
            'activeStatusGroup'
        ));
    }

    /**
     * @throws AuthorizationException
     */
    public function getStores(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Store::class);
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';
        $sellerId = $request->get('seller_id');
        $visibilityStatus = $request->get('visibility_status');
        $verificationStatus = $request->get('verification_status');
        $recommendedStatus = $request->get('recommended_status');

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';
        $columns = ['id', 'name', 'city', 'contact_number', 'verification_status', 'visibility_status', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = Store::query()->with('seller.user');

        $statusGroup = $request->get('status_group');
        if ($visibilityStatus !== null && $visibilityStatus !== '') {
            $query->where('visibility_status', $visibilityStatus);
        }
        if ($verificationStatus !== null && $verificationStatus !== '') {
            $query->where('verification_status', $verificationStatus);
        }
        if ($recommendedStatus !== null && $recommendedStatus !== '') {
            $query->where('is_recommended', filter_var($recommendedStatus, FILTER_VALIDATE_BOOLEAN));
        }
        if (
            ($verificationStatus === null || $verificationStatus === '')
            && ($recommendedStatus === null || $recommendedStatus === '')
            && $statusGroup
        ) {
            if ($normalizedGroup = $this->normalizeStatusGroup($statusGroup, $this->getPanel())) {
                self::applyInsightStatusGroup($query, $normalizedGroup, $this->getPanel());
            }
        }
        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $seller = $user->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Seller not found');
            }
            $query->where('seller_id', $seller->id);
        } elseif ($sellerId) {
            // Check if sellerId contains multiple IDs (comma-separated)
            if (strpos($sellerId, ',') !== false) {
                $sellerIds = explode(',', $sellerId);
                $query->whereIn('seller_id', $sellerIds);
            } else {
                $query->where('seller_id', $sellerId);
            }
        }

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%$searchValue%")
                    ->orWhere('city', 'like', "%$searchValue%")
                    ->orWhere('contact_number', 'like', "%$searchValue%")
                    ->orWhereHas('seller.user', function ($q) use ($searchValue) {
                        $q->where('name', 'like', "%$searchValue%");
                    });
            });
        }

        $totalRecords = $filteredRecords = $query->count();

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($store) use ($editPermission, $deletePermission) {
                $verStatus = $store->verification_status?->value ?? (string) $store->verification_status;
                $visStatus = $store->visibility_status?->value ?? (string) $store->visibility_status;
                $statusVal = $store->status?->value ?? (string) $store->status;

                $requiresAction = $verStatus === 'not_approved' || $statusVal === 'draft' || $visStatus === 'draft';
                $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="' . e(__('labels.action_required') ?? 'Action Required') . '"></span>' : '';
                $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">' . $actionDot . '<span>' . e($store->id) . '</span></div>' : $store->id;

                $data = [
                    'id' => $formattedId,
                    'name' => $store->name,
                    'city' => $store->city,
                    'contact_number' => $store->contact_number,
                    'verification_status' => view('partials.status', [
                        'status' => $store->verification_status->value,
                    ])->render(),
                    'visibility_status' => view('partials.status', [
                        'status' => $store->visibility_status->value,
                    ])->render(),
                    'created_at' => $store->created_at->format('Y-m-d'),
                ];
                if ($this->getPanel() === 'seller') {
                    $data += [
                        'store_configuration' => view('seller.stores.partials.store-config-button', ['store' => $store])->render(),
                        'action' => view('partials.actions', [
                            'modelName' => 'store',
                            'id' => $store->id,
                            'title' => $store->name,
                            'mode' => 'page_view',
                            'route' => route($this->panelView('stores.edit'), $store->id),
                            'editPermission' => $editPermission,
                            'deletePermission' => $deletePermission,
                        ])->render(),
                    ];
                } else {
                    $data += [
                        'recommended' => $store->is_recommended
                            ? '<span class="badge bg-info-lt">' . e(__('labels.recommended')) . '</span>'
                            : '<span class="text-secondary">-</span>',
                        'seller_name' => $store->seller->user->name ?? '',
                        'actions' => view('admin.stores.partials.actions', ['store' => $store])->render(),
                    ];
                }

                return $data;
            })
            ->toArray();

        $sellerIdForInsights = null;
        if ($sellerId) {
            if (is_array($sellerId)) {
                $sellerIdForInsights = $sellerId;
            } elseif (strpos($sellerId, ',') !== false) {
                $sellerIdForInsights = explode(',', $sellerId);
            } else {
                $sellerIdForInsights = (int) $sellerId;
            }
        }

        $storeInsights = $this->getStoreInsights($sellerIdForInsights);

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
            'store_insights' => $storeInsights,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function create(): View
    {
        $this->authorize('create', Store::class);
        $bankAccountTypes = BankAccountTypeEnum::values();
        $setting = Setting::find(SettingTypeEnum::WEB());
        $googleApiKey = $setting->value['googleMapKey'] ?? null;

        return view($this->panelView('stores.form'), compact('bankAccountTypes', 'googleApiKey'));
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Store::class);
            DB::beginTransaction();
            $request->validated();
            $user = auth()->user();
            $seller = $user->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Seller not found');
            }
            $validated = $request->safe()->except('address_proof', 'voided_check');
            $validated['seller_id'] = $seller->id;
            $isExistInZone = DeliveryZoneService::getZonesAtPoint($validated['latitude'], $validated['longitude']);
            if ($isExistInZone['exists'] === false) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(success: false, message: 'Store location is not within any delivery zone');
            }
            $zone = $this->zoneForStoreLocation($isExistInZone);
            if (! $zone || ! $zone->country || blank($zone->currency_code)) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(false, 'Store location zone is missing country or currency configuration.', [], 422);
            }
            $validated = app(ZoneCurrencyResolver::class)->applyStoreCurrencyFromZone($validated, $zone->country, $zone->currency_code);
            $validated['verification_status'] = StoreVerificationStatusEnum::NOT_APPROVED();
            $validated['visibility_status'] = StoreVisibilityStatusEnum::DRAFT();

            // Pre-check subscription usage for store_limit only when multivendor
            if ($error = $this->ensureCanUseOrError($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT())) {
                DB::rollBack();

                return $error;
            }

            // Create store after passing limit checks
            $store = Store::create($validated);

            // Update usage after adding (multivendor only)
            $this->recordUsageIfMultivendor($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT());
            if ($zone) {
                $store->zones()->sync([$zone->id]);
            }
            if ($request->hasFile('store_logo')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::STORE_BANNER());
            }
            if ($request->hasFile('address_proof')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::ADDRESS_PROOF());
            }
            if ($request->hasFile('voided_check')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::VOIDED_CHECK());
            }
            event(new StoreCreated($store));
            DB::commit();
            $redirectUrl = $this->getPanel() === 'admin'
                ? route('admin.sellers.index')
                : route('seller.stores.index');

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Store created successfully',
                data: array_merge($store->toArray(), ['redirect_url' => $redirectUrl])
            );
        } catch (AuthorizationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(success: false, message: 'Unauthorized action: ' . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(success: false, message: 'Error creating store: ' . $e->getMessage());
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function edit($id): View
    {
        $store = Store::findOrFail($id);
        $this->authorize('update', $store);
        $bankAccountTypes = BankAccountTypeEnum::values();
        $setting = Setting::find(SettingTypeEnum::WEB());
        $googleApiKey = $setting->value['googleMapKey'] ?? null;

        return view($this->panelView('stores.form'), compact('bankAccountTypes', 'store', 'googleApiKey'));
    }

    public function show($id): View
    {
        $store = Store::with('zones')->findOrFail($id);
        // Viewing a store should use the 'view' ability; verifying is handled in verify()
        $this->authorize('view', $store);
        $verificationStatus = StoreVerificationStatusEnum::values();
        $visibilityStatus = StoreVisibilityStatusEnum::values();
        $verifyPermission = $this->verifyPermission;

        return view($this->panelView('stores.view'), compact('store', 'verificationStatus', 'visibilityStatus', 'verifyPermission'));
    }

    public function update(UpdateStoreRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $store = Store::findOrFail($id);
            $validated = $request->safe()->except('address_proof', 'voided_check');
            $isExistInZone = DeliveryZoneService::getZonesAtPoint($validated['latitude'], $validated['longitude']);
            if ($isExistInZone['exists'] === false) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(success: false, message: 'Store location is not within any delivery zone');
            }
            $zone = $this->zoneForStoreLocation($isExistInZone);
            if (! $zone || ! $zone->country || blank($zone->currency_code)) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(false, 'Store location zone is missing country or currency configuration.', [], 422);
            }
            $validated = app(ZoneCurrencyResolver::class)->applyStoreCurrencyFromZone($validated, $zone->country, $zone->currency_code);
            $this->authorize('update', $store);

            if ($zone) {
                $currentZoneIds = $store->zones()->pluck('delivery_zones.id')->map(fn ($zoneId) => (int) $zoneId)->all();
                $newZoneId = (int) $zone->id;
                $currencyChanges = strtoupper((string) $store->currency_code) !== strtoupper((string) $zone->currency_code);

                if ($currencyChanges && $this->storeHasFinancialHistory($store)) {
                    DB::rollBack();

                    return ApiResponseType::sendJsonResponse(false, 'Store currency cannot be changed because financial history already exists for this store.', [], 422);
                }

                if ($this->getPanel() === 'seller' && ! in_array($newZoneId, $currentZoneIds, true) && $this->storeHasOrdersInZones($store, $currentZoneIds)) {
                    DB::rollBack();

                    return ApiResponseType::sendJsonResponse(false, 'Store zone cannot be updated because orders already exist in the current zone.', [], 422);
                }
            }

            $store->update($validated);
            if ($request->hasFile('store_logo')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_BANNER());
            }
            if ($request->hasFile('address_proof')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::ADDRESS_PROOF());
            }
            if ($request->hasFile('voided_check')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::VOIDED_CHECK());
            }
            if ($zone) {
                $store->zones()->sync([$zone->id]);
            }
            event(new StoreUpdated($store));
            DB::commit();
            $redirectUrl = $this->getPanel() === 'admin'
                ? route('admin.sellers.index')
                : route('seller.stores.index');

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Store updated successfully',
                data: array_merge($store->toArray(), ['redirect_url' => $redirectUrl])
            );
        } catch (AuthorizationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'Error updating store: ' . $e->getMessage(), []);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $store = Store::findOrFail($id);
            $this->authorize('delete', $store);
            $sellerId = $store->seller_id;

            if ($this->getPanel() === 'seller') {
                $currentZoneIds = $store->zones()->pluck('delivery_zones.id')->map(fn ($zoneId) => (int) $zoneId)->all();

                if ($this->storeHasOrdersInZones($store, $currentZoneIds)) {
                    DB::rollBack();

                    return ApiResponseType::sendJsonResponse(false, 'Store cannot be deleted because orders already exist in its zone.', [], 422);
                }
            }

            $storeService = app(StoreService::class);
            $storeService->deleteForSeller($store);

            // Reduce subscription usage for store_limit only when multivendor
            if ($sellerId) {
                $this->reduceUsageIfMultivendor((int) $sellerId, SubscriptionPlanKeyEnum::STORE_LIMIT());
            }
            DB::commit();

            return ApiResponseType::sendJsonResponse(true, __('labels.store_deleted_successfully') ?? 'Store deleted successfully', []);
        } catch (AuthorizationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'Error deleting store: ' . $e->getMessage(), []);
        }
    }

    public function configuration($id): View
    {
        $store = Store::find($id);
        if (! $store) {
            abort(404, 'Store not found');
        }
        $this->authorize('view', $store);

        return view($this->panelView('stores.configuration'), compact('store'));
    }

    public function storeConfiguration(StoreStoreConfigurationRequest $request, $id): JsonResponse
    {
        try {
            $store = Store::findOrFail($id);
            $this->authorize('update', $store);
            $validated = $request->validated();
            $validated['max_delivery_distance'] = 0.00;

            if (! Setting::isScheduledDeliveryEnabled()) {
                $validated['is_scheduled_delivery_enabled'] = false;
                unset(
                    $validated['scheduled_delivery_buffer_minutes'],
                    $validated['max_advance_booking_days'],
                    $validated['scheduled_delivery_slot_duration'],
                    $validated['scheduled_delivery_slot_capacity'],
                    $validated['prep_concurrency'],
                    $validated['reschedule_deadline_hours']
                );
            }

            // Trigger timings and closure service methods if they changed to propagate reschedule status
            $storeService = app(StoreService::class);
            $newHours = $validated['metadata']['operating_hours'] ?? null;
            $oldHours = $store->metadata['operating_hours'] ?? null;
            if ($newHours && serialize($newHours) !== serialize($oldHours)) {
                $storeService->updateTimings($store, $newHours);
            }

            $newClosed = filter_var($validated['is_temporarily_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $oldClosed = (bool) $store->is_temporarily_closed;
            if ($newClosed !== $oldClosed || ($newClosed && ($validated['temporary_closure_reason'] ?? '') !== $store->temporary_closure_reason)) {
                $storeService->toggleTemporaryClosure($store, $newClosed, $validated['temporary_closure_reason'] ?? null);
            }

            $store->update($validated);
            event(new StoreConfigUpdated($store));

            return ApiResponseType::sendJsonResponse(true, 'Store Configuration updated successfully', ['store' => $store]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'Error updating store configuration: ' . $e->getMessage(), []);
        }
    }

    public function verify(VerifyStoreRequest $request, $id): JsonResponse
    {
        try {
            $this->authorize('verifyStore', Store::class);
            $store = Store::findOrFail($id);
            $validated = $request->validated();
            if (($validated['verification_status'] ?? null) !== StoreVerificationStatusEnum::APPROVED()) {
                if (! empty($validated['is_recommended'])) {
                    return ApiResponseType::sendJsonResponse(
                        false,
                        'Please approve the store before marking it as recommended',
                        []
                    );
                }
                $validated['visibility_status'] = StoreVisibilityStatusEnum::DRAFT();
                $validated['is_recommended'] = false;
            }
            $store->update($validated);
            event(new StoreVerificationUpdate($store));

            return ApiResponseType::sendJsonResponse(true, 'Store verified successfully', [
                'store' => $store,
                'redirect_url' => route('admin.sellers.store.index'),
                'store_insights' => $this->getStoreInsights(),
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'Error updating store configuration: ' . $e->getMessage(), []);
        }
    }

    public function toggleRecommended($id): JsonResponse
    {
        try {
            $this->authorize('verifyStore', Store::class);

            $store = Store::findOrFail($id);

            if (($store->verification_status?->value ?? $store->verification_status) !== StoreVerificationStatusEnum::APPROVED()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'Please approve the store before marking it as recommended',
                    []
                );
            }

            $store->is_recommended = ! $store->is_recommended;
            $store->save();

            return ApiResponseType::sendJsonResponse(
                true,
                $store->is_recommended
                    ? 'Store marked as recommended successfully'
                    : 'Store removed from recommended successfully',
                [
                    'store' => $store,
                    'store_insights' => $this->getStoreInsights($this->getPanel() === 'seller' ? $store->seller_id : null),
                ]
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'Error updating store recommendation: ' . $e->getMessage(), []);
        }
    }

    public function StoreList(Request $request): JsonResponse
    {
        $sellerId = null;

        // Admin acts on behalf of a seller — picker passes seller_id explicitly.
        if ($this->getPanel() === 'admin') {
            $sellerId = $request->query('seller_id');
            if (! is_numeric($sellerId)) {
                return ApiResponseType::sendJsonResponse(success: true, message: 'Stores retrieved successfully', data: []);
            }
            $sellerId = (int) $sellerId;
        } else {
            $seller = auth()->user()?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Seller ID is required');
            }
            $sellerId = (int) $seller->id;
        }

        $stores = Store::query()
            ->where('seller_id', $sellerId)
            ->get()
            ->map(function ($store) {
                $currencyCode = strtoupper((string) ($store->currency_code ?? ''));

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'city' => $store->city,
                    'contact_number' => $store->contact_number,
                    'verification_status' => $store->verification_status,
                    'visibility_status' => $store->visibility_status,
                    'currency_code' => $currencyCode,
                    'currency_symbol' => CurrencyDisplay::label($currencyCode),
                ];
            });

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Stores retrieved successfully',
            data: $stores
        );
    }

    /**
     * Update a store's online/offline status (seller API)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:online,offline',
            ]);

            $store = Store::findOrFail($id);

            // Ensure the authenticated seller owns this store
            $seller = auth()->user()->seller();
            if (! $seller || (int) $store->seller_id !== (int) $seller->id) {
                throw new AuthorizationException('You do not own this store.');
            }

            $this->authorize('update', $store);

            $store->status = $request->input('status');
            $store->save();

            return ApiResponseType::sendJsonResponse(true, __('messages.store_status_updated_successfully'), [
                'store' => $store,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, __('messages.unauthorized_action'), [
                'error' => $e->getMessage(),
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('messages.store_not_found') ?? 'Store not found', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, __('messages.store_status_update_failed') ?? ('Error: ' . $e->getMessage()), [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('search') ?? $request->input('q');
        $findId = $request->input('find_id');

        $query = Store::query()->select(['id', 'name']);

        // Scope by panel: sellers should only see their stores
        if ($this->getPanel() === 'seller') {
            $seller = auth()->user()?->seller();
            if ($seller) {
                $query->where('seller_id', $seller->id);
            } else {
                return response()->json([]);
            }
        }

        if (! empty($findId)) {
            $query->where('id', $findId);
        } elseif (! empty($q)) {
            $query->where('name', 'like', "%{$q}%");
        } else {
            // If no query, limit results to avoid heavy response
            $query->limit(20);
        }

        $stores = $query->orderBy('name')->get();

        $results = $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'value' => $store->id,
                'text' => $store->name,
            ];
        });

        return response()->json($results);
    }

    public function getStoreInsights(int|array|null $sellerId = null): array
    {
        $insightsQuery = Store::query();
        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $seller = $user?->seller();
            if ($seller) {
                $insightsQuery->where('seller_id', $seller->id);
            } elseif ($this->sellerId) {
                $insightsQuery->where('seller_id', $this->sellerId);
            }
        } elseif ($sellerId) {
            if (is_array($sellerId)) {
                $insightsQuery->whereIn('seller_id', $sellerId);
            } else {
                $insightsQuery->where('seller_id', $sellerId);
            }
        }

        $panel = $this->getPanel();

        return [
            'total_stores' => (clone $insightsQuery)->count(),
            'approved_stores' => self::applyInsightStatusGroup(clone $insightsQuery, 'approved', $panel)->count(),
            'pending_stores' => self::applyInsightStatusGroup(clone $insightsQuery, 'pending', $panel)->count(),
            'rejected_stores' => self::applyInsightStatusGroup(clone $insightsQuery, 'rejected', $panel)->count(),
            'recommended_stores' => self::applyInsightStatusGroup(clone $insightsQuery, 'recommended', $panel)->count(),
            'not_approved_stores' => self::applyInsightStatusGroup(clone $insightsQuery, 'not_approved', $panel)->count(),
        ];
    }

    public static function applyInsightStatusGroup(Builder $query, ?string $statusGroup, string $panel = 'admin'): Builder
    {
        return match ($statusGroup) {
            'approved' => $query->where('verification_status', StoreVerificationStatusEnum::APPROVED()),
            'recommended' => $query->where('is_recommended', true),
            'pending' => $query->where(function (Builder $q) {
                $q->whereNull('verification_status')
                    ->orWhere('verification_status', '')
                    ->orWhere('verification_status', 'pending');
            }),
            'rejected' => $query->where('verification_status', StoreVerificationStatusEnum::NOT_APPROVED()),
            'not_approved' => $query->where(function (Builder $q) {
                $q->where('verification_status', StoreVerificationStatusEnum::NOT_APPROVED())
                    ->orWhereNull('verification_status')
                    ->orWhere('verification_status', '');
            }),
            default => $query,
        };
    }

    private function normalizeStatusGroup(mixed $statusGroup, string $panel = 'admin'): ?string
    {
        if (! is_string($statusGroup)) {
            return null;
        }

        $valid = $panel === 'seller'
            ? ['approved', 'pending', 'rejected']
            : ['approved', 'recommended', 'not_approved'];

        return in_array($statusGroup, $valid, true) ? $statusGroup : null;
    }

    private function storeHasOrdersInZones(Store $store, array $zoneIds): bool
    {
        if (empty($zoneIds)) {
            return false;
        }

        return Order::query()
            ->whereIn('delivery_zone_id', $zoneIds)
            ->whereHas('items', fn (Builder $query) => $query->where('store_id', $store->id))
            ->exists();
    }

    private function storeHasFinancialHistory(Store $store): bool
    {
        return Order::query()
            ->whereHas('items', fn (Builder $query) => $query->where('store_id', $store->id))
            ->exists()
            || $store->productVariants()->exists()
            || $store->addonItems()->exists();
    }

    /**
     * @param  array<string, mixed>  $zoneLookup
     */
    private function zoneForStoreLocation(array $zoneLookup): ?DeliveryZone
    {
        if (empty($zoneLookup['zone_id'])) {
            return null;
        }

        return DeliveryZone::query()->with('country')->find((int) $zoneLookup['zone_id']);
    }
}
