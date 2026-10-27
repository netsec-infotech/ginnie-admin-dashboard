<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CurrencyMetadata;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\Store;
use App\Services\PaymentCurrencyAvailabilityService;
use App\Support\Currency\CurrencyCodeValidator;
use App\Support\Currency\SystemCurrency;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyManagementController extends Controller
{
    use ChecksPermissions;

    private const SAFE_GATEWAY_CURRENCY_KEYS = [
        'stripe' => 'stripeCurrencyCode',
        'paystack' => 'paystackCurrencyCode',
        'flutterwave' => 'flutterwaveCurrencyCode',
        'xendit' => 'xenditCurrencyCode',
        'mercado_pago' => 'mercadoPagoCurrencyCode',
    ];

    public function __construct(
        private readonly CurrencyCodeValidator $currencyCodeValidator,
        private readonly PaymentCurrencyAvailabilityService $paymentCurrencyAvailabilityService
    ) {}

    public function index(): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::DELIVERY_ZONE_VIEW()), 403, __('labels.permission_denied'));

        $systemCurrency = $this->systemCurrencyContext();
        $currencyUsage = $this->currencyUsageCounts();
        $zoneCurrencyUsage = $this->zoneCurrencyUsageCounts();
        $enabledCurrencyCodes = CurrencyMetadata::query()
            ->where('is_enabled', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();
        $countries = $this->countriesUsedInZones($enabledCurrencyCodes);
        $zones = $this->zones($systemCurrency['code']);
        $gateways = $this->gateways($systemCurrency['code']);

        return view('admin.currency-management.index', [
            'systemCurrency' => $systemCurrency,
            'summary' => [
                'countries_in_zones' => count($countries),
                'delivery_zones' => count($zones),
                'zones_needing_setup' => collect($zones)->where('has_missing_metadata', true)->count(),
                'store_mismatches' => collect($zones)->where('has_store_zone_mismatch', true)->count(),
                'gateway_differences' => collect($gateways)->where('differs_from_system', true)->count(),
            ],
            'columns' => $this->datatableColumns(),
        ]);
    }

    public function countriesDatatable(Request $request): JsonResponse
    {
        $enabledCurrencyCodes = CurrencyMetadata::query()
            ->where('is_enabled', true)
            ->pluck('code')
            ->all();

        return $this->datatableResponse($request, $this->countriesUsedInZones($enabledCurrencyCodes), [
            'country',
            'iso2',
            'currency',
            'zone_count',
            'store_count',
            'zones_text',
            'review',
        ], fn (array $country) => [
            'country' => e($country['country']),
            'iso2' => e($country['iso2'] ?: '-'),
            'currency' => $this->badge(trim(($country['currency'] ?: 'Missing') . ' ' . ($country['currency_symbol'] ?: '')), 'bg-blue-lt'),
            'zone_count' => $country['zone_count'],
            'store_count' => $country['store_count'],
            'zones_text' => e($this->listText($country['zones'])),
            'review' => $this->countryReviewBadges($country),
        ]);
    }

    public function zonesDatatable(Request $request): JsonResponse
    {
        return $this->datatableResponse($request, $this->zones($this->systemCurrencyContext()['code']), [
            'name',
            'country',
            'currency_code',
            'store_count',
            'countries_text',
            'currencies_text',
            'review',
        ], fn (array $zone) => [
            'name' => e($zone['name']),
            'country' => e($zone['country'] ?: ($zone['country_code'] ?: '-')),
            'currency_code' => $this->badge(trim(($zone['currency_code'] ?: 'Missing') . ' ' . ($zone['currency_symbol'] ?: '')), 'bg-blue-lt'),
            'store_count' => $zone['store_count'],
            'countries_text' => e($this->listText($zone['countries'])),
            'currencies_text' => e($this->listText($zone['currencies'])),
            'review' => $this->zoneReviewBadges($zone),
        ]);
    }

    public function storesDatatable(Request $request): JsonResponse
    {
        $systemCurrency = $this->systemCurrencyContext();
        $enabledCurrencyCodes = CurrencyMetadata::query()
            ->where('is_enabled', true)
            ->pluck('code')
            ->all();

        return $this->datatableResponse($request, $this->stores($systemCurrency['code'], $enabledCurrencyCodes), [
            'name',
            'country_code',
            'currency_code',
            'zones_text',
            'review',
        ], fn (array $store) => [
            'name' => e($store['name']),
            'country_code' => e($store['country_code'] ?: '-'),
            'currency_code' => $this->badge($store['currency_code'] ?: 'Missing', 'bg-blue-lt'),
            'zones_text' => e($this->listText($store['zones'])),
            'review' => $this->storeReviewBadges($store),
        ]);
    }

    public function gatewaysDatatable(Request $request): JsonResponse
    {
        return $this->datatableResponse($request, $this->gateways($this->systemCurrencyContext()['code']), [
            'gateway',
            'currency_code',
            'allowed_currencies_text',
            'status',
        ], fn (array $gateway) => [
            'gateway' => e($gateway['label'] ?? str_replace('_', ' ', ucfirst($gateway['gateway']))),
            'currency_code' => e($gateway['currency_code'] ?: '-'),
            'allowed_currencies_text' => e($this->listText($gateway['allowed_currencies'] ?? [])),
            'status' => $this->gatewayReviewBadges($gateway),
        ]);
    }

    public function currenciesDatatable(Request $request): JsonResponse
    {
        $systemCurrency = $this->systemCurrencyContext();

        return $this->datatableResponse($request, $this->currencies(
            $this->currencyUsageCounts(),
            $this->zoneCurrencyUsageCounts(),
            $systemCurrency['code']
        ), [
            'code',
            'name',
            'symbol',
            'decimal_places',
            'status',
            'usage_text',
            'usage_count',
        ], fn (array $currency) => [
            'code' => '<strong>' . e($currency['code']) . '</strong>',
            'name' => e($currency['name'] ?? '-'),
            'symbol' => e($currency['symbol'] ?? '-'),
            'decimal_places' => e($currency['decimal_places'] . ' decimals'),
            'status' => $this->currencyStatusBadges($currency),
            'usage_text' => e($currency['country_usage_count'] . ' countries / ' . $currency['zone_usage_count'] . ' zones')
                . '<div class="text-secondary small">' . e($this->listText(array_merge($currency['country_names'], $currency['zone_names']))) . '</div>',
            'usage_count' => $currency['usage_count'],
        ]);
    }

    public function gatewayReadinessDatatable(Request $request): JsonResponse
    {
        return $this->datatableResponse($request, $this->gatewayReadiness(), [
            'currency_code',
            'zones_text',
            'available_gateways_text',
            'blocked_gateways_text',
            'warnings',
        ], fn (array $readiness) => [
            'currency_code' => $this->badge(trim($readiness['currency_code'] . ' ' . ($readiness['currency_symbol'] ?: '')), 'bg-blue-lt'),
            'zones_text' => e($this->listText($readiness['zones'])),
            'available_gateways_text' => e($this->listText($readiness['available_gateways'])),
            'blocked_gateways_text' => e($this->listText($readiness['blocked_gateways'])),
            'warnings' => $this->readinessWarningBadges($readiness),
        ]);
    }

    public function toggleCurrency(Request $request, string $currency): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_EDIT()), 403, __('labels.permission_denied'));

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $currency = $this->currencyCodeValidator->normalize($currency);
        abort_unless($currency && $this->currencyCodeValidator->isValid($currency), 422, __('labels.invalid_currency'));

        $metadata = CurrencyMetadata::query()->whereKey($currency)->firstOrFail();
        $usageCount = $this->currencyUsageCounts()[$currency] ?? 0;

        if (! $validated['is_enabled'] && $usageCount > 0) {
            return ApiResponseType::sendJsonResponse(false, 'Currency is already used by financial records and cannot be disabled.', [
                'currency_code' => $currency,
                'usage_count' => $usageCount,
            ], 422);
        }

        $metadata->update(['is_enabled' => (bool) $validated['is_enabled']]);

        return ApiResponseType::sendJsonResponse(true, 'Currency setting updated.', [
            'currency_code' => $metadata->code,
            'is_enabled' => $metadata->is_enabled,
        ]);
    }

    public function updateStoreCurrency(Request $request, Store|string $store): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_EDIT()), 403, __('labels.permission_denied'));

        $store = $store instanceof Store ? $store : Store::query()->findOrFail((int) $store);

        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currency_metadata', 'code')->where('is_enabled', true)],
        ]);

        $currencyCode = $this->currencyCodeValidator->normalize($validated['currency_code']);
        abort_unless($currencyCode && $this->currencyCodeValidator->isValid($currencyCode), 422, __('labels.invalid_currency'));

        if ($this->storeHasFinancialHistory($store)) {
            return ApiResponseType::sendJsonResponse(false, 'Store already has financial history. Review and migrate manually before changing its pricing currency.', [
                'store_id' => $store->id,
                'current_currency_code' => $store->currency_code,
            ], 422);
        }

        $store->update(['currency_code' => $currencyCode]);

        return ApiResponseType::sendJsonResponse(true, 'Store currency updated.', [
            'store_id' => $store->id,
            'currency_code' => $store->currency_code,
        ]);
    }

    public function audit(): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_VIEW()), 403, __('labels.permission_denied'));

        Artisan::call('hyperlocal:currency-audit', [
            '--json' => true,
            '--no-interaction' => true,
        ]);

        return response()->json(json_decode(Artisan::output(), true) ?: [
            'summary' => ['final_status' => 'REVIEW REQUIRED'],
            'findings' => [],
        ]);
    }

    public function backfill(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_EDIT()), 403, __('labels.permission_denied'));

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['dry-run', 'execute'])],
        ]);

        $arguments = [
            '--json' => true,
            '--no-interaction' => true,
        ];

        if ($validated['mode'] === 'execute') {
            $arguments['--execute'] = true;
        } else {
            $arguments['--dry-run'] = true;
        }

        Artisan::call('hyperlocal:currency-backfill', $arguments);

        return response()->json(json_decode(Artisan::output(), true) ?: [
            'summary' => ['final_status' => 'REVIEW REQUIRED'],
            'findings' => [],
        ]);
    }

    /**
     * @return array{code:string,symbol:?string,country_code:?string,source:string}
     */
    private function systemCurrencyContext(): array
    {
        $settings = Setting::systemSettings();

        return [
            'code' => SystemCurrency::code(),
            'symbol' => $settings['currencySymbol'] ?? null,
            'country_code' => $settings['countryCode'] ?? null,
            'source' => 'settings.system.currency',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function currencies(array $currencyUsage, array $zoneCurrencyUsage, string $systemCurrencyCode): array
    {
        $zoneCurrencyCodes = array_keys($zoneCurrencyUsage);
        $currencies = CurrencyMetadata::query()
            ->whereIn('code', $zoneCurrencyCodes)
            ->orderBy('code')
            ->get()
            ->map(function (CurrencyMetadata $currency) use ($currencyUsage, $zoneCurrencyUsage, $systemCurrencyCode) {
                $usageCount = $currencyUsage[$currency->code] ?? 0;
                $zoneUsage = $zoneCurrencyUsage[$currency->code] ?? [
                    'zone_count' => 0,
                    'country_count' => 0,
                    'zones' => [],
                    'countries' => [],
                ];

                return [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => $currency->decimal_places,
                    'minor_unit_factor' => $currency->minor_unit_factor,
                    'is_enabled' => $currency->is_enabled,
                    'is_system_currency' => $currency->code === $systemCurrencyCode,
                    'usage_count' => $usageCount,
                    'zone_usage_count' => $zoneUsage['zone_count'],
                    'country_usage_count' => $zoneUsage['country_count'],
                    'zone_names' => $zoneUsage['zones'],
                    'country_names' => $zoneUsage['countries'],
                    'is_used_by_zones' => $zoneUsage['zone_count'] > 0,
                    'can_disable' => $usageCount === 0 && $currency->code !== $systemCurrencyCode,
                ];
            })
            ->keyBy('code');

        foreach ($zoneCurrencyUsage as $currencyCode => $zoneUsage) {
            if ($currencies->has($currencyCode)) {
                continue;
            }

            $metadata = $this->currencyCodeValidator->metadata($currencyCode);
            $currencies->put($currencyCode, [
                'code' => $currencyCode,
                'name' => $metadata['name'] ?? null,
                'symbol' => $metadata['symbol'] ?? null,
                'decimal_places' => $metadata['decimal_places'],
                'minor_unit_factor' => $metadata['minor_unit_factor'],
                'is_enabled' => false,
                'is_system_currency' => $currencyCode === $systemCurrencyCode,
                'usage_count' => $currencyUsage[$currencyCode] ?? 0,
                'zone_usage_count' => $zoneUsage['zone_count'],
                'country_usage_count' => $zoneUsage['country_count'],
                'zone_names' => $zoneUsage['zones'],
                'country_names' => $zoneUsage['countries'],
                'is_used_by_zones' => $zoneUsage['zone_count'] > 0,
                'can_disable' => false,
            ]);
        }

        return $currencies
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countries(array $enabledCurrencyCodes): array
    {
        return Country::query()
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'name', 'iso2', 'currency', 'currency_name', 'currency_symbol'])
            ->map(function (Country $country) use ($enabledCurrencyCodes) {
                $currencyCode = $this->currencyCodeValidator->normalize($country->currency);
                $classification = $this->currencyCodeValidator->classify($currencyCode);

                return [
                    'id' => $country->id,
                    'name' => $country->name,
                    'iso2' => $country->iso2,
                    'currency' => $currencyCode,
                    'currency_name' => $country->currency_name,
                    'currency_symbol' => $country->currency_symbol,
                    'classification' => $classification,
                    'is_enabled' => $currencyCode ? in_array($currencyCode, $enabledCurrencyCodes, true) : false,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countriesUsedInZones(array $enabledCurrencyCodes): array
    {
        return DeliveryZone::query()
            ->with(['country:id,name,iso2,currency,currency_symbol', 'stores:id,name,currency_code'])
            ->orderBy('name')
            ->get(['id', 'name', 'country_id', 'country_code', 'currency_code', 'currency_symbol'])
            ->groupBy(fn (DeliveryZone $zone) => $zone->country_id ? 'country:' . $zone->country_id : 'unassigned:' . ($zone->country_code ?: 'missing'))
            ->map(function ($zones) use ($enabledCurrencyCodes) {
                /** @var DeliveryZone $firstZone */
                $firstZone = $zones->first();
                $country = $firstZone->country;
                $currencyCode = $this->currencyCodeValidator->normalize($firstZone->currency_code ?: $country?->currency);
                $classification = $this->currencyCodeValidator->classify($currencyCode);

                return [
                    'country' => $country?->name ?? 'Unassigned',
                    'iso2' => $country?->iso2 ?? $firstZone->country_code,
                    'currency' => $currencyCode,
                    'currency_symbol' => $firstZone->currency_symbol ?: $country?->currency_symbol,
                    'classification' => $classification,
                    'is_enabled' => $currencyCode ? in_array($currencyCode, $enabledCurrencyCodes, true) : false,
                    'zone_count' => $zones->count(),
                    'store_count' => $zones->sum(fn (DeliveryZone $zone) => $zone->stores->count()),
                    'zones' => $zones->pluck('name')->values()->all(),
                    'needs_review' => blank($firstZone->country_id) || blank($currencyCode) || $classification !== 'valid',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stores(string $systemCurrencyCode, array $enabledCurrencyCodes): array
    {
        return Store::query()
            ->with('zones:id,name')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'country_code', 'currency_code'])
            ->map(function (Store $store) use ($systemCurrencyCode, $enabledCurrencyCodes) {
                $currencyCode = $this->currencyCodeValidator->normalize($store->currency_code);
                $classification = $this->currencyCodeValidator->classify($currencyCode);

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'country_code' => $store->country_code,
                    'currency_code' => $currencyCode,
                    'classification' => $classification,
                    'is_enabled_currency' => $currencyCode ? in_array($currencyCode, $enabledCurrencyCodes, true) : false,
                    'differs_from_system' => $currencyCode !== null && $currencyCode !== $systemCurrencyCode,
                    'has_financial_history' => $this->storeHasFinancialHistory($store),
                    'zones' => $store->zones->pluck('name')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Zone currency comes from explicit zone metadata. Geometry and names are
     * never used as a currency source.
     *
     * @return array<int, array<string, mixed>>
     */
    private function zones(string $systemCurrencyCode): array
    {
        return DeliveryZone::query()
            ->with(['country:id,name,iso2,currency,currency_symbol', 'stores:id,name,country_code,currency_code'])
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'status', 'country_id', 'country_code', 'currency_code', 'currency_symbol', 'country_detection_status'])
            ->map(function (DeliveryZone $zone) use ($systemCurrencyCode) {
                $countries = $zone->stores->pluck('country_code')->filter()->unique()->values();
                $currencies = $zone->stores->pluck('currency_code')->filter()->map(fn ($code) => $this->currencyCodeValidator->normalize($code))->filter()->unique()->values();
                $zoneCurrencyCode = $this->currencyCodeValidator->normalize($zone->currency_code);

                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'status' => $zone->status,
                    'country' => $zone->country?->name,
                    'country_code' => $zone->country_code,
                    'currency_code' => $zoneCurrencyCode,
                    'currency_symbol' => $zone->currency_symbol,
                    'country_detection_status' => $zone->country_detection_status,
                    'store_count' => $zone->stores->count(),
                    'countries' => $countries->all(),
                    'currencies' => $currencies->all(),
                    'has_multiple_countries' => $countries->count() > 1,
                    'has_multiple_currencies' => $currencies->count() > 1,
                    'has_system_mismatch' => $currencies->contains(fn ($code) => $code !== $systemCurrencyCode),
                    'has_missing_metadata' => blank($zone->country_id) || blank($zoneCurrencyCode) || $zone->stores->contains(fn ($store) => blank($store->country_code) || blank($store->currency_code)),
                    'has_store_zone_mismatch' => $zoneCurrencyCode !== null && $currencies->contains(fn ($code) => $code !== $zoneCurrencyCode),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gateways(string $systemCurrencyCode): array
    {
        $settings = Setting::query()->whereKey('payment')->value('value');
        $settings = is_string($settings) ? json_decode($settings, true) : $settings;
        $settings = is_array($settings) ? $settings : [];

        $gateways = [];
        foreach (self::SAFE_GATEWAY_CURRENCY_KEYS as $gateway => $key) {
            $currencyCode = $this->currencyCodeValidator->normalize($settings[$key] ?? null);
            $gateways[] = [
                'gateway' => $gateway,
                'label' => str_replace('_', ' ', ucfirst($gateway)),
                'currency_code' => $currencyCode,
                'allowed_currencies' => $currencyCode ? [$currencyCode] : [],
                'classification' => $this->currencyCodeValidator->classify($currencyCode),
                'differs_from_system' => $currencyCode !== null && $currencyCode !== $systemCurrencyCode,
                'configured' => ! blank($currencyCode),
            ];
        }

        if (! empty($settings['razorpayPayment'])) {
            $capability = collect($this->paymentCurrencyAvailabilityService->settingsMetadata()['payment_gateway_currency_capabilities'] ?? [])
                ->firstWhere('key', PaymentTypeEnum::RAZORPAY());
            $allowedCurrencies = $capability['allowed_currencies'] ?? ['INR'];

            $gateways[] = [
                'gateway' => 'razorpay',
                'label' => 'Razorpay',
                'currency_code' => null,
                'allowed_currencies' => $allowedCurrencies,
                'classification' => 'valid',
                'differs_from_system' => ! in_array($systemCurrencyCode, $allowedCurrencies, true),
                'configured' => true,
                'requires_account_approval' => true,
            ];
        }

        return $gateways;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function datatableColumns(): array
    {
        return [
            'countries' => [
                ['data' => 'country', 'name' => 'country', 'label' => 'Country'],
                ['data' => 'iso2', 'name' => 'iso2', 'label' => 'ISO2'],
                ['data' => 'currency', 'name' => 'currency', 'label' => 'Currency'],
                ['data' => 'zone_count', 'name' => 'zone_count', 'label' => 'Zones'],
                ['data' => 'store_count', 'name' => 'store_count', 'label' => 'Stores'],
                ['data' => 'zones_text', 'name' => 'zones_text', 'label' => 'Used In Zones'],
                ['data' => 'review', 'name' => 'review', 'label' => 'Review', 'orderable' => false],
            ],
            'zones' => [
                ['data' => 'name', 'name' => 'name', 'label' => 'Zone'],
                ['data' => 'country', 'name' => 'country', 'label' => 'Country'],
                ['data' => 'currency_code', 'name' => 'currency_code', 'label' => 'Zone Currency'],
                ['data' => 'store_count', 'name' => 'store_count', 'label' => 'Stores'],
                ['data' => 'countries_text', 'name' => 'countries_text', 'label' => 'Store Countries'],
                ['data' => 'currencies_text', 'name' => 'currencies_text', 'label' => 'Store Currencies'],
                ['data' => 'review', 'name' => 'review', 'label' => 'Review', 'orderable' => false],
            ],
            'stores' => [
                ['data' => 'name', 'name' => 'name', 'label' => 'Store'],
                ['data' => 'country_code', 'name' => 'country_code', 'label' => 'Country Code'],
                ['data' => 'currency_code', 'name' => 'currency_code', 'label' => 'Store Currency'],
                ['data' => 'zones_text', 'name' => 'zones_text', 'label' => 'Linked Zones'],
                ['data' => 'review', 'name' => 'review', 'label' => 'Review', 'orderable' => false],
            ],
            'gateways' => [
                ['data' => 'gateway', 'name' => 'gateway', 'label' => 'Gateway'],
                ['data' => 'currency_code', 'name' => 'currency_code', 'label' => 'Configured Currency'],
                ['data' => 'allowed_currencies_text', 'name' => 'allowed_currencies_text', 'label' => 'Allowed Currencies'],
                ['data' => 'status', 'name' => 'status', 'label' => 'Status', 'orderable' => false],
            ],
            'currencies' => [
                ['data' => 'code', 'name' => 'code', 'label' => 'Code'],
                ['data' => 'name', 'name' => 'name', 'label' => 'Name'],
                ['data' => 'symbol', 'name' => 'symbol', 'label' => 'Symbol'],
                ['data' => 'decimal_places', 'name' => 'decimal_places', 'label' => 'Precision'],
                ['data' => 'status', 'name' => 'status', 'label' => 'Status', 'orderable' => false],
                ['data' => 'usage_text', 'name' => 'usage_text', 'label' => 'Country / Zone Usage'],
                ['data' => 'usage_count', 'name' => 'usage_count', 'label' => 'Financial Usage'],
            ],
            'gateway_readiness' => [
                ['data' => 'currency_code', 'name' => 'currency_code', 'label' => 'Zone Currency'],
                ['data' => 'zones_text', 'name' => 'zones_text', 'label' => 'Zones'],
                ['data' => 'available_gateways_text', 'name' => 'available_gateways_text', 'label' => 'Available Gateways'],
                ['data' => 'blocked_gateways_text', 'name' => 'blocked_gateways_text', 'label' => 'Blocked Gateways'],
                ['data' => 'warnings', 'name' => 'warnings', 'label' => 'Warnings', 'orderable' => false],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $sortable
     */
    private function datatableResponse(Request $request, array $rows, array $sortable, callable $formatter): JsonResponse
    {
        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 10)));
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $orderIndex = (int) data_get($request->input('order'), '0.column', 0);
        $orderDirection = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy = $sortable[$orderIndex] ?? $sortable[0];

        $collection = collect($rows);
        $recordsTotal = $collection->count();

        if ($search !== '') {
            $collection = $collection->filter(fn (array $row) => str_contains(
                strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                strtolower($search)
            ));
        }

        $recordsFiltered = $collection->count();
        $sorted = $collection->sortBy(fn (array $row) => data_get($row, $orderBy, ''), SORT_REGULAR, $orderDirection === 'desc');

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $sorted
                ->slice($start, $length)
                ->values()
                ->map(fn (array $row) => $formatter($row))
                ->all(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gatewayReadiness(): array
    {
        $onlineGateways = [
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::RAZORPAY(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
        ];

        return collect($this->zoneCurrencyUsageCounts())
            ->map(function (array $usage, string $currencyCode) use ($onlineGateways) {
                $availability = collect($this->paymentCurrencyAvailabilityService->availabilityForCurrency($currencyCode, $onlineGateways))
                    ->filter(fn (array $method) => $method['is_enabled'])
                    ->values();
                $available = $availability
                    ->filter(fn (array $method) => $method['is_available'])
                    ->pluck('label')
                    ->values()
                    ->all();
                $blocked = $availability
                    ->reject(fn (array $method) => $method['is_available'])
                    ->map(fn (array $method) => $method['label'] . ': ' . $method['message'])
                    ->values()
                    ->all();
                $warnings = [];

                if ($available === []) {
                    $warnings[] = 'No online gateway supports this zone currency';
                }

                if ($availability->contains(fn (array $method) => $method['key'] === PaymentTypeEnum::RAZORPAY()
                    && $currencyCode !== 'INR'
                    && $method['reason_code'] === 'currency_not_supported')) {
                    $warnings[] = 'Razorpay international currencies are not configured';
                }

                if ($availability->contains(fn (array $method) => ! empty($method['configured_currency']) && $method['configured_currency'] !== $currencyCode)) {
                    $warnings[] = 'Gateway currency differs from this zone currency';
                }

                $metadata = $this->currencyCodeValidator->metadata($currencyCode);

                return [
                    'currency_code' => $currencyCode,
                    'currency_symbol' => $metadata['symbol'] ?? null,
                    'zones' => $usage['zones'],
                    'available_gateways' => $available,
                    'blocked_gateways' => $blocked,
                    'warnings' => $warnings,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    private function listText(array $values, int $limit = 5): string
    {
        $values = array_values(array_filter($values, fn ($value) => filled($value)));

        if ($values === []) {
            return '-';
        }

        $shown = array_slice($values, 0, $limit);
        $suffix = count($values) > $limit ? ' +' . (count($values) - $limit) : '';

        return implode(', ', $shown) . $suffix;
    }

    private function badge(string $label, string $class): string
    {
        return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
    }

    private function countryReviewBadges(array $country): string
    {
        $badges = [$this->badge($country['classification'], $country['classification'] === 'valid' ? 'bg-green-lt' : 'bg-red-lt')];

        if ($country['classification'] === 'valid' && ! $country['is_enabled']) {
            $badges[] = $this->badge('Valid but disabled', 'bg-yellow-lt');
        }

        if ($country['needs_review']) {
            $badges[] = $this->badge('Review required', 'bg-orange-lt');
        }

        return implode(' ', $badges);
    }

    private function zoneReviewBadges(array $zone): string
    {
        $badges = [];

        if ($zone['has_multiple_countries']) {
            $badges[] = $this->badge('Multiple store countries', 'bg-yellow-lt');
        }

        if ($zone['has_multiple_currencies']) {
            $badges[] = $this->badge('Multiple store currencies', 'bg-red-lt');
        }

        if ($zone['has_missing_metadata']) {
            $badges[] = $this->badge('Setup missing', 'bg-secondary-lt');
        }

        if ($zone['has_store_zone_mismatch']) {
            $badges[] = $this->badge('Store/zone mismatch', 'bg-red-lt');
        }

        return $badges === [] ? $this->badge('Clean', 'bg-green-lt') : implode(' ', $badges);
    }

    private function storeReviewBadges(array $store): string
    {
        $badges = [];

        if ($store['classification'] !== 'valid') {
            $badges[] = $this->badge('Invalid currency', 'bg-red-lt');
        }

        if (! $store['is_enabled_currency']) {
            $badges[] = $this->badge('Not enabled', 'bg-yellow-lt');
        }

        if ($store['differs_from_system']) {
            $badges[] = $this->badge('Differs from system', 'bg-orange-lt');
        }

        if ($store['has_financial_history']) {
            $badges[] = $this->badge('Has history', 'bg-secondary-lt');
        }

        return $badges === [] ? $this->badge('Clean', 'bg-green-lt') : implode(' ', $badges);
    }

    private function gatewayReviewBadges(array $gateway): string
    {
        $badges = [$this->badge($gateway['classification'], $gateway['classification'] === 'valid' ? 'bg-green-lt' : 'bg-secondary-lt')];

        if ($gateway['differs_from_system']) {
            $badges[] = $this->badge('Differs from system', 'bg-yellow-lt');
        }

        if (! empty($gateway['requires_account_approval'])) {
            $badges[] = $this->badge('Approval needed for non-INR', 'bg-orange-lt');
        }

        return implode(' ', $badges);
    }

    private function currencyStatusBadges(array $currency): string
    {
        $badges = [];

        if ($currency['is_system_currency']) {
            $badges[] = $this->badge('System Currency', 'bg-blue-lt');
        }

        $badges[] = $this->badge($currency['is_enabled'] ? 'Enabled' : 'Disabled', $currency['is_enabled'] ? 'bg-green-lt' : 'bg-secondary-lt');

        if ($currency['is_used_by_zones'] && ! $currency['is_enabled']) {
            $badges[] = $this->badge('Used by zones', 'bg-yellow-lt');
        }

        return implode(' ', $badges);
    }

    private function readinessWarningBadges(array $readiness): string
    {
        if ($readiness['warnings'] === []) {
            return $this->badge('Ready', 'bg-green-lt');
        }

        return collect($readiness['warnings'])
            ->map(fn (string $warning) => $this->badge($warning, 'bg-yellow-lt'))
            ->implode(' ');
    }

    /**
     * @return array<string, int>
     */
    private function currencyUsageCounts(): array
    {
        $tables = [
            ['orders', 'currency_code'],
            ['order_payment_transactions', 'currency'],
            ['order_payment_transactions', 'order_currency_code'],
            ['order_payment_transactions', 'gateway_currency_code'],
            ['wallets', 'currency_code'],
            ['wallet_transactions', 'currency_code'],
            ['seller_statements', 'currency_code'],
            ['pos_payment_sessions', 'currency_code'],
            ['pos_refunds', 'currency_code'],
            ['stores', 'currency_code'],
        ];

        $counts = [];
        foreach ($tables as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select($column, DB::raw('COUNT(*) as aggregate_count'))
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->get()
                ->each(function ($row) use (&$counts, $column) {
                    $currencyCode = $this->currencyCodeValidator->normalize($row->{$column});
                    if ($currencyCode !== null) {
                        $counts[$currencyCode] = ($counts[$currencyCode] ?? 0) + (int) $row->aggregate_count;
                    }
                });
        }

        return $counts;
    }

    /**
     * Operational currency usage comes from explicit delivery zone metadata only.
     * Zone names, geometry, and boundaries are intentionally not treated as currency sources.
     *
     * @return array<string, array{zone_count:int,country_count:int,zones:array<int, string>,countries:array<int, string>}>
     */
    private function zoneCurrencyUsageCounts(): array
    {
        $usage = [];

        DeliveryZone::query()
            ->with('country:id,name,iso2')
            ->orderBy('name')
            ->get(['id', 'name', 'country_id', 'country_code', 'currency_code'])
            ->each(function (DeliveryZone $zone) use (&$usage) {
                $currencyCode = $this->currencyCodeValidator->normalize($zone->currency_code);
                if ($currencyCode === null) {
                    return;
                }

                $usage[$currencyCode] ??= [
                    'zone_count' => 0,
                    'country_keys' => [],
                    'zones' => [],
                    'countries' => [],
                ];

                $usage[$currencyCode]['zone_count']++;
                if (count($usage[$currencyCode]['zones']) < 5) {
                    $usage[$currencyCode]['zones'][] = $zone->name;
                }

                $countryKey = $zone->country_id ? 'country:' . $zone->country_id : 'code:' . ($zone->country_code ?: 'missing');
                $countryName = $zone->country?->name ?: ($zone->country_code ?: 'Unassigned');
                if (! isset($usage[$currencyCode]['country_keys'][$countryKey])) {
                    $usage[$currencyCode]['country_keys'][$countryKey] = true;
                    if (count($usage[$currencyCode]['countries']) < 5) {
                        $usage[$currencyCode]['countries'][] = $countryName;
                    }
                }
            });

        return collect($usage)
            ->map(fn (array $item) => [
                'zone_count' => $item['zone_count'],
                'country_count' => count($item['country_keys']),
                'zones' => $item['zones'],
                'countries' => $item['countries'],
            ])
            ->all();
    }

    private function storeHasFinancialHistory(Store $store): bool
    {
        return Schema::hasTable('order_items')
            && Schema::hasColumn('order_items', 'store_id')
            && DB::table('order_items')->where('store_id', $store->id)->exists();
    }
}
