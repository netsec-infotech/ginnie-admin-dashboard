<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Shape of the recipient mobile number passed to the configured SMS gateway.
 *
 * @method static E164_WITH_PLUS()
 * @method static E164_WITHOUT_PLUS()
 * @method static NATIONAL()
 * @method static NATIONAL_WITH_ZERO()
 */
enum SmsMobileFormatEnum: string
{
    use InvokableCases, Names, Values;

    case E164_WITH_PLUS     = 'e164_with_plus';
    case E164_WITHOUT_PLUS  = 'e164_without_plus';
    case NATIONAL           = 'national';
    case NATIONAL_WITH_ZERO = 'national_with_zero';

    /** Human label rendered in the admin dropdown. */
    public function label(): string
    {
        return match ($this) {
            self::E164_WITH_PLUS     => '+919876543210 (E.164 with +)',
            self::E164_WITHOUT_PLUS  => '919876543210 (E.164 without +)',
            self::NATIONAL           => '9876543210 (National only)',
            self::NATIONAL_WITH_ZERO => '09876543210 (National with leading 0)',
        };
    }
}
