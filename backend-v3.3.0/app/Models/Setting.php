<?php

namespace App\Models;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SystemVendorTypeEnum;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    protected $primaryKey = 'variable';  // Define the primary key

    public $incrementing = false;  // Tell Laravel it's NOT an auto-incrementing key

    protected $keyType = 'string';

    protected $fillable = ['variable', 'value'];

    public function getValueAttribute($value)
    {
        return json_decode($value, true);
    }

    public function getAllowedCountriesAttribute()
    {
        if (! empty($this->value['allowedCountries'])) {
            return Country::whereIn('name', $this->value['allowedCountries'])->pluck('iso2')->toArray();
        }

        return null;
    }

    public static function systemType(): string
    {
        $systemSettings = self::systemSettings();

        return ! empty($systemSettings['systemVendorType']) ? $systemSettings['systemVendorType'] : SystemVendorTypeEnum::MULTIPLE();
    }

    public static function systemSettings()
    {
        $settingService = app(SettingService::class);
        $resource = $settingService->getSettingByVariable('system');

        return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }

    public static function aiSettings()
    {
        $settingService = app(SettingService::class);
        $resource = $settingService->getSettingByVariable(SettingTypeEnum::AI());

        return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }

    public static function isAiDescriptionEnabled(): bool
    {
        try {
            $aiSettings = self::aiSettings();
            if (!empty($aiSettings['enableAiDescription'])) {
                return true;
            }

            $systemSettings = self::systemSettings();

            return !empty($systemSettings['enableAiDescription']);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isDemoModeEnabled(): bool
    {
        try {
            $user = Auth::user();
            if ($user && ($user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) || $user->hasRole('super-admin'))) {
                return false;
            }

            $settings = self::systemSettings();

            return (bool) ($settings['demoMode'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isSystemVendorTypeMultiple(): bool
    {
        $systemVendorType = self::systemType();

        return $systemVendorType === SystemVendorTypeEnum::MULTIPLE();
    }

    public static function isSystemVendorTypeSingle(): bool
    {
        $systemVendorType = self::systemType();

        return $systemVendorType === SystemVendorTypeEnum::SINGLE();
    }

    public static function isSubscriptionEnabled(): bool
    {
        try {
            $service = app(SettingService::class);
            $resource = $service->getSettingByVariable(SettingTypeEnum::SUBSCRIPTION());
            $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

            return ($settings['enableSubscription'] ?? null) === true;
        } catch (\Throwable) {
            // On any error, fail open (do not enforce limits)
            return false;
        }
    }

    public static function isPickupEnabled(): bool
    {
        try {
            $service = app(SettingService::class);
            $resource = $service->getSettingByVariable(SettingTypeEnum::PICKUP());
            $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

            return ($settings['enabled'] ?? null) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isScheduledDeliveryEnabled(): bool
    {
        try {
            $service = app(SettingService::class);
            $resource = $service->getSettingByVariable(SettingTypeEnum::SCHEDULED_DELIVERY());
            $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

            return filter_var($settings['enableScheduledDelivery'] ?? true, FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable) {
            return true;
        }
    }

    public static function adminSeller()
    {
        try {
            $userAuth = Auth::user();
            $seller = SellerUser::where('user_id', $userAuth->id)->with('seller');
            if (
                Setting::isSystemVendorTypeSingle()
                && $userAuth
                && $userAuth->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())
                && $seller->exists()
            ) {
                return $seller->first()->seller;
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }

    public static function canImpersonate(): bool
    {
        $userAuth = Auth::user();

        return Setting::isSystemVendorTypeSingle()
            && $userAuth
            && $userAuth->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())
            && SellerUser::where('user_id', $userAuth->id)->exists();
    }

    /**
     * Returns the current application version.
     * Priority: config('app.version') > latest applied SystemUpdate > '0.0.0'
     */
    public static function getCurrentVersion(): string
    {
        // Prefer the last applied update version
        $lastApplied = SystemUpdate::where('status', 'applied')->orderByDesc('id')->first();
        if ($lastApplied && is_string($lastApplied->version) && $lastApplied->version !== '') {
            return $lastApplied->version;
        }
        // Fallback to config value
        $fromConfig = config('app.version');
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        return '0.0.0';
    }

    /**
     * Returns an array of enabled payment gateway keys (matching PaymentTypeEnum values).
     *
     * @return string[]
     */
    public static function getEnabledPaymentGateway($onlyGateway = false): array
    {
        try {
            $settingService = app(SettingService::class);
            $resource = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
            $paymentSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

            $gateways = [
                PaymentTypeEnum::STRIPE(),
                PaymentTypeEnum::RAZORPAY(),
                PaymentTypeEnum::PAYSTACK(),
                PaymentTypeEnum::FLUTTERWAVE(),
                PaymentTypeEnum::XENDIT(),
                PaymentTypeEnum::MERCADO_PAGO(),
            ];

            if (! $onlyGateway) {
                $gateways[] = PaymentTypeEnum::COD();
                $gateways[] = PaymentTypeEnum::WALLET();
            }

            return array_values(array_filter($gateways, fn($gateway) => ! empty($paymentSettings[$gateway])));
        } catch (\Throwable $e) {
            Log::error('Failed to load enabled payment gateways: ' . $e->getMessage());

            return [];
        }
    }

    public static function posWalkinUserId(): ?int
    {
        $values = app(SettingService::class)->getSettingValues('pos_settings');

        return $values['walkin_user_id'] ?? null;
    }

    public static function currencySymbol(): string
    {
        $setting = self::where('variable', 'system')->first();
        $val = $setting?->value;

        return is_array($val) ? ($val['currency_symbol'] ?? '') : '';
    }

    public static function getRiderTipSettings(): array
    {
        try {
            // Read from delivery_boy setting (Admin Panel section)
            $dbRow = Setting::where('variable', SettingTypeEnum::DELIVERY_BOY()->value)->first();
            $dbSettings = $dbRow?->value ?? [];

            if (isset($dbSettings['enableRiderTips'])) {
                return [
                    'enable_rider_tips' => filter_var($dbSettings['enableRiderTips'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'min_tip_amount' => (float) ($dbSettings['minTipAmount'] ?? 5.00),
                    'preset_1' => (float) ($dbSettings['preset1'] ?? 10.00),
                    'preset_2' => (float) ($dbSettings['preset2'] ?? 20.00),
                    'preset_3' => (float) ($dbSettings['preset3'] ?? 50.00),
                    'max_tip_amount' => (float) ($dbSettings['maxTipAmount'] ?? 500.00),
                ];
            }

            // Fallback to rider_tip setting if present
            $tipRow = Setting::where('variable', SettingTypeEnum::RIDER_TIP()->value)->first();
            $settings = $tipRow?->value ?? [];

            return [
                'enable_rider_tips' => filter_var($settings['enable_rider_tips'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'min_tip_amount' => (float) ($settings['min_tip_amount'] ?? 5.00),
                'preset_1' => (float) ($settings['preset_1'] ?? 10.00),
                'preset_2' => (float) ($settings['preset_2'] ?? 20.00),
                'preset_3' => (float) ($settings['preset_3'] ?? 50.00),
                'max_tip_amount' => (float) ($settings['max_tip_amount'] ?? 500.00),
            ];
        } catch (\Throwable) {
            return [
                'enable_rider_tips' => true,
                'min_tip_amount' => 5.00,
                'preset_1' => 10.00,
                'preset_2' => 20.00,
                'preset_3' => 50.00,
                'max_tip_amount' => 500.00,
            ];
        }
    }

    public static function getLoyaltyPointSettings(): array
    {
        try {
            $row = Setting::where('variable', SettingTypeEnum::LOYALTY_POINT())->first();
            $settings = $row?->value ?? [];

            $expiryDays = (int) ($settings['expiry_days'] ?? 0);
            $isExpiryEnabled = $expiryDays > 0 || filter_var($settings['is_expiry_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return [
                'is_enabled' => filter_var($settings['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'point_rate' => (float) ($settings['point_rate'] ?? 100.0),
                'redemption_value' => (float) ($settings['redemption_value'] ?? 1.0),
                'max_redemption_percentage' => (float) ($settings['max_redemption_percentage'] ?? 10.0),
                'min_order_value_to_redeem' => (float) ($settings['min_order_value_to_redeem'] ?? 0.0),
                'is_expiry_enabled' => $isExpiryEnabled,
                'expiry_days' => $expiryDays,
            ];
        } catch (\Throwable) {
            return [
                'is_enabled' => false,
                'point_rate' => 100.0,
                'redemption_value' => 1.0,
                'max_redemption_percentage' => 10.0,
                'min_order_value_to_redeem' => 0.0,
                'is_expiry_enabled' => false,
                'expiry_days' => 0,
            ];
        }
    }
}
