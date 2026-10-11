<?php

namespace App\Services\Visibility;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Brand;
use App\Models\DeliveryZone;
use App\Services\BrandService;
use App\Services\ZonePreviewService;

/**
 * Explains *why* a brand is or isn't visible in the customer frontend.
 *
 * Returns the canonical inspector shape:
 *   [
 *     'status'        => 'live' | 'partial' | 'hidden',
 *     'checks'        => [{key, state: pass|warn|fail, label, message, fix?}, ...],
 *     'zone_summary'  => [
 *         'reachable_count' => int,
 *         'total_count'     => int,
 *         'problem_zones'   => [{id, name, reason}, ...],
 *     ],
 *   ]
 *
 * Same gates the customer-facing API applies — driven by the shared
 * BrandService::availableInStoresQuery() so this can never drift from what
 * the customer actually sees.
 */
class BrandVisibilityInspector
{
    public function __construct(
        protected BrandService $brandService,
        protected ZonePreviewService $zonePreviewService,
    ) {}

    /**
     * Inspect a brand. Pass $zoneScopeIds to limit zone reachability to a
     * specific subset (e.g. the seller's store zones); pass null for all
     * active zones (admin view).
     */
    public function inspect(Brand $brand, ?array $zoneScopeIds = null): array
    {
        $checks = [
            $this->checkStatus($brand),
            $this->checkScope($brand),
            ...$this->checkProducts($brand),
        ];

        $zoneSummary = $this->checkZoneReachability($brand, $zoneScopeIds);

        return [
            'status' => $this->rollupStatus($checks, $zoneSummary),
            'checks' => $checks,
            'zone_summary' => $zoneSummary,
        ];
    }

    protected function checkStatus(Brand $brand): array
    {
        $isActive = $brand->status === BrandStatusEnum::ACTIVE();

        return [
            'key' => 'status',
            'state' => $isActive ? 'pass' : 'fail',
            'label' => __('labels.status'),
            'message' => $isActive
                ? __('labels.visibility_brand_status_active')
                : __('labels.visibility_brand_status_inactive'),
            'fix' => $isActive ? null : __('labels.visibility_brand_status_fix'),
        ];
    }

    protected function checkScope(Brand $brand): array
    {
        $scope = $brand->scope_type;

        if ($scope === HomePageScopeEnum::GLOBAL()) {
            return [
                'key' => 'scope',
                'state' => 'pass',
                'label' => __('labels.scope_type'),
                'message' => __('labels.visibility_brand_scope_global'),
                'fix' => null,
            ];
        }

        if ($scope === HomePageScopeEnum::CATEGORY() && $brand->scope_id) {
            return [
                'key' => 'scope',
                'state' => 'pass',
                'label' => __('labels.scope_type'),
                'message' => __('labels.visibility_brand_scope_category'),
                'fix' => null,
            ];
        }

        return [
            'key' => 'scope',
            'state' => 'fail',
            'label' => __('labels.scope_type'),
            'message' => __('labels.visibility_brand_scope_invalid'),
            'fix' => __('labels.visibility_brand_scope_fix'),
        ];
    }

    /**
     * Two checks rolled up: total products linked + how many of those pass the
     * customer-side product gating (active + approved + has stocked variants
     * anywhere).
     */
    protected function checkProducts(Brand $brand): array
    {
        $totalProducts = $brand->products()->count();
        if ($totalProducts === 0) {
            return [[
                'key' => 'has_products',
                'state' => 'fail',
                'label' => __('labels.visibility_brand_products_label'),
                'message' => __('labels.visibility_brand_no_products'),
                'fix' => __('labels.visibility_brand_no_products_fix'),
            ]];
        }

        $stockedProducts = $brand->products()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->whereHas('variants.storeProductVariants')
            ->count();

        if ($stockedProducts === 0) {
            return [[
                'key' => 'stocked_products',
                'state' => 'fail',
                'label' => __('labels.visibility_brand_products_label'),
                'message' => __('labels.visibility_brand_products_unstocked', ['total' => $totalProducts]),
                'fix' => __('labels.visibility_brand_products_unstocked_fix'),
            ]];
        }

        return [[
            'key' => 'stocked_products',
            'state' => 'pass',
            'label' => __('labels.visibility_brand_products_label'),
            'message' => __('labels.visibility_brand_products_ok', [
                'stocked' => $stockedProducts,
                'total' => $totalProducts,
            ]),
            'fix' => null,
        ]];
    }

    /**
     * Reachable in N of M zones. When $zoneScopeIds is provided, restrict the
     * count to that subset (so a seller sees their own zones, not the whole
     * system). Returns a problem_zones list capped at 10 so the UI never
     * flood-renders 50 entries.
     */
    protected function checkZoneReachability(Brand $brand, ?array $zoneScopeIds = null): array
    {
        $zones = DeliveryZone::query()
            ->where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->when(! is_null($zoneScopeIds), fn($q) => $q->whereIn('id', $zoneScopeIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $total = $zones->count();
        $problemZones = [];
        $reachable = 0;

        foreach ($zones as $zone) {
            $storeIds = $this->zonePreviewService->storeIdsInZone($zone->id);
            $hasStock = $this->brandService
                ->availableInStoresQuery($storeIds)
                ->where('brands.id', $brand->id)
                ->exists();

            if ($hasStock) {
                $reachable++;
            } else {
                $problemZones[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => empty($storeIds)
                        ? __('labels.visibility_zone_no_stores')
                        : __('labels.visibility_zone_no_stocked_products'),
                ];
            }
        }

        // Cap problem zones to keep the panel readable on edge cases (50+ zones).
        $cappedProblems = array_slice($problemZones, 0, 10);

        return [
            'reachable_count' => $reachable,
            'total_count' => $total,
            'problem_zones' => $cappedProblems,
            'problem_truncated' => count($problemZones) > count($cappedProblems),
        ];
    }

    /**
     * Live = every check passes and reachable everywhere.
     * Hidden = any fail check, or reachable in zero zones.
     * Partial = otherwise (warnings or partial reachability).
     */
    protected function rollupStatus(array $checks, array $zoneSummary): string
    {
        foreach ($checks as $check) {
            if ($check['state'] === 'fail') {
                return 'hidden';
            }
        }

        if ($zoneSummary['total_count'] > 0 && $zoneSummary['reachable_count'] === 0) {
            return 'hidden';
        }

        if ($zoneSummary['total_count'] > 0 && $zoneSummary['reachable_count'] < $zoneSummary['total_count']) {
            return 'partial';
        }

        foreach ($checks as $check) {
            if ($check['state'] === 'warn') {
                return 'partial';
            }
        }

        return 'live';
    }
}
