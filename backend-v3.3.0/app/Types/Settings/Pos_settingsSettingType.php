<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class Pos_settingsSettingType implements SettingInterface
{
    use SettingTrait;

    public ?int $walkin_user_id = null;
    public ?string $default_receipt_footer = null;

    protected static function getValidationRules(): array
    {
        return [
            'walkin_user_id' => 'nullable|integer',
            'default_receipt_footer' => 'nullable|string|max:1000',
        ];
    }
}
