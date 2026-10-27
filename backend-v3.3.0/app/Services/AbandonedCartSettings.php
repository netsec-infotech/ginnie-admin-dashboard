<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AbandonedCartSettings
{
    public const CHANNEL_PUSH = 'push';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    /** @var array<int, string> */
    public const ALLOWED_CHANNELS = [
        self::CHANNEL_PUSH,
        self::CHANNEL_EMAIL,
        self::CHANNEL_SMS,
    ];

    public function __construct(protected SettingService $settingService) {}

    /**
     * @return array{enabled: bool, delay_minutes: int, channels: array<int, string>}
     */
    public function configuration(): array
    {
        $values = $this->settingService->getSettingValues(SettingTypeEnum::NOTIFICATION());
        $channels = $values['abandonedCartNotificationChannels'] ?? [self::CHANNEL_PUSH];
        $channels = is_array($channels) ? $channels : [];

        return [
            'enabled' => filter_var(
                $values['enableAbandonedCartNotification'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            ),
            'delay_minutes' => max(5, min(1440, (int) ($values['abandonedCartNotificationDelayMinutes'] ?? 60))),
            'channels' => array_values(array_intersect(self::ALLOWED_CHANNELS, $channels)),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->configuration()['enabled'];
    }

    public function delayMinutes(): int
    {
        return $this->configuration()['delay_minutes'];
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->configuration()['channels'], true);
    }

    /**
     * @return array<string, bool>
     */
    public function channelAvailability(): array
    {
        return [
            self::CHANNEL_PUSH => $this->safely(fn (): bool => $this->firebaseIsConfigured()),
            self::CHANNEL_EMAIL => $this->safely(fn (): bool => $this->emailIsConfigured()),
            self::CHANNEL_SMS => $this->safely(fn (): bool => $this->smsIsConfigured()),
        ];
    }

    public function channelIsAvailable(string $channel): bool
    {
        return $this->channelAvailability()[$channel] ?? false;
    }

    public function customerWebCartUrl(): ?string
    {
        $url = trim((string) (Setting::find(SettingTypeEnum::WEB())?->value['customerWebUrl'] ?? ''));

        return $url === '' ? null : rtrim($url, '/').'/cart';
    }

    public function customerAppCartDeepLink(): ?string
    {
        $scheme = trim((string) (Setting::find(SettingTypeEnum::APP())?->value['customerAppScheme'] ?? ''));

        return $scheme === '' ? null : rtrim($scheme, ':/').'://cart';
    }

    private function firebaseIsConfigured(): bool
    {
        $path = 'settings/service-account-file.json';

        if (Storage::disk('local')->exists($path)) {
            return true;
        }

        $activeDisk = config('media-library.disk_name', 'public');

        return $activeDisk !== 'local' && Storage::disk($activeDisk)->exists($path);
    }

    private function emailIsConfigured(): bool
    {
        $email = Setting::find(SettingTypeEnum::EMAIL())?->value ?? [];

        foreach (['smtpHost', 'smtpPort', 'smtpEmail', 'smtpPassword'] as $key) {
            if (empty($email[$key])) {
                return false;
            }
        }

        return true;
    }

    private function smsIsConfigured(): bool
    {
        $authentication = Setting::find(SettingTypeEnum::AUTHENTICATION())?->value ?? [];

        return filter_var($authentication['customSms'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($authentication['customSmsUrl'])
            && ! empty($authentication['customSmsMethod']);
    }

    private function safely(callable $callback): bool
    {
        try {
            return $callback();
        } catch (Throwable) {
            return false;
        }
    }
}
