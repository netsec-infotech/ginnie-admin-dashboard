<?php

namespace App\Notifications\Concerns;

use App\Broadcasting\FirebaseChannel;
use App\Enums\SettingTypeEnum;
use App\Services\SettingService;

trait RespectsChannelSettings
{
    protected function filterChannels(array $channels): array
    {
        $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::NOTIFICATION());

        $pushEnabled = (bool) ($settings['enablePush'] ?? true);
        $emailEnabled = (bool) ($settings['enableEmail'] ?? false);

        return array_values(array_filter($channels, function ($channel) use ($pushEnabled, $emailEnabled) {
            if ($channel === FirebaseChannel::class) {
                return $pushEnabled;
            }

            if ($channel === 'mail') {
                return $emailEnabled;
            }

            return true;
        }));
    }
}
