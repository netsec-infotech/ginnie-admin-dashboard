<?php

namespace App\Services\Visibility;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\FeaturedSection\FeaturedSectionTypeEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\DeliveryZone;
use App\Models\FeaturedSection;
use App\Services\FeaturedSectionService;

/**
 * Explains *why* a featured section is or isn't visible in the customer frontend.
 *
 * Returns the canonical inspector shape (same as Brand + Banner inspectors):
 *   [
 *     'status'        => 'live' | 'partial' | 'hidden',
 *     'checks'        => [{key, state, label, message, fix?}, ...],
 *     'zone_summary'  => [
 *         'restricted'        => bool,
 *         'reachable_count'   => int,
 *         'total_count'       => int,
 *         'problem_zones'     => [{id, name, reason}, ...],
 *         'problem_truncated' => bool,
 *     ],
 *   ]
 *
 * Same gates the customer-facing API applies — driven by the shared
 * FeaturedSectionService::availableInZoneQuery() so the inspector tracks
 * paginateSections() exactly.
 */
class FeaturedSectionVisibilityInspector
{
    public function __construct(protected FeaturedSectionService $featuredSectionService) {}

    public function inspect(FeaturedSection $section): array
    {
        $section->loadMissing('zones:id,name');

        $checks = [
            $this->checkStatus($section),
            $this->checkScope($section),
            $this->checkSectionType($section),
            $this->checkBackground($section),
        ];

        $zoneSummary = $this->checkZoneReachability($section);

        return [
            'status' => $this->rollupStatus($checks, $zoneSummary),
            'checks' => $checks,
            'zone_summary' => $zoneSummary,
        ];
    }

    protected function checkStatus(FeaturedSection $section): array
    {
        $isActive = $section->status === ActiveInactiveStatusEnum::ACTIVE();

        return [
            'key' => 'status',
            'state' => $isActive ? 'pass' : 'fail',
            'label' => __('labels.status'),
            'message' => $isActive
                ? __('labels.visibility_fs_status_active')
                : __('labels.visibility_fs_status_inactive'),
            'fix' => $isActive ? null : __('labels.visibility_fs_status_fix'),
        ];
    }

    protected function checkScope(FeaturedSection $section): array
    {
        $scope = $section->scope_type;

        if ($scope === HomePageScopeEnum::GLOBAL()) {
            return [
                'key' => 'scope',
                'state' => 'pass',
                'label' => __('labels.scope_type'),
                'message' => __('labels.visibility_fs_scope_global'),
                'fix' => null,
            ];
        }

        if ($scope === HomePageScopeEnum::CATEGORY() && $section->scope_id) {
            return [
                'key' => 'scope',
                'state' => 'pass',
                'label' => __('labels.scope_type'),
                'message' => __('labels.visibility_fs_scope_category'),
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

    protected function checkSectionType(FeaturedSection $section): array
    {
        $valid = in_array($section->section_type, FeaturedSectionTypeEnum::values(), true);

        return [
            'key' => 'section_type',
            'state' => $valid ? 'pass' : 'fail',
            'label' => __('labels.section_type'),
            'message' => $valid
                ? __('labels.visibility_fs_section_type_ok', ['type' => $section->section_type])
                : __('labels.visibility_fs_section_type_invalid'),
            'fix' => $valid ? null : __('labels.visibility_fs_section_type_fix'),
        ];
    }

    /**
     * If the user picked a background type, make sure the corresponding asset is set.
     * Otherwise warn (the section will render plain, which often isn't intended).
     */
    protected function checkBackground(FeaturedSection $section): array
    {
        $type = $section->background_type;

        if (! $type) {
            return [
                'key' => 'background',
                'state' => 'warn',
                'label' => __('labels.background_type'),
                'message' => __('labels.visibility_fs_background_none'),
                'fix' => __('labels.visibility_fs_background_none_fix'),
            ];
        }

        if ($type === 'color') {
            $hasColor = ! empty($section->background_color);
            return [
                'key' => 'background',
                'state' => $hasColor ? 'pass' : 'warn',
                'label' => __('labels.background_type'),
                'message' => $hasColor
                    ? __('labels.visibility_fs_background_color_ok')
                    : __('labels.visibility_fs_background_color_missing'),
                'fix' => $hasColor ? null : __('labels.visibility_fs_background_color_missing_fix'),
            ];
        }

        if ($type === 'image') {
            $hasAnyImage = ! empty($section->background_image)
                || ! empty($section->desktop_4k_background_image)
                || ! empty($section->desktop_fdh_background_image)
                || ! empty($section->tablet_background_image)
                || ! empty($section->mobile_background_image);
            return [
                'key' => 'background',
                'state' => $hasAnyImage ? 'pass' : 'warn',
                'label' => __('labels.background_type'),
                'message' => $hasAnyImage
                    ? __('labels.visibility_fs_background_image_ok')
                    : __('labels.visibility_fs_background_image_missing'),
                'fix' => $hasAnyImage ? null : __('labels.visibility_fs_background_image_missing_fix'),
            ];
        }

        return [
            'key' => 'background',
            'state' => 'warn',
            'label' => __('labels.background_type'),
            'message' => __('labels.visibility_fs_background_unknown'),
            'fix' => __('labels.visibility_fs_background_none_fix'),
        ];
    }

    /**
     * Reachable in N of M zones. Tracks both the direct zone pivot
     * (intentional restriction) and the canonical reachability check.
     */
    protected function checkZoneReachability(FeaturedSection $section): array
    {
        $zones = DeliveryZone::query()
            ->where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->orderBy('name')
            ->get(['id', 'name']);

        $allowedZoneIds = $section->zones->pluck('id')->all();
        $hasZoneRestriction = ! empty($allowedZoneIds);
        $totalProblems = [];
        $reachable = 0;
        $total = $zones->count();

        foreach ($zones as $zone) {
            // Direct gate: when explicitly restricted, only listed zones pass.
            if ($hasZoneRestriction && ! in_array($zone->id, $allowedZoneIds, true)) {
                $totalProblems[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => __('labels.visibility_fs_zone_restricted_out'),
                ];
                continue;
            }

            // Canonical query gate (active + availableInZone).
            $passes = $this->featuredSectionService
                ->availableInZoneQuery($zone->id)
                ->where('featured_sections.id', $section->id)
                ->exists();

            if ($passes) {
                $reachable++;
            } else {
                $totalProblems[] = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'reason' => __('labels.visibility_zone_no_stocked_products'),
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
