<?php

namespace App\Services\Visibility;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\Banner\BannerTypeEnum;
use App\Enums\Banner\BannerVisibilityStatusEnum;
use App\Enums\BrandStatusEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Services\BannerService;
use App\Services\ZonePreviewService;

/**
 * Explains *why* a banner is or isn't visible in the customer frontend.
 *
 * Returns the canonical inspector shape (same as BrandVisibilityInspector):
 *   [
 *     'status'        => 'live' | 'partial' | 'hidden',
 *     'checks'        => [{key, state, label, message, fix?}, ...],
 *     'zone_summary'  => [
 *         'restricted'      => bool, // true when banner has explicit zone selection
 *         'reachable_count' => int,
 *         'total_count'     => int,
 *         'problem_zones'   => [{id, name, reason}, ...],
 *         'problem_truncated' => bool,
 *     ],
 *   ]
 *
 * Same gates the customer-facing API applies — driven by the shared
 * BannerService::availableInZoneStoresQuery() so the inspector can never
 * drift from what the customer actually sees.
 */
class BannerVisibilityInspector
{
    public function __construct(
        protected BannerService $bannerService,
        protected ZonePreviewService $zonePreviewService,
    ) {}

    public function inspect(Banner $banner): array
    {
        $banner->loadMissing(['zones:id,name', 'product:id,title,status,verification_status', 'category:id,title,status', 'brand:id,title,status']);

        $checks = [
            $this->checkVisibility($banner),
            $this->checkScope($banner),
            $this->checkLinkedEntity($banner),
            $this->checkImage($banner),
        ];

        $zoneSummary = $this->checkZoneReachability($banner);

        return [
            'status' => $this->rollupStatus($checks, $zoneSummary),
            'checks' => $checks,
            'zone_summary' => $zoneSummary,
        ];
    }

    protected function checkVisibility(Banner $banner): array
    {
        $isPublished = $banner->visibility_status === BannerVisibilityStatusEnum::PUBLISHED();

        return [
            'key' => 'visibility',
            'state' => $isPublished ? 'pass' : 'fail',
            'label' => __('labels.visibility_status'),
            'message' => $isPublished
                ? __('labels.visibility_banner_published')
                : __('labels.visibility_banner_draft'),
            'fix' => $isPublished ? null : __('labels.visibility_banner_publish_fix'),
        ];
    }

