<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Mirrors the `device_type` ENUM column on `user_fcm_tokens`.
 *
 * @method static ANDROID()
 * @method static IOS()
 * @method static WEB()
 */
enum DeviceTypeEnum: string
{
    use InvokableCases, Names, Values;

    case ANDROID = 'android';
    case IOS = 'ios';
    case WEB = 'web';
}
