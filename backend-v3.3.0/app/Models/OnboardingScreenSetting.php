<?php

namespace App\Models;

use App\Enums\OnboardingAppTypeEnum;
use Illuminate\Database\Eloquent\Model;

class OnboardingScreenSetting extends Model
{
    protected $fillable = [
        'app_type',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public static function forApp(OnboardingAppTypeEnum|string $appType): self
    {
        $value = $appType instanceof OnboardingAppTypeEnum ? $appType->value : $appType;

        return self::firstOrCreate(
            ['app_type' => $value],
            ['is_enabled' => true],
        );
    }
}
