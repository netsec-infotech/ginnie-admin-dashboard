<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Store\StoreStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\StoreService;
use App\Traits\SubscriptionLimitGuard;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

#[Group('Seller Stores')]
class SellerStoreApiController extends Controller
{
    use AuthorizesRequests, SubscriptionLimitGuard;

    public function __construct(protected StoreService $storeService) {}

    /**
     * List stores for the authenticated seller with pagination and search
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of stores per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search query for name/description/address', type: 'string', example: 'Downtown')]
    #[QueryParameter('status', description: 'Filter by store status', type: 'string', example: 'online/offline')]
    #[QueryParameter('visibility_status', description: 'Filter by store visibility status', type: 'string', example: 'visible/draft')]
    #[QueryParameter('verification_status', description: 'Filter by store verification status', type: 'string', example: 'approved/not_approved')]
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorization via StorePolicy@viewAny
            $this->authorize('viewAny', Store::class);

            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $perPage = (int) $request->input('per_page', 15);
            $q = trim((string) $request->input('search', ''));

            $filters = $request->only(['status', 'visibility_status', 'verification_status']);

            $paginator = $this->storeService->listForSeller($seller, $q, $perPage, $filters);
            // Transform collection to follow store response standard
            $paginator->getCollection()->transform(fn ($store) => new StoreResource($store));

            $response = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => $paginator->items(),
            ];

            return ApiResponseType::sendJsonResponse(true, __('labels.stores_fetched_successfully'), $response);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller stores index error: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, __('labels.error_fetching_stores'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a single store owned by the authenticated seller
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('view', $store);

            return ApiResponseType::sendJsonResponse(true, __('labels.store_fetched_successfully'), new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.store_not_found') ?? 'Store not found', [], 404);
        }
    }

    /**
     * Create a new store for seller
     */
    public function store(StoreStoreRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Store::class);
            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            // Pre-check subscription usage for store_limit (multivendor only)
            if ($error = $this->ensureCanUseOrError($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT())) {
                return $error;
            }
            $store = $this->storeService->createForSeller($request, $seller);

            // Update usage after create (multivendor only)
            $this->recordUsageIfMultivendor($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT());

            return ApiResponseType::sendJsonResponse(true, __('labels.store_created_successfully') ?? 'Store created successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.error_creating_store') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an owned store
     */
    public function update(UpdateStoreRequest $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $store = $this->storeService->updateForSeller($request, $store);

            return ApiResponseType::sendJsonResponse(true, __('labels.store_updated_successfully') ?? 'Store updated successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.error_updating_store') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete an owned store
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('delete', $store);

            $this->storeService->deleteForSeller($store);

            // Reduce usage after delete (multivendor only)
            $this->reduceUsageIfMultivendor($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT());

            return ApiResponseType::sendJsonResponse(true, __('labels.store_deleted_successfully') ?? 'Store deleted successfully', []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.error_deleting_store') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update online/offline status for an owned store
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:online,offline',
            ]);

            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $store = $this->storeService->updateStatusForSeller($store, $request->string('status'));

            return ApiResponseType::sendJsonResponse(true, __('labels.store_status_updated_successfully') ?? 'Store status updated successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.store_status_update_failed') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preview the impact of operating hour changes on future scheduled orders
     */
    public function previewTimingImpact(Request $request, int $id): JsonResponse
    {
        try {
            $operatingHours = $request->input('operating_hours');
            if (! is_array($operatingHours)) {
                return ApiResponseType::sendJsonResponse(false, 'Operating hours must be an array', [], 422);
            }

            foreach ($operatingHours as $day => $config) {
                if (! in_array(strtolower($day), ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])) {
                    return ApiResponseType::sendJsonResponse(false, "Invalid day key in operating hours: {$day}", [], 422);
                }
                if (! isset($config['is_open'])) {
                    return ApiResponseType::sendJsonResponse(false, "Missing is_open flag for {$day}", [], 422);
                }
                $isOpen = filter_var($config['is_open'], FILTER_VALIDATE_BOOLEAN);
                if ($isOpen) {
                    if (empty($config['open_time']) || empty($config['close_time'])) {
                        return ApiResponseType::sendJsonResponse(false, "Open and close times are required when store is open on {$day}", [], 422);
                    }
                }
            }

            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $count = $this->storeService->previewTimingImpact($store, $operatingHours);

            return ApiResponseType::sendJsonResponse(true, 'Timing impact calculated successfully', [
                'impacted_orders_count' => $count,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Update store operating hours and flag affected scheduled orders
     */
    public function updateTimings(Request $request, int $id): JsonResponse
    {
        try {
            $operatingHours = $request->input('operating_hours');
            if (! is_array($operatingHours)) {
                return ApiResponseType::sendJsonResponse(false, 'Operating hours must be an array', [], 422);
            }

            foreach ($operatingHours as $day => $config) {
                if (! in_array(strtolower($day), ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])) {
                    return ApiResponseType::sendJsonResponse(false, "Invalid day key in operating hours: {$day}", [], 422);
                }
                if (! isset($config['is_open'])) {
                    return ApiResponseType::sendJsonResponse(false, "Missing is_open flag for {$day}", [], 422);
                }
                $isOpen = filter_var($config['is_open'], FILTER_VALIDATE_BOOLEAN);
                if ($isOpen) {
                    if (empty($config['open_time']) || empty($config['close_time'])) {
                        return ApiResponseType::sendJsonResponse(false, "Open and close times are required when store is open on {$day}", [], 422);
                    }
                }
            }

            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $this->storeService->updateTimings($store, $operatingHours);

            $store->refresh();

            return ApiResponseType::sendJsonResponse(true, 'Store timings updated successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Toggle store temporary closure status and flag future orders
     */
    public function toggleTemporaryClosure(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'is_temporarily_closed' => 'required|boolean',
                'temporary_closure_reason' => 'required_if:is_temporarily_closed,true,1|nullable|string',
            ]);

            $user = auth()->user();
            $seller = $user?->seller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $this->storeService->toggleTemporaryClosure(
                $store,
                $request->boolean('is_temporarily_closed'),
                $request->input('temporary_closure_reason')
            );

            $store->refresh();

            return ApiResponseType::sendJsonResponse(true, 'Store closure status updated successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Get all store enums
     */
    public function getStoresEnums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'stores enums fetched successfully',
            data: [
                'status' => StoreStatusEnum::values(),
                'visibility_status' => StoreVisibilityStatusEnum::values(),
                'verification_status' => StoreVerificationStatusEnum::values(),
            ]
        );
    }
}
