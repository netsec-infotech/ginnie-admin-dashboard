<?php

namespace App\Services\Support;

use App\Enums\WeekDayEnum;
use App\Services\SettingService;
use App\Support\WeeklySchedule;
use Carbon\CarbonInterface;

class SupportAvailabilityService
{
    public function __construct(private readonly SettingService $settings) {}

    public function configuration(): array
    {
        $defaults = config('support.availability');
        $defaultHours = $defaults['hours'] ?? array_fill_keys(WeekDayEnum::values(), [
            'start_time' => $defaults['start_time'] ?? '09:00',
            'end_time' => $defaults['end_time'] ?? '18:00',
        ]);
        $stored = $this->settings->getSettingValues('support')['availability'] ?? [];
        $configuration = array_replace($defaults, $stored);
        $configuration['hours'] = ! isset($stored['hours']) && isset($stored['start_time'], $stored['end_time'])
            ? array_fill_keys(WeekDayEnum::values(), ['start_time' => $stored['start_time'], 'end_time' => $stored['end_time']])
            : array_replace($defaultHours, $stored['hours'] ?? []);

        return $configuration;
    }

    public function save(array $availability): array
    {
        $values = $this->settings->getSettingValues('support');
        $current = $this->configuration();
        if (isset($availability['hours'])) {
            $availability['hours'] = array_replace($current['hours'], $availability['hours']);
        }
        $availability['enabled'] = (bool) $availability['enabled'];
        $values['availability'] = array_replace($current, $availability);
        $this->settings->saveSetting('support', $values);

        return $values['availability'];
    }

    public function awayMessage(CarbonInterface $at): ?string
    {
        $configuration = $this->configuration();
        $schedule = new WeeklySchedule($configuration, config('app.timezone'));

        return $schedule->isAvailable($at) ? null : $configuration['message'];
    }
}
