<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class AppSettingType implements SettingInterface
{
    use SettingTrait;
    public string $customerAppstoreLink = '';
    public string $customerPlaystoreLink = '';
    public string $customerAppScheme = '';
    public bool $customerViewDeliveryZoneInfo = false;
    public string $customerGoogleAnalyticsId = '';
    public string $customerMicrosoftClarityId = '';
    public string $sellerAppstoreLink = '';
    public string $sellerPlaystoreLink = '';
    public string $sellerAppScheme = '';
    public string $sellerGoogleAnalyticsId = '';
    public string $sellerMicrosoftClarityId = '';
    public string $riderAppstoreLink = '';
    public string $riderPlaystoreLink = '';
    public string $riderGoogleAnalyticsId = '';
    public string $riderMicrosoftClarityId = '';
    protected static function getValidationRules(): array
    {
        return [
            'customerAppstoreLink' => 'nullable|url',
            'customerPlaystoreLink' => 'nullable|url',
            'customerAppScheme' => ['nullable', 'regex:/^\S+$/'],
            'customerViewDeliveryZoneInfo' => 'nullable|in:0,1,true,false',
            'customerGoogleAnalyticsId' => 'nullable|string|max:255',
            'customerMicrosoftClarityId' => 'nullable|string|max:255',
            'sellerAppstoreLink' => 'nullable|url',
            'sellerPlaystoreLink' => 'nullable|url',
            'sellerAppScheme' => ['nullable', 'regex:/^\S+$/'],
            'sellerGoogleAnalyticsId' => 'nullable|string|max:255',
            'sellerMicrosoftClarityId' => 'nullable|string|max:255',
            'riderAppstoreLink' => 'nullable|url',
            'riderPlaystoreLink' => 'nullable|url',
            'riderGoogleAnalyticsId' => 'nullable|string|max:255',
            'riderMicrosoftClarityId' => 'nullable|string|max:255',
        ];
    }
}
