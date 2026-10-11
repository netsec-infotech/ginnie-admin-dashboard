<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TestEmailRequest;
use App\Http\Requests\Settings\TestPaymentRequest;
use App\Http\Requests\Settings\TestSmsRequest;
use App\Libs\libphonenumber\NumberParseException;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Models\Setting;
use App\Services\SettingService;
use App\Services\SettingTestService;
use App\Services\SmsService;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AdvertisementSettingType;
use App\Types\Settings\AppSettingType;
use App\Types\Settings\AuthenticationSettingType;
use App\Types\Settings\DeliveryBoySettingType;
use App\Types\Settings\EmailSettingType;
use App\Types\Settings\HomeGeneralSettingType;
use App\Types\Settings\NotificationSettingType;
use App\Types\Settings\PaymentSettingType;
use App\Types\Settings\PickupSettingType;
use App\Types\Settings\Pos_settingsSettingType;
use App\Types\Settings\Scheduled_deliverySettingType;
use App\Types\Settings\SellerSettingType;
use App\Types\Settings\StorageSettingType;
use App\Types\Settings\SubscriptionSettingType;
use App\Types\Settings\SystemSettingType;
use App\Types\Settings\SystemUpdateSettingType;
use App\Types\Settings\WebSettingType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    use AuthorizesRequests;

    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(): View
    {
        try {
            $this->authorize('viewAny', Setting::class);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        }

        return view('admin.settings.all_settings');
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => ['required', new Enum(SettingTypeEnum::class)],
            ]);

            $type = $request->input('type');

            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    data: [],
                    status: 403
                );
            }

            // Map setting type to the corresponding class
            $method = match ($type) {
                SettingTypeEnum::SYSTEM() => SystemSettingType::class,
                SettingTypeEnum::STORAGE() => StorageSettingType::class,
                SettingTypeEnum::EMAIL() => EmailSettingType::class,
                SettingTypeEnum::PAYMENT() => PaymentSettingType::class,
                SettingTypeEnum::AUTHENTICATION() => AuthenticationSettingType::class,
                SettingTypeEnum::NOTIFICATION() => NotificationSettingType::class,
                SettingTypeEnum::WEB() => WebSettingType::class,
                SettingTypeEnum::APP() => AppSettingType::class,
                SettingTypeEnum::DELIVERY_BOY() => DeliveryBoySettingType::class,
                SettingTypeEnum::SELLER() => SellerSettingType::class,
                SettingTypeEnum::HOME_GENERAL_SETTINGS() => HomeGeneralSettingType::class,
                SettingTypeEnum::SUBSCRIPTION() => SubscriptionSettingType::class,
                SettingTypeEnum::SYSTEM_UPDATE_SETTINGS() => SystemUpdateSettingType::class,
                SettingTypeEnum::ADVERTISEMENT() => AdvertisementSettingType::class,
                SettingTypeEnum::POS_SETTINGS() => Pos_settingsSettingType::class,
                SettingTypeEnum::PICKUP() => PickupSettingType::class,
                SettingTypeEnum::SCHEDULED_DELIVERY() => Scheduled_deliverySettingType::class,
                SettingTypeEnum::LOYALTY_POINT() => \App\Types\Settings\Loyalty_pointSettingType::class,
                default => null,
            };

            if (! $method) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.invalid_type'),
                    data: []
                );
            }

            // Initialize settings object from request data
            $settings = $method::fromArray($request->all());

            // Handle media uploads
            $this->handleMediaUploads($request, $settings, $type);

            // Prepare values for storage as array (service will handle encoding + cache)
            $values = json_decode($settings->toJson(), true);

            switch ($type) {
                case 'authentication':
                    // Custom validation for mutually exclusive settings
                    $customErrors = AuthenticationSettingType::validateMutuallyExclusiveSettings($request->all());
                    if (! empty($customErrors)) {
                        return ApiResponseType::sendJsonResponse(
                            success: false,
                            message: __('labels.validation_failed') . ': ' . collect($customErrors)->flatten()->first(),
                            data: $customErrors
                        );
                    }
                    // Automatically set SMS gateway based on enabled services
                    $smsGateway = AuthenticationSettingType::getSmsGateway($request->all());
                    $request->merge(['smsGateway' => $smsGateway]);
                    break;
                case 'system':
                    // Prevent overwriting existing systemVendorType
                    $currentSystem = Setting::where('variable', 'system')->first();
                    $currentValue = $currentSystem?->value ?? [];
                    if (! empty($currentValue['systemVendorType'])) {
                        $values['systemVendorType'] = $currentValue['systemVendorType'];
                    } else {
                        $values['systemVendorType'] = 'multiple';
                    }
                    // Allow override only in dev mode
                    if (file_exists(storage_path('dev')) && $request->has('systemVendorType')) {
                        $values['systemVendorType'] = $request->input('systemVendorType');
                    }
                    // Derive countryCode (phonecode) from the selected currency.
                    $values['countryCode'] = $this->settingService->resolveCountryCodeFromCurrency(
                        $values['currency'] ?? null
                    );
                    break;
                case 'subscription':
                    // Enforce one-way enabling rule
                    $currentSubscription = Setting::where('variable', 'subscription')->first();
                    $currentEnable = $currentSubscription->value['enableSubscription'] ?? null;
                    if ($currentEnable === true) {
                        // Once enabled, always enabled
                        $values['enableSubscription'] = true;
                    }
                    break;
            }

            // Authorize the module-wise update action
            try {
                $this->authorize('updateSetting', [Setting::class, $type]);
            } catch (AuthorizationException $e) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.unauthorized_access'),
                    data: []
                );
            }

            // Update or create setting using service (handles cache refresh)
            $existing = Setting::find($type);
            $saved = $this->settingService->saveSetting($type, $values ?? []);
            $saved['redirect_url'] = $request->input('redirect_url') ?? '';

            if ($existing) {
                return ApiResponseType::sendJsonResponse(
                    success: true,
                    message: __('labels.setting_updated_successfully', ['type' => ucfirst(Str::replace('_', ' ', $type))]),
                    data: $saved
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.setting_created_successfully', ['type' => ucfirst(Str::replace('_', ' ', $type))]),
                data: $saved
            );
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed') . ': ' . $firstError,
                data: $e->errors()
            );
        }
    }

    /**
     * Handle media file uploads and assign paths to the settings object.
     *
     * @param  mixed  $settings
     */
    private function handleMediaUploads(Request $request, $settings, ?string $type = null): void
    {
        $mediaFields = [
            'logo' => [
                'name' => fn($file) => 'logo-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'favicon' => [
                'name' => fn($file) => 'favicon-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteHeaderDarkLogo' => [
                'name' => fn($file) => 'site-header-dark-logo-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteHeaderLogo' => [
                'name' => fn($file) => 'site-header-logo-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteFooterLogo' => [
                'name' => fn($file) => 'site-footer-logo-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'siteFavicon' => [
                'name' => fn($file) => 'site-favicon-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'orderNowSectionImage' => [
                'name' => fn($file) => 'order-now-image-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'sellerBannerBackgroundImage' => [
                'name' => fn($file) => 'seller-banner-bg-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],

            'backgroundImage' => [
                'name' => fn($file) => uniqid() . '-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'backgroundImageWeb' => [
                'name' => fn($file) => uniqid() . '-web-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'backgroundImageApp' => [
                'name' => fn($file) => uniqid() . '-app-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'backgroundAnimation' => [
                'name' => fn($file) => uniqid() . '-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'backgroundAnimationWeb' => [
                'name' => fn($file) => uniqid() . '-web-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'backgroundAnimationApp' => [
                'name' => fn($file) => uniqid() . '-app-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'icon' => [
                'name' => fn($file) => uniqid() . '-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'activeIcon' => [
                'name' => fn($file) => uniqid() . '-' . $file->getClientOriginalName(),
                'path' => 'settings',
            ],

            'serviceAccountFile' => ['name' => 'service-account-file.json', 'path' => 'settings'],
            'pwaLogo192x192' => [
                'name' => fn($file) => 'pwa-logo-192x192-' . time() . '.png',
                'path' => 'pwa_logos',
            ],

            'pwaLogo512x512' => [
                'name' => fn($file) => 'pwa-logo-512x512-' . time() . '.png',
                'path' => 'pwa_logos',
            ],

            'pwaLogo144x144' => [
                'name' => fn($file) => 'pwa-logo-144x144-' . time() . '.png',
                'path' => 'pwa_logos',
            ],

            'adminSignature' => [
                'name' => fn($file) => 'admin-signature-' . time() . '.' . $file->getClientOriginalExtension(),
                'path' => 'settings',
            ],
        ];

        $existing = $type ? $this->settingService->getSettingValues($type) : [];
        foreach ($mediaFields as $field => $config) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $fileName = is_callable($config['name']) ? $config['name']($file) : $config['name'];

                // [CHANGED BY AGENT]: Dynamically resolve target disk to active cloud provider
                $disk = $config['disk'] ?? config('media-library.disk_name', 'public');
                $options = ['disk' => $disk];

                // Force private visibility for sensitive files like service account
                if ($field === 'serviceAccountFile') {
                    $options['visibility'] = 'private';
                }

                $path = $file->storeAs($config['path'], $fileName, $options);
                $settings->$field = $path;
            } elseif (property_exists($settings, $field) && empty($settings->$field) && !empty($existing[$field])) {
                $settings->$field = $existing[$field];
            }
        }
    }

    public function show($variable): View
    {
        try {

            $setting_variable = SettingTypeEnum::values();
            if (! in_array($variable, $setting_variable)) {
                abort(404, __('labels.invalid_type'));
            }

            $transformedSetting = $this->settingService->getSettingByVariable($variable);

            if (! $transformedSetting) {
                abort(404, __('labels.setting_not_found'));
            }
            // Authorize module-wise view access
            $this->authorize('viewSetting', [Setting::class, $variable]);
            $settings = $transformedSetting->toArray(request())['value'] ?? [];

            // Ensure smsGateway field exists for authentication settings
            if ($variable === 'authentication') {
                // Calculate smsGateway based on current settings if not present
                if (! isset($settings['smsGateway'])) {
                    $customSmsEnabled = isset($settings['customSms']) && $settings['customSms'];
                    $firebaseEnabled = isset($settings['firebase']) && $settings['firebase'];

                    if ($customSmsEnabled) {
                        $settings['smsGateway'] = 'custom';
                    } elseif ($firebaseEnabled) {
                        $settings['smsGateway'] = 'firebase';
                    } else {
                        $settings['smsGateway'] = '';
                    }
                }
            }

            return view('admin.settings.' . $variable, [
                'settings' => $settings,
                'variable' => $variable,
            ]);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        } catch (\Exception $e) {
            abort(500, __('labels.something_went_wrong'));
        }
    }

    /**
     * Fire a one-off SMS using the values currently sitting in the admin form,
     * without touching the saved authentication setting. Lets the customer
     * verify a gateway config before committing it.
     */
    public function testSms(TestSmsRequest $request, SmsService $smsService): JsonResponse
    {
        try {
            $parsed = PhoneNumberUtil::getInstance()->parse((string) $request->input('test_mobile'));
        } catch (NumberParseException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.invalid_mobile_number'),
                data: ['error' => $e->getMessage()],
            );
        }

        $config = $request->toSmsConfig();
        $template = $config['customSmsTextFormatData']
            ?: __('labels.test_sms_default_message');

        $message = strtr($template, [
            '{otp}' => '123456',
            '{minutes}' => '10',
            '{brand}' => $this->settingService->getBrandName(),
        ]);

        $result = $smsService->sendSmsWithConfig($parsed, $message, $config);

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [
                'gateway_status' => $result['gateway_status'] ?? null,
                'gateway_body' => $result['gateway_body'] ?? null,
                'gateway_error' => $result['gateway_error'] ?? null,
            ],
        );
    }

    /**
     * Dispatch a one-off email using the SMTP credentials currently sitting in
     * the admin form, without persisting the email setting. Lets the operator
     * verify their SMTP config before saving.
     */
    public function testEmail(TestEmailRequest $request, SettingTestService $testService): JsonResponse
    {
        $result = $testService->sendTestEmail(
            (string) $request->input('test_email'),
            $request->toSmtpConfig(),
        );

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [
                'gateway_status' => $result['gateway_status'] ?? null,
                'gateway_body' => $result['gateway_body'] ?? null,
                'gateway_error' => $result['gateway_error'] ?? null,
            ],
        );
    }

    /**
     * Probe a payment gateway with the credentials currently in the admin form
     * via a cheap read-only API call. Returns the gateway HTTP status + body
     * so the operator can debug bad keys before saving.
     */
    public function testPayment(TestPaymentRequest $request, SettingTestService $testService): JsonResponse
    {
        $result = $testService->testPaymentGateway(
            (string) $request->input('gateway'),
            $request->credentials(),
        );

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [
                'gateway_status' => $result['gateway_status'] ?? null,
                'gateway_body' => $result['gateway_body'] ?? null,
                'gateway_error' => $result['gateway_error'] ?? null,
            ],
        );
    }
}
