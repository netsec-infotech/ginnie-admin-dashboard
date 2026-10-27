<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class DeliveryBoySettingType implements SettingInterface
{
    use SettingTrait;

    public string $termsCondition = '';

    public string $privacyPolicy = '';

    // [CHANGE] Global settings for cash collection limit
    public bool $cashCollectionLimitStatus = false;

    public string $cashCollectionLimitAmount = '0';

    // ─── Delivery Boy Refer & Earn Program ────────────────────────────────────
    // Fixed-amount only. Settled immediately on admin verification. One-time per referred DB.
    public bool $deliveryBoyReferEarnStatus = false;

    public string $deliveryBoyReferEarnBonusReferral = '0'; // amount paid to DB-A (referrer)

    public string $deliveryBoyReferEarnBonusReferee = '0'; // amount paid to DB-B (new delivery boy)

    // ─── Rider Tips Configuration ──────────────────────────────────────────────
    public bool $enableRiderTips = true;

    public string $minTipAmount = '1';

    public string $preset1 = '10';

    public string $preset2 = '20';

    public string $preset3 = '50';

    public string $maxTipAmount = '500';

    protected static function getValidationRules(): array
    {
        return [
            'termsCondition' => 'nullable|string',
            'privacyPolicy' => 'nullable|string',
            'cashCollectionLimitStatus' => ['nullable', 'boolean'],
            'cashCollectionLimitAmount' => ['nullable', 'numeric', 'min:0'],
            'deliveryBoyReferEarnStatus' => ['nullable', 'boolean'],
            'deliveryBoyReferEarnBonusReferral' => ['nullable', 'numeric', 'min:0'],
            'deliveryBoyReferEarnBonusReferee' => ['nullable', 'numeric', 'min:0'],
            'enableRiderTips' => ['nullable', 'boolean'],
            'minTipAmount' => ['nullable', 'numeric', 'min:0'],
            'preset1' => ['nullable', 'numeric', 'min:0'],
            'preset2' => ['nullable', 'numeric', 'min:0'],
            'preset3' => ['nullable', 'numeric', 'min:0'],
            'maxTipAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