    protected function checkScope(Banner $banner): array
    {
        $scope = $banner->scope_type;

        if ($scope === HomePageScopeEnum::GLOBAL()) {
            return [
                'key' => 'scope',
                'state' => 'pass',
                'label' => __('labels.scope_type'),
                'message' => __('labels.visibility_banner_scope_global'),
                'fix' => null,
            ];
        }

        if ($scope === HomePageScopeEnum::CATEGORY() && $banner->scope_id) {
            return [
                'key' => 'scope',
                'state' => 'pass',
                'label' => __('labels.scope_type'),
                'message' => __('labels.visibility_banner_scope_category'),
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
     * Per-type linked entity check. Mirrors BannerService::availableInZoneStoresQuery:
     *   - CUSTOM   → must have a custom_url
     *   - PRODUCT  → product_id must point to an active+approved product
     *   - CATEGORY → category_id must point to an active category
     *   - BRAND    → brand_id must point to an active brand
     */
    protected function checkLinkedEntity(Banner $banner): array
    {
        $type = $banner->type;

        if ($type === BannerTypeEnum::CUSTOM()) {
            $hasUrl = ! empty($banner->custom_url);
            return [
                'key' => 'linked_entity',
                'state' => $hasUrl ? 'pass' : 'fail',
                'label' => __('labels.visibility_banner_link_label'),
                'message' => $hasUrl
                    ? __('labels.visibility_banner_link_custom_ok')
                    : __('labels.visibility_banner_link_custom_missing'),
                'fix' => $hasUrl ? null : __('labels.visibility_banner_link_custom_fix'),
            ];
        }

        if ($type === BannerTypeEnum::PRODUCT()) {
            $product = $banner->product;
            if (! $product) {
                return $this->failedLink('linked_entity', 'visibility_banner_link_product_missing', 'visibility_banner_link_product_fix');
            }
            $isReachable = $product->status === ProductStatusEnum::ACTIVE()
                && $product->verification_status === ProductVarificationStatusEnum::APPROVED();

            return [
                'key' => 'linked_entity',
                'state' => $isReachable ? 'pass' : 'fail',
                'label' => __('labels.visibility_banner_link_label'),
                'message' => $isReachable
                    ? __('labels.visibility_banner_link_product_ok', ['title' => $product->title])
                    : __('labels.visibility_banner_link_product_inactive', ['title' => $product->title]),
                'fix' => $isReachable ? null : __('labels.visibility_banner_link_product_inactive_fix'),
            ];
        }

        if ($type === BannerTypeEnum::CATEGORY()) {
            $category = $banner->category;
            if (! $category) {
                return $this->failedLink('linked_entity', 'visibility_banner_link_category_missing', 'visibility_banner_link_category_fix');
            }
            $isReachable = $category->status === CategoryStatusEnum::ACTIVE();

            return [
                'key' => 'linked_entity',
                'state' => $isReachable ? 'pass' : 'fail',
                'label' => __('labels.visibility_banner_link_label'),
                'message' => $isReachable
                    ? __('labels.visibility_banner_link_category_ok', ['title' => $category->title])
                    : __('labels.visibility_banner_link_category_inactive', ['title' => $category->title]),
                'fix' => $isReachable ? null : __('labels.visibility_banner_link_category_inactive_fix'),
            ];
        }

        if ($type === BannerTypeEnum::BRAND()) {
            $brand = $banner->brand;
            if (! $brand) {
                return $this->failedLink('linked_entity', 'visibility_banner_link_brand_missing', 'visibility_banner_link_brand_fix');
            }
            $isReachable = $brand->status === BrandStatusEnum::ACTIVE();

            return [
                'key' => 'linked_entity',
                'state' => $isReachable ? 'pass' : 'fail',
                'label' => __('labels.visibility_banner_link_label'),
                'message' => $isReachable
                    ? __('labels.visibility_banner_link_brand_ok', ['title' => $brand->title])
                    : __('labels.visibility_banner_link_brand_inactive', ['title' => $brand->title]),
                'fix' => $isReachable ? null : __('labels.visibility_banner_link_brand_inactive_fix'),
            ];
        }

        return $this->failedLink('linked_entity', 'visibility_banner_link_unknown_type', 'visibility_banner_link_unknown_type_fix');
    }

    protected function checkImage(Banner $banner): array
    {
        $hasImage = ! empty($banner->banner_image);
        return [
            'key' => 'image',
            'state' => $hasImage ? 'pass' : 'warn',
            'label' => __('labels.banner_image'),
            'message' => $hasImage
                ? __('labels.visibility_banner_image_ok')
                : __('labels.visibility_banner_image_missing'),
            'fix' => $hasImage ? null : __('labels.visibility_banner_image_missing_fix'),
        ];
    }

    /**
     * Reachable in N of M zones. Counts ALL active zones — even when the banner
     * has explicit zone restrictions — so the admin sees both kinds of "hidden":
     * the ones intentionally excluded by the zones picker AND the ones excluded
     * because the linked entity has no stock.
     */
    protected function checkZoneReachability(Banner $banner): array
    {
        $zones = DeliveryZone::query()
            ->where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->orderBy('name')
            ->get(['id', 'name']);

        $allowedZoneIds = $banner->zones->pluck('id')->all();
        $hasZoneRestriction = ! empty($allowedZoneIds);
        $totalProblems = [];
        $reachable = 0;
        $total = $zones->count();

        foreach ($zones as $zone) {
            // Step 1: direct zone gate.
            if ($hasZoneRestriction && ! in_array($zone->id, $allowedZoneIds, true)) {
                $totalProblems[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => __('labels.visibility_banner_zone_restricted_out'),
                ];
                continue;
            }

            // Step 2: indirect type-based gate via the canonical service query.
            $storeIds = $this->zonePreviewService->storeIdsInZone($zone->id);
            $passes = $this->bannerService
                ->availableInZoneStoresQuery($zone->id, $storeIds)
                ->where('banners.id', $banner->id)
                ->exists();

            if ($passes) {
                $reachable++;
            } else {
                $totalProblems[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => $this->indirectFailureReason($banner, empty($storeIds)),
                ];
            }
        }

        $cappedProblems = array_slice($totalProblems, 0, 10);

        return [
            'restricted' => $hasZoneRestriction,
            'reachable_count' => $reachable,
            'total_count' => $total,
            'problem_zones' => $cappedProblems,
            'problem_truncated' => count($totalProblems) > count($cappedProblems),
        ];
    }

    protected function indirectFailureReason(Banner $banner, bool $noStoresInZone): string
    {
        if ($noStoresInZone) {
            return __('labels.visibility_zone_no_stores');
        }

        $type = $banner->type;
        if ($type === BannerTypeEnum::PRODUCT()) {
            return __('labels.visibility_banner_zone_product_unstocked');
        }
        if ($type === BannerTypeEnum::CATEGORY()) {
            return __('labels.visibility_banner_zone_category_empty');
        }
        if ($type === BannerTypeEnum::BRAND()) {
            return __('labels.visibility_banner_zone_brand_empty');
        }
        // CUSTOM should never hit indirect failure but keep a generic fallback.
        return __('labels.visibility_zone_no_stocked_products');
    }

    protected function failedLink(string $key, string $msgKey, string $fixKey): array
    {
        return [
            'key' => $key,
            'state' => 'fail',
            'label' => __('labels.visibility_banner_link_label'),
            'message' => __('labels.' . $msgKey),
            'fix' => __('labels.' . $fixKey),
        ];
    }

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
