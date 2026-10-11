<?php

namespace App\Services;

use App\Enums\Banner\BannerTypeEnum;
use App\Enums\Banner\BannerVisibilityStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Banner;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class BannerService
{
    /**
     * Canonical zone-aware banner query.
     *
     * Bundles every gate that the customer-facing API applies to decide whether
     * a banner is visible for a customer at a given location:
     *   - visibility_status = published
     *   - direct zone gate via availableInZone() — empty pivot = visible everywhere
     *   - per-type indirect gate (the linked entity must have stock in the zone):
     *       • CUSTOM   → always passes
     *       • PRODUCT  → its product must be active+approved with stock in the zone
     *       • CATEGORY → at least one product in that category (or its direct
     *         children) must be active+approved with stock in the zone
     *       • BRAND    → at least one product of that brand must be active+approved
     *         with stock in the zone
     *
     * Scope filtering (global vs category-scoped) and ordering / pagination are
     * intentionally left to the caller so each surface (customer API, admin
     * Zone Preview) can layer its own concerns on top.
     */
    public function availableInZoneStoresQuery(?int $zoneId, array $storeIds): Builder
    {
        $useZoneFilter = ! is_null($zoneId);

        return Banner::query()
            ->where('visibility_status', BannerVisibilityStatusEnum::PUBLISHED())
            ->availableInZone($zoneId)
            ->where(function (Builder $q) use ($useZoneFilter, $storeIds) {
                // CUSTOM banners always pass.
                $q->where('type', BannerTypeEnum::CUSTOM());

                // PRODUCT banners require their product to be active and stocked in zone.
                $q->orWhere(function (Builder $q) use ($useZoneFilter, $storeIds) {
                    $q->where('type', BannerTypeEnum::PRODUCT())
                        ->whereExists(
                            Product::query()
                                ->selectRaw('1')
                                ->whereColumn('products.id', 'banners.product_id')
                                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                                ->where('status', ProductStatusEnum::ACTIVE())
                                ->when($useZoneFilter, function ($pq) use ($storeIds) {
                                    if (empty($storeIds)) {
                                        $pq->whereRaw('1 = 0');
                                    } else {
                                        $pq->whereHas(
                                            'variants.storeProductVariants',
                                            fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
                                        );
                                    }
                                })
                        );
                });

                // CATEGORY banners require a stocked product in that category or its direct children.
                $q->orWhere(function (Builder $q) use ($useZoneFilter, $storeIds) {
                    $q->where('type', BannerTypeEnum::CATEGORY())
                        ->whereExists(
                            Product::query()
                                ->selectRaw('1')
                                ->where(function (Builder $pq) {
                                    $pq->whereColumn('products.category_id', 'banners.category_id')
                                        ->orWhereIn('products.category_id', function ($sq) {
                                            $sq->select('id')
                                                ->from('categories')
                                                ->whereColumn('parent_id', 'banners.category_id');
                                        });
                                })
                                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                                ->where('status', ProductStatusEnum::ACTIVE())
                                ->when($useZoneFilter, function ($pq) use ($storeIds) {
                                    if (empty($storeIds)) {
                                        $pq->whereRaw('1 = 0');
                                    } else {
                                        $pq->whereHas(
                                            'variants.storeProductVariants',
                                            fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
                                        );
                                    }
                                })
                        );
                });

                // BRAND banners require at least one stocked product for that brand.
                $q->orWhere(function (Builder $q) use ($useZoneFilter, $storeIds) {
                    $q->where('type', BannerTypeEnum::BRAND())
                        ->whereExists(
                            Product::query()
                                ->selectRaw('1')
                                ->whereColumn('products.brand_id', 'banners.brand_id')
                                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                                ->where('status', ProductStatusEnum::ACTIVE())
                                ->when($useZoneFilter, function ($pq) use ($storeIds) {
                                    if (empty($storeIds)) {
                                        $pq->whereRaw('1 = 0');
                                    } else {
                                        $pq->whereHas(
                                            'variants.storeProductVariants',
                                            fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
                                        );
                                    }
                                })
                        );
                });
            });
    }
}
