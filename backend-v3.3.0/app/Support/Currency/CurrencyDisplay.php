<?php

namespace App\Support\Currency;

class CurrencyDisplay
{
    public static function format(mixed $amount, ?string $currencyCode, ?string $fallbackSymbol = null): string
    {
        $formatted = CurrencySnapshotPayload::formatAmount($amount, $currencyCode);

        if ($formatted !== null) {
            return $formatted;
        }

        $prefix = trim((string) ($currencyCode ?: $fallbackSymbol));

        return trim($prefix.' '.number_format((float) $amount, 2, '.', ','));
    }

    public static function zero(?string $currencyCode, ?string $fallbackSymbol = null): string
    {
        return self::format(0, $currencyCode, $fallbackSymbol);
    }

    public static function label(?string $currencyCode, ?string $fallbackSymbol = null): string
    {
        $label = preg_replace('/\s*-?[\d,]+(?:\.\d+)?$/', '', self::zero($currencyCode, $fallbackSymbol));

        return trim((string) $label);
    }
}
