<?php

namespace App\Services;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Events\Store\StoreCreated;
use App\Events\Store\StoreUpdated;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Models\Country;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class StoreService
{
    public function listForSeller(Seller $seller, ?string $search, int $perPage = 15, $filters = []): LengthAwarePaginator
    {
        $query = Store::query()
            ->where('seller_id', $seller->id)
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['visibility_status'])) {
            $query->where('visibility_status', $filters['visibility_status']);
        }
        if (! empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if ($search !== null && trim($search) !== '') {
            $q = trim($search);
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%")
                    ->orWhere('address', 'like', "%$q%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a store for seller using validated request data.
     */
    public function createForSeller(StoreStoreRequest $request, Seller $seller): Store
    {
        return DB::transaction(function () use ($request, $seller) {
            $validated = $request->safe()->except('address_proof', 'voided_check');
            $validated['seller_id'] = $seller->id;

            $isInZone = DeliveryZoneService::getZonesAtPoint($validated['latitude'], $validated['longitude']);
            if ($isInZone['exists'] === false) {
                throw new \RuntimeException('Store location is not within any delivery zone');
            }

            $country = Country::where('name', $validated['country'])->firstOrFail();
            if (! empty($country->phonecode)) {
                $validated['country_code'] = $country->phonecode;
                $validated['currency_code'] = $country->currency;
            }

            $validated['verification_status'] = StoreVerificationStatusEnum::NOT_APPROVED();
            $validated['visibility_status'] = StoreVisibilityStatusEnum::DRAFT();

            $store = Store::create($validated);

            if (! empty($isInZone['zone_id'])) {
                $store->zones()->sync([$isInZone['zone_id']]);
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

            return $store;
        });
    }

    /**
     * Update an existing seller-owned store.
     */
    public function updateForSeller(UpdateStoreRequest $request, Store $store): Store
    {
        return DB::transaction(function () use ($request, $store) {
            $validated = $request->validated();

            $isInZone = DeliveryZoneService::getZonesAtPoint($validated['latitude'], $validated['longitude']);
            if ($isInZone['exists'] === false) {
                throw new \RuntimeException('Store location is not within any delivery zone');
            }

            $country = Country::where('name', $validated['country'])->firstOrFail();
            if (! empty($country->phonecode)) {
                $validated['country_code'] = $country->phonecode;
                $validated['currency_code'] = $country->currency;
            }

            $store->update($validated);

            if (! empty($isInZone['zone_id'])) {
                $store->zones()->sync([$isInZone['zone_id']]);
            }

            if ($request->hasFile('store_logo')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_BANNER());
            }

            event(new StoreUpdated($store));

            return $store;
        });
    }

    public function deleteForSeller(Store $store): void
    {
        $store->delete();
    }

    public function updateStatusForSeller(Store $store, string $status): Store
    {
        $store->status = $status;
        $store->save();

        return $store;
    }

    /**
     * Preview timing change impact on future scheduled orders.
     * Calculates the count of orders that fall outside proposed operating hours or on closed days.
     */
    public function previewTimingImpact(Store $store, array $newOperatingHours): int
    {
        $futureOrders = Order::whereHas('items', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })
            ->whereDate('scheduled_date', '>=', today()->format('Y-m-d'))
            ->whereNotIn('status', [
            OrderStatusEnum::CANCELLED(),
            OrderStatusEnum::REJECTED_BY_SELLER(),
            OrderStatusEnum::FAILED(),
        ])
            ->get();

        $affectedCount = 0;

        foreach ($futureOrders as $order) {
            $dayName = strtolower(Carbon::parse($order->scheduled_date)->format('l'));
            $dayConfig = $newOperatingHours[$dayName] ?? null;

            $isOpen = $dayConfig && isset($dayConfig['is_open']) && filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN);

            // If day is closed, all scheduled orders on that day are affected
            if (! $isOpen) {
                $affectedCount++;

                continue;
            }

            $orderStart = $order->scheduled_slot_start_time;
            $orderEnd = $order->scheduled_slot_end_time;
            if ($orderStart && $orderEnd) {
                $openTime = $dayConfig['open_time'] ?? null;
                $closeTime = $dayConfig['close_time'] ?? null;
                // If the scheduled slot starts before opening time or ends after closing time
                if (! $openTime || ! $closeTime || $orderStart < $openTime || $orderEnd > $closeTime) {
                    $affectedCount++;
                } else {
                    $hasBreak = ! empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
                    if ($hasBreak) {
                        $breakStart = $dayConfig['break_start_time'] ?? null;
                        $breakEnd = $dayConfig['break_end_time'] ?? null;
                        if ($breakStart && $breakEnd && $orderStart < $breakEnd && $orderEnd > $breakStart) {
                            $affectedCount++;
                        }
                    }
                }
            }
        }

        return $affectedCount;
    }

    /**
     * Update store operating hours and flag affected future scheduled orders for rescheduling.
     */
    public function updateTimings(Store $store, array $newOperatingHours): void
    {
        DB::transaction(function () use ($store, $newOperatingHours) {
            $futureOrders = Order::whereHas('items', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
                ->whereDate('scheduled_date', '>=', today()->format('Y-m-d'))
                ->whereNotIn('status', [
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::REJECTED_BY_SELLER(),
                OrderStatusEnum::FAILED(),
            ])
                ->get();

            foreach ($futureOrders as $order) {
                $dayName = strtolower(Carbon::parse($order->scheduled_date)->format('l'));
                $dayConfig = $newOperatingHours[$dayName] ?? null;

                $isOpen = $dayConfig && isset($dayConfig['is_open']) && filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN);
                $isAffected = false;

                if (! $isOpen) {
                    $isAffected = true;
                } else {
                    $orderStart = $order->scheduled_slot_start_time;
                    $orderEnd = $order->scheduled_slot_end_time;
                    if ($orderStart && $orderEnd) {
                        $openTime = $dayConfig['open_time'] ?? null;
                        $closeTime = $dayConfig['close_time'] ?? null;
                        if (! $openTime || ! $closeTime || $orderStart < $openTime || $orderEnd > $closeTime) {
                            $isAffected = true;
                        } else {
                            $hasBreak = ! empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
                            if ($hasBreak) {
                                $breakStart = $dayConfig['break_start_time'] ?? null;
                                $breakEnd = $dayConfig['break_end_time'] ?? null;
                                if ($breakStart && $breakEnd && $orderStart < $breakEnd && $orderEnd > $breakStart) {
                                    $isAffected = true;
                                }
                            }
                        }
                    }
                }

                if ($isAffected) {
                    $deadline = now()->addHours((int) ($store->reschedule_deadline_hours ?? 24));

                    $order->update([
                        'reschedule_status' => 'needs_reschedule',
                        'reschedule_reason' => 'Store timing changed',
                        'reschedule_deadline' => $deadline,
                    ]);
                }
            }

            // Update operating hours configuration in store metadata
            $metadata = $store->metadata ?? [];
            $metadata['operating_hours'] = $newOperatingHours;
            $store->metadata = $metadata;
            $store->save();
        });
    }

    /**
     * Toggle temporary closure status of a store and flag all future scheduled orders.
     */
    public function toggleTemporaryClosure(Store $store, bool $isClosed, ?string $reason): void
    {
        DB::transaction(function () use ($store, $isClosed, $reason) {
            $store->is_temporarily_closed = $isClosed;
            $store->temporary_closure_reason = $isClosed ? $reason : null;
            $store->save();

            if ($isClosed) {
                $futureOrders = Order::whereHas('items', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                    ->whereDate('scheduled_date', '>=', today()->format('Y-m-d'))
                    ->whereNotIn('status', [
                    OrderStatusEnum::CANCELLED(),
                    OrderStatusEnum::REJECTED_BY_SELLER(),
                    OrderStatusEnum::FAILED(),
                ])
                    ->get();

                foreach ($futureOrders as $order) {
                    $deadline = now()->addHours((int) ($store->reschedule_deadline_hours ?? 24));

                    $order->update([
                        'reschedule_status' => 'needs_reschedule',
                        'reschedule_reason' => $reason ?: 'Store temporarily closed',
                        'reschedule_deadline' => $deadline,
                    ]);
                }
            }
        });
    }

    /**
     * Ensure the given store belongs to the seller; throw if not found/owned.
     */
    public function findOwnedOrFail(Seller $seller, int $id): Store
    {
        $store = Store::findOrFail($id);
        if ((int) $store->seller_id !== (int) $seller->id) {
            throw new ModelNotFoundException('Store not found');
        }

        return $store;
    }

    /**
     * Get available stores for given zone IDs with optional search and pagination.
     *
     * @param  array  $zoneIds  Array of zone IDs to filter available stores
     * @param  string|null  $search  Optional search term to filter stores by name, description, address, or seller name
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator Paginated list of available stores
     */
    public function getAvailableStoresByZoneIds(array $zoneIds, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        // Normalize zone IDs to unique integers
        $zoneIds = array_values(array_unique(array_filter(array_map('intval', $zoneIds))));

        // Build base query for available stores
        $query = $this->baseAvailabilityQuery($zoneIds);

        // Apply optional search filter
        if (! empty($search)) {
            $this->applySearch($query, $search);
        }

        // Order by name
        $query->orderBy('name');

        return $query->paginate($perPage);
    }

    /**
     * Build base store query constrained by verification/visibility status and zone availability.
     *
     * A store is considered available if it is approved and visible in the specified zones.
     */
    protected function baseAvailabilityQuery(array $zoneIds): Builder
    {
        $storeQuery = Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE());
        if (! empty($zoneIds)) {
            $storeQuery->whereHas('zones', function ($q) use ($zoneIds) {
                $q->whereIn('delivery_zones.id', $zoneIds);
            });
        }

        return $storeQuery;
    }

    /**
     * Apply simple search on the store query.
     * Searches across store name, description, address, and seller name.
     */
    protected function applySearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('address', 'LIKE', "%{$search}%")
                ->orWhereHas('seller.user', function ($sq) use ($search) {
                    $sq->where('name', 'LIKE', "%{$search}%");
                });
        });
    }

    /**
     * Create an empty paginator.
     */
    protected function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
