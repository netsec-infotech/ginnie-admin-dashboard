<?php

namespace App\Services;

use App\Enums\Payment\FlutterwaveCurrencyCodeEnum;
use App\Enums\Payment\MercadoPagoCurrencyCodeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Payment\PaystackCurrencyCodeEnum;
use App\Enums\Payment\StripeCurrencyCodeEnum;
use App\Enums\Payment\XenditCurrencyCodeEnum;
use App\Enums\SettingTypeEnum;
use App\Support\Currency\CurrencyCodeValidator;
use Illuminate\Support\Arr;

class PaymentCurrencyAvailabilityService
{
    public function __construct(
        private SettingService $settingService,
        private CurrencyCodeValidator $currencyValidator
    ) {}

    /**
     * Safe gateway capability data for frontend settings. This intentionally
     * excludes credentials and still requires contextual checkout validation.
     *
     * @return array<string, mixed>
     */
    public function settingsMetadata(): array
    {
        $settings = $this->paymentSettings();
        $methods = [
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::RAZORPAY(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
            PaymentTypeEnum::COD(),
            PaymentTypeEnum::WALLET(),
        ];

        $capabilities = [];
        foreach ($methods as $method) {
            $capabilities[] = $this->capabilityFor($method, $settings);
        }

        return [
            'enabled_payment_gateways' => collect($capabilities)
                ->filter(fn (array $capability) => $capability['is_enabled'])
                ->pluck('key')
                ->values()
                ->all(),
            'payment_gateway_currency_capabilities' => $capabilities,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availabilityForCurrency(?string $currencyCode, array $methods = [], ?string $walletCurrencyCode = null): array
    {
        $methods = $methods ?: [
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::RAZORPAY(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
            PaymentTypeEnum::COD(),
            PaymentTypeEnum::WALLET(),
        ];

        return array_map(
            fn (string $method) => $this->availabilityForMethod($method, $currencyCode, $walletCurrencyCode),
            $methods
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function availabilityForMethod(string $method, ?string $currencyCode, ?string $walletCurrencyCode = null): array
    {
        $settings = $this->paymentSettings();
        $normalizedMethod = $this->normalizeMethod($method);
        $currency = $this->normalizeCurrency($currencyCode);
        $capability = $this->capabilityFor($normalizedMethod, $settings);

        if (! $capability['is_enabled']) {
            return $this->withAvailability($capability, $currency, false, 'gateway_disabled', 'This payment method is not enabled.');
        }

        if (! $currency || ! $this->currencyValidator->isValid($currency)) {
            return $this->withAvailability($capability, $currency, false, 'invalid_currency', 'A valid order currency is required for this payment method.');
        }

        if ($normalizedMethod === PaymentTypeEnum::WALLET()) {
            $walletCurrency = $this->normalizeCurrency($walletCurrencyCode);
            if ($walletCurrency && $walletCurrency !== $currency) {
                return $this->withAvailability($capability, $currency, false, 'wallet_currency_mismatch', 'Wallet currency does not match the order currency.');
            }

            return $this->withAvailability($capability, $currency, true, null, 'Available for this currency.');
        }

        if ($normalizedMethod === PaymentTypeEnum::COD()) {
            return $this->withAvailability($capability, $currency, true, null, 'Available for this currency.');
        }

        if ($this->requiresConfiguredCurrency($normalizedMethod) && empty($capability['configured_currency'])) {
            return $this->withAvailability($capability, $currency, false, 'gateway_currency_not_configured', 'Payment gateway currency is not configured.');
        }

        $allowedCurrencies = $capability['allowed_currencies'];
        if ($allowedCurrencies !== [] && ! in_array($currency, $allowedCurrencies, true)) {
            return $this->withAvailability($capability, $currency, false, 'currency_not_supported', 'This payment method does not support the order currency. Please choose another method.');
        }

        if ($normalizedMethod === PaymentTypeEnum::RAZORPAY() && $currency !== 'INR' && empty($allowedCurrencies)) {
            return $this->withAvailability($capability, $currency, false, 'razorpay_allowed_currency_required', 'Razorpay international payments require approved allowed currencies before using this order currency.');
        }

        return $this->withAvailability($capability, $currency, true, null, 'Available for this currency.');
    }

    public function isAvailable(string $method, ?string $currencyCode, ?string $walletCurrencyCode = null): bool
    {
        return (bool) $this->availabilityForMethod($method, $currencyCode, $walletCurrencyCode)['is_available'];
    }

    public function normalizeMethod(string $method): string
    {
        return match ($method) {
            'razorpay' => PaymentTypeEnum::RAZORPAY(),
            'stripe' => PaymentTypeEnum::STRIPE(),
            'paystack' => PaymentTypeEnum::PAYSTACK(),
            'flutterwave' => PaymentTypeEnum::FLUTTERWAVE(),
            'xendit' => PaymentTypeEnum::XENDIT(),
            'mercadopago', 'mercado_pago' => PaymentTypeEnum::MERCADO_PAGO(),
            default => $method,
        };
    }

    public function gatewayName(string $method): string
    {
        return match ($this->normalizeMethod($method)) {
            PaymentTypeEnum::RAZORPAY() => 'razorpay',
            PaymentTypeEnum::STRIPE() => 'stripe',
            PaymentTypeEnum::PAYSTACK() => 'paystack',
            PaymentTypeEnum::FLUTTERWAVE() => 'flutterwave',
            PaymentTypeEnum::XENDIT() => 'xendit',
            PaymentTypeEnum::MERCADO_PAGO() => 'mercadopago',
            PaymentTypeEnum::COD() => 'cod',
            PaymentTypeEnum::WALLET() => 'wallet',
            default => $method,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilityFor(string $method, array $settings): array
    {
        $normalizedMethod = $this->normalizeMethod($method);
        $configuredCurrency = $this->configuredCurrency($normalizedMethod, $settings);
        $allowedCurrencies = $this->allowedCurrencies($normalizedMethod, $settings, $configuredCurrency);

        return [
            'key' => $normalizedMethod,
            'name' => $this->gatewayName($normalizedMethod),
            'label' => $this->label($normalizedMethod),
            'mode' => $this->mode($normalizedMethod),
            'is_enabled' => $this->isEnabled($normalizedMethod, $settings),
            'configured_currency' => $configuredCurrency,
            'allowed_currencies' => $allowedCurrencies,
            'requires_account_approval' => $normalizedMethod === PaymentTypeEnum::RAZORPAY(),
        ];
    }

    private function isEnabled(string $method, array $settings): bool
    {
        if ($method === PaymentTypeEnum::WALLET()) {
            return filter_var($settings['walletPayment'] ?? $settings['wallet'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return filter_var($settings[$method] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function configuredCurrency(string $method, array $settings): ?string
    {
        $key = match ($method) {
            PaymentTypeEnum::STRIPE() => 'stripeCurrencyCode',
            PaymentTypeEnum::PAYSTACK() => 'paystackCurrencyCode',
            PaymentTypeEnum::FLUTTERWAVE() => 'flutterwaveCurrencyCode',
            PaymentTypeEnum::XENDIT() => 'xenditCurrencyCode',
            PaymentTypeEnum::MERCADO_PAGO() => 'mercadoPagoCurrencyCode',
            default => null,
        };

        return $key ? $this->normalizeCurrency($settings[$key] ?? null) : null;
    }

    private function requiresConfiguredCurrency(string $method): bool
    {
        return in_array($method, [
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
        ], true);
    }

    /**
     * @return string[]
     */
    private function allowedCurrencies(string $method, array $settings, ?string $configuredCurrency): array
    {
        if ($method === PaymentTypeEnum::RAZORPAY()) {
            $allowed = $this->normalizeCurrencyList($settings['razorpayAllowedCurrencyCodes'] ?? []);
            if (filter_var($settings['razorpayInternationalPayments'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                if ($allowed === []) {
                    return [];
                }

                return array_values(array_unique(array_merge(['INR'], $allowed)));
            }

            return ['INR'];
        }

        if ($method === PaymentTypeEnum::STRIPE()) {
            return $configuredCurrency ? [$configuredCurrency] : $this->enumValues(StripeCurrencyCodeEnum::class);
        }

        if ($method === PaymentTypeEnum::PAYSTACK()) {
            return $configuredCurrency ? [$configuredCurrency] : $this->enumValues(PaystackCurrencyCodeEnum::class);
        }

        if ($method === PaymentTypeEnum::FLUTTERWAVE()) {
            return $configuredCurrency ? [$configuredCurrency] : $this->enumValues(FlutterwaveCurrencyCodeEnum::class);
        }

        if ($method === PaymentTypeEnum::XENDIT()) {
            return $configuredCurrency ? [$configuredCurrency] : $this->enumValues(XenditCurrencyCodeEnum::class);
        }

        if ($method === PaymentTypeEnum::MERCADO_PAGO()) {
            return $configuredCurrency ? [$configuredCurrency] : $this->enumValues(MercadoPagoCurrencyCodeEnum::class);
        }

        return [];
    }

    /**
     * @param class-string $enum
     * @return string[]
     */
    private function enumValues(string $enum): array
    {
        return method_exists($enum, 'values') ? array_values($enum::values()) : [];
    }

    /**
     * @return string[]
     */
    private function normalizeCurrencyList(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect(Arr::flatten($values))
            ->map(fn (mixed $code) => $this->normalizeCurrency($code))
            ->filter(fn (?string $code) => $code && $this->currencyValidator->isValid($code))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeCurrency(mixed $currencyCode): ?string
    {
        $code = strtoupper(trim((string) $currencyCode));

        return $code !== '' ? $code : null;
    }

    /**
     * @param array<string, mixed> $capability
     * @return array<string, mixed>
     */
    private function withAvailability(array $capability, ?string $currencyCode, bool $available, ?string $reasonCode, string $message): array
    {
        return array_merge($capability, [
            'currency_code' => $currencyCode,
            'is_available' => $available,
            'reason_code' => $reasonCode,
            'message' => $message,
        ]);
    }

    private function label(string $method): string
    {
        return match ($method) {
            PaymentTypeEnum::RAZORPAY() => 'Razorpay',
            PaymentTypeEnum::STRIPE() => 'Stripe',
            PaymentTypeEnum::PAYSTACK() => 'Paystack',
            PaymentTypeEnum::FLUTTERWAVE() => 'Flutterwave',
            PaymentTypeEnum::XENDIT() => 'Xendit',
            PaymentTypeEnum::MERCADO_PAGO() => 'Mercado Pago',
            PaymentTypeEnum::COD() => 'Cash on Delivery',
            PaymentTypeEnum::WALLET() => 'Wallet',
            default => ucfirst($method),
        };
    }

    private function mode(string $method): string
    {
        return match ($method) {
            PaymentTypeEnum::RAZORPAY() => 'inline',
            PaymentTypeEnum::COD(), PaymentTypeEnum::WALLET() => 'direct',
            default => 'redirect',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSettings(): array
    {
        $setting = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());

        return (array) ($setting?->value ?? []);
    }
}
