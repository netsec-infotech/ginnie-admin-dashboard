<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class WatchAndBuySettingType implements SettingInterface
{
    use SettingTrait;

    public bool $enabled = true;

    public string $title = 'Watch & Buy';

    public int $maxDurationSeconds = 60;

    public int $maxUploadMb = 50;

    public int $maxProductsPerReel = 10;

    public bool $statusesEnabled = true;

    public int $maxStatusDurationSeconds = 15;

    public int $maxProductsPerStatus = 5;

    protected static function getValidationRules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:100'],
            'maxDurationSeconds' => ['required', 'integer', 'min:5', 'max:180'],
            'maxUploadMb' => ['required', 'integer', 'min:5', 'max:500'],
            'maxProductsPerReel' => ['required', 'integer', 'min:1', 'max:20'],
            'statusesEnabled' => ['required', 'boolean'],
            'maxStatusDurationSeconds' => ['required', 'integer', 'min:5', 'max:60'],
            'maxProductsPerStatus' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
