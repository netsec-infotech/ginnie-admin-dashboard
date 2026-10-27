<?php

namespace App\Support\Currency;

use ResourceBundle;
use Symfony\Component\Intl\Currencies;

class CurrencyCodeValidator
{
    /**
     * Conservative fallback used only when PHP intl currency data is unavailable.
     *
     * @var array<string, true>
     */
    private const FALLBACK_ISO_CODES = [
        'AED' => true,
        'AUD' => true,
        'BRL' => true,
        'CAD' => true,
        'CHF' => true,
        'CLP' => true,
        'CNY' => true,
        'COP' => true,
        'EUR' => true,
        'GBP' => true,
        'GHS' => true,
        'IDR' => true,
        'INR' => true,
        'JPY' => true,
        'KES' => true,
        'MXN' => true,
        'MYR' => true,
        'NGN' => true,
        'PEN' => true,
        'PHP' => true,
        'SAR' => true,
        'SGD' => true,
        'THB' => true,
        'USD' => true,
        'UYU' => true,
        'VND' => true,
        'ZAR' => true,
    ];

    /** @var array<string, true>|null */
    private ?array $isoCodes = null;

    public function normalize(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized === '' ? null : $normalized;
    }

    public function classify(?string $code): string
    {
        $normalized = $this->normalize($code);

        if ($normalized === null || ! preg_match('/^[A-Z]{3}$/', $normalized)) {
            return 'malformed_code';
        }

        if (! $this->isIsoCurrency($normalized)) {
            return 'unknown_iso_code';
        }

        return 'valid';
    }

    public function isValid(?string $code): bool
    {
        return $this->classify($code) === 'valid';
    }

    /**
     * @return array{name:?string,symbol:?string,decimal_places:int,minor_unit_factor:int}
     */
    public function metadata(string $code): array
    {
        $code = $this->normalize($code) ?? $code;
        $decimalPlaces = $this->fractionDigits($code);

        return [
            'name' => class_exists(Currencies::class) && Currencies::exists($code) ? Currencies::getName($code) : null,
            'symbol' => class_exists(Currencies::class) && Currencies::exists($code) ? Currencies::getSymbol($code) : null,
            'decimal_places' => $decimalPlaces,
            'minor_unit_factor' => 10 ** $decimalPlaces,
        ];
    }

    /**
     * Uses ICU's ISO currency metadata when ext-intl is available, with a
     * compact fallback for environments where ResourceBundle is unavailable.
     */
    private function isIsoCurrency(string $code): bool
    {
        if ($this->isoCodes === null) {
            $this->isoCodes = self::FALLBACK_ISO_CODES;

            if (class_exists(Currencies::class)) {
                $this->isoCodes = array_fill_keys(Currencies::getCurrencyCodes(), true);

                return isset($this->isoCodes[$code]);
            }

            if (class_exists(ResourceBundle::class)) {
                $bundle = ResourceBundle::create('en', 'ICUDATA-curr');
                $currencies = $bundle ? ($bundle['Currencies'] ?? null) : null;

                if ($currencies instanceof ResourceBundle) {
                    $this->isoCodes = array_fill_keys(array_keys(iterator_to_array($currencies)), true);
                }
            }
        }

        return isset($this->isoCodes[$code]);
    }

    private function fractionDigits(string $code): int
    {
        if (class_exists(Currencies::class) && Currencies::exists($code)) {
            return Currencies::getFractionDigits($code);
        }

        return match ($code) {
            'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' => 3,
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' => 0,
            default => 2,
        };
    }
}
