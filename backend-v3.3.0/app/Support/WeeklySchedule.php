<?php

namespace App\Support;

use Carbon\CarbonInterface;

class WeeklySchedule
{
    public function __construct(
        private readonly array $schedule,
        private readonly string $timezone,
    ) {}

    public function isAvailable(CarbonInterface $at): bool
    {
        if (! ($this->schedule['enabled'] ?? false)) {
            return true;
        }

        $local = $at->copy()->setTimezone($this->timezone);
        $time = $local->format('H:i');
        $day = strtolower($local->englishDayOfWeek);
        $hours = isset($this->schedule['hours']) ? ($this->schedule['hours'][$day] ?? []) : $this->schedule;

        return in_array($day, $this->schedule['days'] ?? [], true)
            && $time >= ($hours['start_time'] ?? '00:00')
            && $time < ($hours['end_time'] ?? '00:00');
    }
}
