<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static CUSTOMER()
 * @method static SELLER()
 * @method static RIDER()
 */
enum OnboardingAppTypeEnum: string
{
    use InvokableCases, Names, Values;

    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case RIDER = 'rider';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => __('labels.customer_app'),
            self::SELLER => __('labels.seller_app'),
            self::RIDER => __('labels.rider_app'),
        };
    }
}
