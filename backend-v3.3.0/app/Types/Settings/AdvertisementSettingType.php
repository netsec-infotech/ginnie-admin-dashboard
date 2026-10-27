<?php

namespace App\Types\Settings;

use App\Enums\Advertisement\AdsDisableBehaviorEnum;
use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

/**
 * Schema for the `advertisement` row in the `settings` table.
 *
 * Public properties become both the default values (read via reflection by
 * SettingService) and the validated keys saved into the JSON blob.
 *
 * Engineering knobs (token TTL, impression visibility threshold, dwell time)
 * are intentionally NOT settings — they are hardcoded so non-technical admins
 * cannot break event accounting by tweaking them. See class constants below.
 */
class AdvertisementSettingType implements SettingInterface
{
    use SettingTrait;

    // Hardcoded engineering knobs consumed by PR3 (event handler / token signer).
    public const AD_TOKEN_TTL_MINUTES = 15;
    public const AD_IMPRESSION_VISIBILITY_PCT = 70;
    public const AD_IMPRESSION_VISIBILITY_MS = 1000;

    // --- General ---
    public bool $featureEnabled = false;
    public string $disableBehavior = 'keep_running';

    // --- Pricing (locked at approval onto each campaign) ---
    public ?float $cpcRate = null;
    public ?float $walletMinTopup = null;

    // --- Reach estimation multipliers shown on seller create-campaign form ---
    // 1 click ≈ impressionMultiplierMin–impressionMultiplierMax impressions.
    // Defaults represent a ~5–8% CTR assumption typical for marketplace ads.
    public int $impressionMultiplierMin = 12;
    public int $impressionMultiplierMax = 20;

    protected static function getValidationRules(): array
    {
        $disableBehaviors = AdsDisableBehaviorEnum::values();

        return [
            'featureEnabled'            => 'nullable|boolean',
            'disableBehavior'           => 'nullable|string|in:' . implode(',', $disableBehaviors),
            'cpcRate'                   => 'nullable|numeric|min:0',
            'walletMinTopup'            => 'nullable|numeric|min:0',
            'impressionMultiplierMin'   => 'nullable|integer|min:1|max:1000',
            'impressionMultiplierMax'   => 'nullable|integer|min:1|max:1000',
        ];
    }
}
