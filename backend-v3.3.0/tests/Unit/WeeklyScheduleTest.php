<?php

use App\Support\WeeklySchedule;
use Carbon\CarbonImmutable;

it('evaluates selected weekdays and exact local time boundaries', function (string $time, bool $expected) {
    $schedule = new WeeklySchedule([
        'enabled' => true,
        'days' => ['monday'],
        'start_time' => '09:00',
        'end_time' => '18:00',
    ], 'Asia/Kolkata');

    expect($schedule->isAvailable(CarbonImmutable::parse($time, 'UTC')))->toBe($expected);
})->with([
    'before opening' => ['2026-09-07 03:29:59', false],
    'at opening' => ['2026-09-07 03:30:00', true],
    'before closing' => ['2026-09-07 12:29:59', true],
    'at closing' => ['2026-09-07 12:30:00', false],
    'unselected day' => ['2026-09-08 04:00:00', false],
]);

it('treats disabled schedules as always available without changing the input time', function () {
    $at = CarbonImmutable::parse('2026-09-06 23:00:00', 'UTC');
    expect((new WeeklySchedule(['enabled' => false], 'Asia/Kolkata'))->isAvailable($at))->toBeTrue()
        ->and($at->timezoneName)->toBe('UTC');
});

it('uses each days own opening and closing times', function (string $time, bool $expected) {
    $schedule = new WeeklySchedule([
        'enabled' => true,
        'days' => ['monday', 'tuesday', 'wednesday'],
        'hours' => [
            'monday' => ['start_time' => '09:00', 'end_time' => '17:00'],
            'tuesday' => ['start_time' => '12:00', 'end_time' => '20:00'],
        ],
    ], 'Asia/Kolkata');

    expect($schedule->isAvailable(CarbonImmutable::parse($time, 'Asia/Kolkata')))->toBe($expected);
})->with([
    ['2026-09-07 09:00:00', true],
    ['2026-09-07 17:00:00', false],
    ['2026-09-08 09:00:00', false],
    ['2026-09-08 12:00:00', true],
    ['2026-09-08 19:59:59', true],
    ['2026-09-08 20:00:00', false],
    ['2026-09-09 12:00:00', false],
    ['2026-09-10 12:00:00', false],
]);
