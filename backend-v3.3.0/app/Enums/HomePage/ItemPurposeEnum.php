<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * How a home_section_items row is used: shown as a card, or as a query filter.
 * @method static DISPLAY()
 * @method static FILTER()
 */
enum ItemPurposeEnum: string
{
    use InvokableCases, Values, Names;

    case DISPLAY = 'display';
    case FILTER = 'filter';
}
