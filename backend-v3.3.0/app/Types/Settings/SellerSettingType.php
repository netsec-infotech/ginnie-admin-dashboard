<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class SellerSettingType implements SettingInterface
{
    use SettingTrait;

    public string $termsCondition = '';
    public string $privacyPolicy = '';
    public int $return_seller_inspection_sla_hours = 48;
    public bool $regularOrderAcceptanceTimeoutEnabled = true;
    public int $regularOrderAcceptanceTimeoutMinutes = 10;

    protected static function getValidationRules(): array
    {
        return [
            'termsCondition' => 'nullable|string',
            'privacyPolicy' => 'nullable|string',
            'return_seller_inspection_sla_hours' => 'required|integer|min:1',
            'regularOrderAcceptanceTimeoutEnabled' => 'nullable|boolean',
            'regularOrderAcceptanceTimeoutMinutes' => 'required|integer|min:1|max:1440',
        ];
    }
}
