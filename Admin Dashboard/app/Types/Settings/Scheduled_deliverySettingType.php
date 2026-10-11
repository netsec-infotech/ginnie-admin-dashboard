<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

/**
 * Class Scheduled_deliverySettingType
 * Handles validation and storage mapping for global scheduled delivery settings.
 */
class Scheduled_deliverySettingType implements SettingInterface
{
    use SettingTrait;

    // Globally enable or disable scheduled delivery checkouts
    public bool $enableScheduledDelivery = true;

    // Platform-wide maximum limit on store-level advance booking days
    public int $maxAdvanceBookingDaysLimit = 7;

    /**
     * Get validation rules for Scheduled Delivery Settings.
     */
    protected static function getValidationRules(): array
    {
        return [
            'enableScheduledDelivery' => ['nullable', 'boolean'],
            'maxAdvanceBookingDaysLimit' => ['required', 'integer', 'min:1'],
        ];
    }
}
