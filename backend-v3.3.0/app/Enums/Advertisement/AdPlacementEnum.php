<?php

namespace App\Enums\Advertisement;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Where a campaign ad can be served.
 * Placement is set internally to all slots — not exposed in the seller UI.
 *
 * @method static SEARCH()
 * @method static RELATED()
 */
enum AdPlacementEnum: string
{
    use InvokableCases, Values, Names;

    case SEARCH  = 'search';
    case RELATED = 'related';

    /** All available placements (used when creating a campaign). */
    public static function all(): array
    {
        return [self::SEARCH(), self::RELATED()];
    }
}
