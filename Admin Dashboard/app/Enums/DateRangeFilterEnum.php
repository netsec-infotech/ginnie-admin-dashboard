<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;
use Carbon\Carbon;

/**
 * @method static LAST_30_MINUTES()
 * @method static LAST_1_HOUR()
 * @method static LAST_5_HOURS()
 * @method static LAST_1_DAY()
 * @method static LAST_7_DAYS()
 * @method static LAST_30_DAYS()
 * @method static LAST_365_DAYS()
 */
enum DateRangeFilterEnum: string
{
    use InvokableCases, Values, Names;

    case LAST_30_MINUTES = 'last_30_minutes';
    case LAST_1_HOUR = 'last_1_hour';
    case LAST_5_HOURS = 'last_5_hours';
    case LAST_1_DAY = 'last_1_day';
    case LAST_7_DAYS = 'last_7_days';
    case LAST_30_DAYS = 'last_30_days';
    case LAST_365_DAYS = 'last_365_days';

    public static function fromDate($range): ?Carbon
    {
        return match ($range) {
            DateRangeFilterEnum::LAST_30_MINUTES() => Carbon::now()->subMinutes(30),
            DateRangeFilterEnum::LAST_1_HOUR() => Carbon::now()->subHour(),
            DateRangeFilterEnum::LAST_5_HOURS() => Carbon::now()->subHours(5),
            DateRangeFilterEnum::LAST_1_DAY() => Carbon::now()->subDay(),
            DateRangeFilterEnum::LAST_7_DAYS() => Carbon::now()->subDays(7),
            DateRangeFilterEnum::LAST_30_DAYS() => Carbon::now()->subDays(30),
            DateRangeFilterEnum::LAST_365_DAYS() => Carbon::now()->subDays(365),
            default => null,
        };
    }
}
