<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingTypeEnum;
use App\Events\BroadcastConnectionTested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TestEmailRequest;
use App\Http\Requests\Settings\TestPaymentRequest;
use App\Http\Requests\Settings\TestSmsRequest;
use App\Libs\libphonenumber\NumberParseException;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\BroadcastSettingsService;
use App\Services\SettingService;
use App\Services\SettingTestService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\DemoMode;
use App\Support\WhatsAppTemplateCatalog;
use App\Services\Ai\AiManager;
use App\Support\SettingSecrets;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AdvertisementSettingType;
use App\Types\Settings\AiSettingType;
use App\Types\Settings\AppSettingType;
use App\Types\Settings\AuthenticationSettingType;
use App\Types\Settings\BroadcastDriverSettingType;
use App\Types\Settings\DeliveryBoySettingType;
use App\Types\Settings\EmailSettingType;
use App\Types\Settings\HomeGeneralSettingType;
use App\Types\Settings\Loyalty_pointSettingType;
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
use App\Types\Settings\WatchAndBuySettingType;
use App\Types\Settings\WebSettingType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    // Test real-time WebSockets connection for Reverb or Pusher
    public function testBroadcastConnection(Request $request, BroadcastSettingsService $broadcastSettings): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::BROADCAST_DRIVER()]);
        $validated = $request->validate([
            'test_id' => ['required', 'uuid'],
        ]);

        if (! $broadcastSettings->isConfigured()) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.broadcast_configuration_required'),
                data: [],
                status: 422,
            );
        }

        try {
            $realtime = $broadcastSettings->apply();
            BroadcastConnectionTested::dispatch((int) $request->user()->id, $validated['test_id']);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.broadcast_test_published'),
                data: ['driver' => $realtime['driver']],
            );
        } catch (\Throwable $e) {
            Log::warning('Broadcast connection test failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.broadcast_test_failed'),
                data: [],
                status: 422,
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => ['required', new Enum(SettingTypeEnum::class)],
            ]);

            $type = $request->input('type');

            if (DemoMode::isDemoUser($request->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_action_restricted'),
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
                SettingTypeEnum::LOYALTY_POINT() => Loyalty_pointSettingType::class,
                SettingTypeEnum::WATCH_AND_BUY() => WatchAndBuySettingType::class,
                // Broadcast driver settings
                SettingTypeEnum::BROADCAST_DRIVER() => BroadcastDriverSettingType::class,
                SettingTypeEnum::AI() => AiSettingType::class,
                default => null,
            };

            if (! $method) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.invalid_type'),
                    data: []
                );
            }

            $settingsInput = $request->all();
            if (
                $type === SettingTypeEnum::NOTIFICATION()
                && ! $request->has('abandonedCartNotificationChannels')
            ) {
                $settingsInput['abandonedCartNotificationChannels'] = [];
            }

            if ($type === SettingTypeEnum::PAYMENT()) {
                $settingsInput = $this->normalizePaymentCurrencySettings($settingsInput);
            }

            if (in_array($type, [SettingTypeEnum::PAYMENT(), SettingTypeEnum::BROADCAST_DRIVER(), SettingTypeEnum::NOTIFICATION()], true)) {
                $existingValues = $this->settingService->getSettingValues($type);
                foreach (SettingSecrets::keysFor($type) as $secretKey) {
                    $incoming = $settingsInput[$secretKey] ?? null;
                    if ($incoming === null || $incoming === '' || $incoming === SettingSecrets::MASK) {
                        $settingsInput[$secretKey] = $existingValues[$secretKey] ?? '';
                    }
                }
            }

            // Initialize settings object from request data
            $settings = $method::fromArray($settingsInput);

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

            // Apply broadcast settings dynamically if broadcast driver was updated
            if ($type === SettingTypeEnum::BROADCAST_DRIVER()) {
                app(BroadcastSettingsService::class)->apply();
            }

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

            'appSectionImage' => [
                'name' => fn($file) => 'app-section-image-' . time() . '.' . $file->getClientOriginalExtension(),
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

            'backgroundImageWebMobile' => [
                'name' => fn($file) => uniqid() . '-web-mobile-' . $file->getClientOriginalName(),
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

            'serviceAccountFile' => ['name' => 'service-account-file.json', 'path' => 'settings', 'disk' => 'local'],
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
            $disk = $config['disk'] ?? config('media-library.disk_name', 'public');

            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $fileName = is_callable($config['name']) ? $config['name']($file) : $config['name'];

                $options = ['disk' => $disk];

                // Force private visibility for sensitive files like service account
                if ($field === 'serviceAccountFile') {
                    $options['visibility'] = 'private';
                }

                $oldPath = $existing[$field] ?? null;
                $path = $file->storeAs($config['path'], $fileName, $options);
                $settings->$field = $path;
                if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
                    // Delete only after the replacement stores successfully.
                    $this->deleteStoredSettingsFile($oldPath, $disk);
                }
            } elseif ($this->canRemoveSettingsMedia($type, $field) && $request->boolean('remove_' . $field)) {
                $oldPath = $existing[$field] ?? null;
                if (is_string($oldPath) && $oldPath !== '') {
                    $this->deleteStoredSettingsFile($oldPath, $disk);
                }
                $settings->$field = '';
            } elseif (property_exists($settings, $field) && empty($settings->$field) && ! empty($existing[$field])) {
                $settings->$field = $existing[$field];
            }
        }

        if ($type === SettingTypeEnum::HOME_GENERAL_SETTINGS()) {
            $this->clearInactiveHomeGeneralMedia($settings, $existing, $mediaFields);
        }
    }

    private function canRemoveSettingsMedia(?string $type, string $field): bool
    {
        return in_array($field, match ($type) {
            SettingTypeEnum::SYSTEM() => ['logo', 'favicon', 'adminSignature'],
            SettingTypeEnum::WEB() => [
                'appSectionImage',
                'orderNowSectionImage',
                'sellerBannerBackgroundImage',
                'pwaLogo192x192',
                'pwaLogo512x512',
                'pwaLogo144x144',
            ],
            SettingTypeEnum::HOME_GENERAL_SETTINGS() => [
                'backgroundImage',
                'backgroundImageWeb',
                'backgroundImageWebMobile',
                'backgroundImageApp',
                'backgroundAnimation',
                'backgroundAnimationWeb',
                'backgroundAnimationApp',
                'icon',
                'activeIcon',
            ],
            default => [],
        }, true);
    }

    private function deleteStoredSettingsFile(string $path, string $disk): void
    {
        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('Settings media cleanup failed', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function clearInactiveHomeGeneralMedia($settings, array $existing, array $mediaFields): void
    {
        $backgroundType = $settings->backgroundType ?? null;
        $fields = [];

        if ($backgroundType !== 'image') {
            $fields = array_merge($fields, [
                'backgroundImage',
                'backgroundImageWeb',
                'backgroundImageWebMobile',
                'backgroundImageApp',
            ]);
        }

        if ($backgroundType !== 'animation') {
            $fields = array_merge($fields, [
                'backgroundAnimation',
                'backgroundAnimationWeb',
                'backgroundAnimationApp',
            ]);
        }

        foreach (array_unique($fields) as $field) {
            $oldPath = $existing[$field] ?? null;
            $settings->$field = '';

            if (! is_string($oldPath) || $oldPath === '' || ! isset($mediaFields[$field])) {
                continue;
            }

            $disk = $mediaFields[$field]['disk'] ?? config('media-library.disk_name', 'public');
            $this->deleteStoredSettingsFile($oldPath, $disk);
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
            $isDemoUser = DemoMode::isDemoUser(request()->user());

            if (! $isDemoUser) {
                $rawSettings = $this->settingService->getSettingValues($variable);

                foreach (SettingSecrets::keysFor($variable) as $secretKey) {
                    if (array_key_exists($secretKey, $rawSettings)) {
                        $settings[$secretKey] = $rawSettings[$secretKey];
                    }
                }
            }

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

            $viewData = [
                'settings' => $settings,
                'variable' => $variable,
                'isDemoUser' => $isDemoUser,
            ];

            // Prepare broadcast settings and realtime state for broadcast driver view
            if ($variable === SettingTypeEnum::BROADCAST_DRIVER()) {
                $broadcastSettings = app(BroadcastSettingsService::class);
                $viewData['broadcastConfigured'] = $broadcastSettings->isConfigured();
                $viewData['realtime'] = $viewData['broadcastConfigured']
                    ? $broadcastSettings->clientConfig(route('admin.support.broadcasting.auth'))
                    : config('support.public_realtime');
            }

            if ($variable === SettingTypeEnum::AI()) {
                $viewData['providerCatalog'] = app(AiManager::class)->getProviderCatalog();
            }

            return view('admin.settings.' . $variable, $viewData);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        } catch (\Exception $e) {
            Log::error('Error loading setting view: ' . $e->getMessage(), [
                'variable' => $variable,
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, __('labels.something_went_wrong'));
        }
    }

    public function whatsapp(): View
    {
        try {
            $this->authorize('viewSetting', [Setting::class, SettingTypeEnum::NOTIFICATION()]);

            $transformedSetting = $this->settingService->getSettingByVariable(SettingTypeEnum::NOTIFICATION());
            if (! $transformedSetting) {
                abort(404, __('labels.setting_not_found'));
            }

            $settings = $transformedSetting->toArray(request())['value'] ?? [];
            $isDemoUser = DemoMode::isDemoUser(request()->user());

            if (! $isDemoUser) {
                $rawSettings = $this->settingService->getSettingValues(SettingTypeEnum::NOTIFICATION());
                foreach (SettingSecrets::keysFor(SettingTypeEnum::NOTIFICATION()) as $secretKey) {
                    if (array_key_exists($secretKey, $rawSettings)) {
                        $settings[$secretKey] = $rawSettings[$secretKey];
                    }
                }
            }

            return view('admin.settings.whatsapp', [
                'settings' => $settings,
                'variable' => SettingTypeEnum::NOTIFICATION(),
                'isDemoUser' => $isDemoUser,
                'whatsAppTemplateModules' => WhatsAppTemplateCatalog::modules(),
                'whatsAppTemplates' => Schema::hasTable('whatsapp_templates')
                    ? WhatsAppTemplate::query()->get()->keyBy('module')
                    : collect(),
                'whatsAppTemplateStatuses' => WhatsAppTemplateCatalog::statuses(),
                'whatsAppParameterFormats' => WhatsAppTemplateCatalog::parameterFormats(),
            ]);
        } catch (AuthorizationException $e) {
            abort(403, __('labels.unauthorized_access'));
        } catch (\Exception $e) {
            Log::error('Error loading WhatsApp setting view: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
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

    public function testWhatsApp(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::NOTIFICATION()]);

        $validated = $request->validate([
            'test_mobile' => ['required', 'string', 'max:32'],
            'module' => ['required', 'string', Rule::in(WhatsAppTemplateCatalog::moduleKeys())],
        ]);

        $result = $whatsAppService->sendModuleTest($validated['module'], $validated['test_mobile']);

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: [
                'gateway_status' => $result['gateway_status'] ?? null,
                'gateway_body' => $result['gateway_body'] ?? null,
            ],
            status: $result['success'] ? 200 : 422
        );
    }

    public function saveWhatsAppTemplates(Request $request): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::NOTIFICATION()]);

        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.module' => ['required', 'string', Rule::in(WhatsAppTemplateCatalog::moduleKeys())],
            'templates.*.template_name' => ['nullable', 'string', 'max:255'],
            'templates.*.language' => ['nullable', 'string', 'max:16'],
            'templates.*.parameter_format' => ['required', 'string', Rule::in(WhatsAppTemplateCatalog::parameterFormats())],
            'templates.*.is_active' => ['nullable', 'boolean'],
            'templates.*.variables' => ['nullable', 'string'],
        ]);

        foreach ($validated['templates'] as $templateInput) {
            $module = $templateInput['module'];
            $templateName = trim((string) ($templateInput['template_name'] ?? ''));
            $format = $templateInput['parameter_format'];
            $variables = $this->decodeWhatsAppVariables($templateInput['variables'] ?? null, $module, $format);
            $existingTemplate = WhatsAppTemplate::where('module', $module)->first();
            $language = $templateInput['language'] ?: 'en_US';
            $templateIdentityChanged = $existingTemplate
                && (
                    (string) $existingTemplate->template_name !== ($templateName ?: null)
                    || (string) $existingTemplate->language !== $language
                );

            if ($templateName === '' && ! $existingTemplate) {
                continue;
            }

            WhatsAppTemplate::updateOrCreate(
                ['module' => $module],
                array_merge([
                    'template_name' => $templateName ?: null,
                    'language' => $language,
                    'parameter_format' => $format,
                    'variables' => $variables,
                    'status' => $templateIdentityChanged
                        ? WhatsAppTemplateCatalog::STATUS_DRAFT
                        : ($existingTemplate?->status ?: WhatsAppTemplateCatalog::STATUS_DRAFT),
                    'is_active' => filter_var($templateInput['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ], $templateIdentityChanged ? [
                    'meta_template_id' => null,
                    'category' => null,
                    'quality_score' => null,
                    'rejected_reason' => null,
                    'components' => null,
                    'last_synced_at' => null,
                    'sync_error' => null,
                ] : [])
            );
        }

        return ApiResponseType::sendJsonResponse(true, 'WhatsApp templates saved successfully.', []);
    }

    public function syncWhatsAppTemplates(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::NOTIFICATION()]);

        $validated = $request->validate([
            'module' => ['nullable', 'string', Rule::in(WhatsAppTemplateCatalog::moduleKeys())],
        ]);

        $result = $whatsAppService->syncConfiguredTemplates($validated['module'] ?? null);

        return ApiResponseType::sendJsonResponse(
            success: (bool) $result['success'],
            message: (string) $result['message'],
            data: ['results' => $result['results']],
            status: $result['success'] ? 200 : 422
        );
    }

    protected function decodeWhatsAppVariables(?string $variables, string $module, string $format): array
    {
        if (! is_string($variables) || trim($variables) === '') {
            return WhatsAppTemplateCatalog::defaultVariables($module, $format);
        }

        $decoded = json_decode($variables, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'templates' => ['Variable mapping must be valid JSON.'],
            ]);
        }

        return collect($decoded)
            ->mapWithKeys(fn (mixed $source, string|int $target) => [(string) $target => (string) $source])
            ->all();
    }

    /**
     * @param array<string, mixed> $settingsInput
     * @return array<string, mixed>
     */
    private function normalizePaymentCurrencySettings(array $settingsInput): array
    {
        $settingsInput['razorpayAllowedCurrencyCodes'] = collect(Arr::flatten([
            $settingsInput['razorpayAllowedCurrencyCodes'] ?? [],
        ]))
            ->flatMap(fn (mixed $value) => explode(',', (string) $value))
            ->map(fn (string $currencyCode) => strtoupper(trim($currencyCode)))
            ->filter(fn (string $currencyCode) => preg_match('/^[A-Z]{3}$/', $currencyCode) === 1)
            ->unique()
            ->values()
            ->all();

        return $settingsInput;
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
