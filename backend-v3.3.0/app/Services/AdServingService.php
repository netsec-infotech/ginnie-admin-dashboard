<?php

namespace App\Services;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdsDisableBehaviorEnum;
use App\Enums\SettingTypeEnum;
use App\Models\AdCampaign;
use Illuminate\Support\Collection;

class AdServingService
{
    public function __construct(
        protected SettingService $settingService,
    ) {
    }

    /**
     * Find running campaigns (with remaining budget) for a given set of product IDs.
     *
     * @param array  $productIds  Product IDs to check.
     * @param string $placement   Placement key (AdPlacementEnum value).
     * @return Collection<int, array{product_id: int, campaign_id: int, remaining_budget: float}>  Keyed by product_id.
     */
    public function findCampaignsForProducts(array $productIds, string $placement): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $settings = $this->getAdSettings();

        if (! ($settings['featureEnabled'] ?? false)) {
            $behavior = $settings['disableBehavior'] ?? AdsDisableBehaviorEnum::KEEP_RUNNING();
            if ($behavior === AdsDisableBehaviorEnum::PAUSE_ALL()) {
                return collect();
            }
        }

        return AdCampaign::whereIn('product_id', $productIds)
            ->where('status', AdCampaignStatusEnum::RUNNING())
            ->whereRaw('(budget - spent) > 0')
            ->whereJsonContains('placements', $placement)
            ->get()
            ->map(fn (AdCampaign $campaign) => [
                'product_id'       => $campaign->product_id,
                'campaign_id'      => $campaign->id,
                'remaining_budget' => max(0.01, (float) $campaign->budget - (float) $campaign->spent),
            ])
            ->keyBy('product_id');
    }

    private function getAdSettings(): array
    {
        $resource = $this->settingService->getSettingByVariable(SettingTypeEnum::ADVERTISEMENT());
        return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }
}
