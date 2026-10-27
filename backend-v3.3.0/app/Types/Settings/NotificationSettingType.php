<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class NotificationSettingType implements SettingInterface
{
    use SettingTrait;

    public string $firebaseProjectId = '';
    public string $serviceAccountFile = '';
    public string $vapIdKey = '';
    public bool $whatsappEnabled = false;
    public string $whatsappPhoneNumberId = '';
    public string $whatsappBusinessAccountId = '';
    public string $whatsappAccessToken = '';
    public string $whatsappWebhookVerifyToken = '';
    public string $whatsappDefaultLanguage = 'en_US';
    public string $whatsappTestMobile = '';
    public bool $enableAbandonedCartNotification = true;
    public int $abandonedCartNotificationDelayMinutes = 60;

    /** @var array<int, string> */
    public array $abandonedCartNotificationChannels = ['push'];

    protected static function getValidationRules(): array
    {
        return [
            'firebaseProjectId' => 'nullable',
            'serviceAccountFile' => 'nullable|mimes:json',
            'vapIdKey' => 'nullable',
            'whatsappEnabled' => ['nullable', 'boolean'],
            'whatsappPhoneNumberId' => ['required_if:whatsappEnabled,1,true', 'nullable', 'string', 'max:255'],
            'whatsappBusinessAccountId' => ['nullable', 'string', 'max:255'],
            'whatsappAccessToken' => ['required_if:whatsappEnabled,1,true', 'nullable', 'string'],
            'whatsappWebhookVerifyToken' => ['nullable', 'string', 'max:255'],
            'whatsappDefaultLanguage' => ['required_if:whatsappEnabled,1,true', 'nullable', 'string', 'max:16'],
            'whatsappTestMobile' => ['nullable', 'string', 'max:32'],
            'enableAbandonedCartNotification' => ['required', 'boolean'],
            'abandonedCartNotificationDelayMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'abandonedCartNotificationChannels' => ['present', 'array'],
            'abandonedCartNotificationChannels.*' => ['string', 'distinct', 'in:push,email,sms'],
        ];
    }
}
