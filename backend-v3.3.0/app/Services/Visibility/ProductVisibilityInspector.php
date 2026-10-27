<?php

namespace App\Services\Visibility;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Services\ZonePreviewService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Explains *why* a product is or isn't visible in the customer frontend.
 *
 * Returns the canonical inspector shape (same as Brand / Banner / FS inspectors):
 *   [
 *     'status'        => 'live' | 'partial' | 'hidden',
 *     'checks'        => [{key, state, label, message, fix?}, ...],
 *     'zone_summary'  => [
 *         'reachable_count'   => int,
 *         'total_count'       => int,
 *         'problem_zones'     => [{id, name, reason}, ...],
 *         'problem_truncated' => bool,
 *     ],
 *   ]
 *
 * Mirrors the customer-side product gate: status=active + verification_status=approved
 * + at least one variant with stock in a reachable store.
 */
class ProductVisibilityInspector
{
    public function __construct(protected ZonePreviewService $zonePreviewService) {}

    /**
     * Inspect a product. Pass $zoneScopeIds to limit zone reachability to a
     * subset (e.g. the seller's store zones); null = all active zones.
     */
    public function inspect(Product $product, ?array $zoneScopeIds = null): array
    {
        $product->loadMissing('variants.storeProductVariants:id,product_variant_id,store_id');

        $checks = [
            $this->checkStatus($product),
            $this->checkVerification($product),
            $this->checkVariants($product),
            $this->checkMainImage($product),
        ];

        $zoneSummary = $this->checkZoneReachability($product, $zoneScopeIds);

        return [
            'status' => $this->rollupStatus($checks, $zoneSummary),
            'checks' => $checks,
            'zone_summary' => $zoneSummary,
        ];
    }

    protected function checkStatus(Product $product): array
    {
        $isActive = $product->status === ProductStatusEnum::ACTIVE();

        return [
            'key' => 'status',
            'state' => $isActive ? 'pass' : 'fail',
            'label' => __('labels.status'),
            'message' => $isActive
                ? __('labels.visibility_product_status_active')
                : __('labels.visibility_product_status_inactive'),
            'fix' => $isActive ? null : __('labels.visibility_product_status_fix'),
        ];
    }

    protected function checkVerification(Product $product): array
    {
        $isApproved = $product->verification_status === ProductVarificationStatusEnum::APPROVED();

        return [
            'key' => 'verification',
            'state' => $isApproved ? 'pass' : 'fail',
            'label' => __('labels.verification_status'),
            'message' => $isApproved
                ? __('labels.visibility_product_verification_ok')
                : __('labels.visibility_product_verification_pending', ['status' => $product->verification_status]),
            'fix' => $isApproved ? null : __('labels.visibility_product_verification_fix'),
        ];
    }

    /**
     * Two checks rolled up: are there any variants? Of those variants, do any
     * have at least one store_product_variants row anywhere?
     */
    protected function checkVariants(Product $product): array
    {
        $totalVariants = $product->variants->count();
        if ($totalVariants === 0) {
            return [
                'key' => 'variants',
                'state' => 'fail',
                'label' => __('labels.visibility_product_variants_label'),
                'message' => __('labels.visibility_product_no_variants'),
                'fix' => __('labels.visibility_product_no_variants_fix'),
            ];
        }

        $stockedVariants = $product->variants->filter(
            fn($v) => $v->storeProductVariants->isNotEmpty(),
        )->count();

        if ($stockedVariants === 0) {
            return [
                'key' => 'variants',
                'state' => 'fail',
                'label' => __('labels.visibility_product_variants_label'),
                'message' => __('labels.visibility_product_unstocked', ['total' => $totalVariants]),
                'fix' => __('labels.visibility_product_unstocked_fix'),
            ];
        }

        return [
            'key' => 'variants',
            'state' => 'pass',
            'label' => __('labels.visibility_product_variants_label'),
            'message' => __('labels.visibility_product_variants_ok', [
                'stocked' => $stockedVariants,
                'total' => $totalVariants,
            ]),
            'fix' => null,
        ];
    }

    protected function checkMainImage(Product $product): array
    {
        $hasImage = ! empty($product->main_image)
            && ! str_ends_with($product->main_image, 'product-placeholder.jpg');

        return [
            'key' => 'main_image',
            'state' => $hasImage ? 'pass' : 'warn',
            'label' => __('labels.main_image'),
            'message' => $hasImage
                ? __('labels.visibility_product_image_ok')
                : __('labels.visibility_product_image_missing'),
            'fix' => $hasImage ? null : __('labels.visibility_product_image_missing_fix'),
        ];
    }

    /**
     * Per-zone reachability: at least one variant has stock in a store
     * approved + visible + attached to that zone.
     *
     * When $zoneScopeIds is provided, restricts the count to that subset (so
     * a seller sees their own zones, not the full system).
     */
    protected function checkZoneReachability(Product $product, ?array $zoneScopeIds = null): array
    {
        $zones = DeliveryZone::query()
            ->where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->when(! is_null($zoneScopeIds), fn($q) => $q->whereIn('id', $zoneScopeIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $total = $zones->count();
        $reachable = 0;
        $problems = [];

        foreach ($zones as $zone) {
            $storeIds = $this->zonePreviewService->storeIdsInZone($zone->id);
            if (empty($storeIds)) {
                $problems[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => __('labels.visibility_zone_no_stores'),
                ];
                continue;
            }

            $hasStock = $product->variants()
                ->whereHas(
                    'storeProductVariants',
                    fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
                )
                ->exists();

            if ($hasStock) {
                $reachable++;
            } else {
                $problems[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => __('labels.visibility_product_zone_no_stocked_variant'),
                ];
            }
        }

        $cappedProblems = array_slice($problems, 0, 10);

        return [
            'reachable_count' => $reachable,
            'total_count' => $total,
            'problem_zones' => $cappedProblems,
            'problem_truncated' => count($problems) > count($cappedProblems),
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
