<?php

namespace App\Notifications\Channels;

use App\Enums\SettingTypeEnum;
use App\Libs\libphonenumber\NumberParseException;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Services\AbandonedCartSettings;
use App\Services\SettingService;
use App\Services\SmsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CustomSmsChannel
{
    public function __construct(
        protected SmsService $smsService,
        protected SettingService $settingService,
        protected AbandonedCartSettings $abandonedCartSettings,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')
            || ! $this->abandonedCartSettings->channelIsAvailable(AbandonedCartSettings::CHANNEL_SMS)) {
            return;
        }

        $mobile = trim((string) ($notifiable->mobile ?? ''));
        if ($mobile === '') {
            return;
        }

        try {
            $parsed = PhoneNumberUtil::getInstance()->parse($this->normaliseMobile($notifiable, $mobile));
        } catch (NumberParseException $exception) {
            Log::warning('Notification SMS skipped because the customer mobile number is invalid.', [
                'notifiable_id' => $notifiable->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $result = $this->smsService->sendSms($parsed, (string) $notification->toSms($notifiable));

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException((string) ($result['message'] ?? 'SMS delivery failed.'));
        }
    }

    private function normaliseMobile(object $notifiable, string $mobile): string
    {
        if (str_starts_with($mobile, '+') || str_starts_with($mobile, '00')) {
            return $mobile;
        }

        $countryCode = preg_replace('/\D+/', '', (string) ($notifiable->country_code ?? ''));
        if ($countryCode === '') {
            $system = $this->settingService->getRawSetting(SettingTypeEnum::SYSTEM())?->value ?? [];
            $countryCode = preg_replace('/\D+/', '', (string) ($system['countryCode'] ?? ''));
        }

        return $countryCode === ''
            ? $mobile
            : '+'.$countryCode.preg_replace('/\D+/', '', $mobile);
    }
}
