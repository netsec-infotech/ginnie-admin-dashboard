<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class PickupSettingType implements SettingInterface
{
    use SettingTrait;

    public bool $enabled = false;

    /**
     * Get Laravel validation rules for the properties
     *
     * @return array<string, array<string>>
     */
    protected static function getValidationRules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}
