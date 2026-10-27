<?php

namespace App\Support\Currency;

use App\Models\Order;
use App\Models\Wallet;
use InvalidArgumentException;

class CurrencyWriteGuard
{
    public function __construct(private readonly CurrencyCodeValidator $currencyValidator) {}

    public function requireValid(?string $currencyCode, string $context = 'Financial'): string
    {
        $normalized = $this->currencyValidator->normalize($currencyCode);

        if (! $this->currencyValidator->isValid($normalized)) {
            throw new InvalidArgumentException("{$context} currency code must be a valid ISO 4217 code.");
        }

        return $normalized;
    }

    public function systemCurrency(): string
    {
        return $this->requireValid(SystemCurrency::code(), 'System');
    }

    public function orderCurrency(Order $order): string
    {
        return $this->requireValid($order->currency_code, 'Order');
    }

    public function walletCurrency(Wallet $wallet): string
    {
        return $this->requireValid($wallet->currency_code, 'Wallet');
    }

    public function nullableValid(?string $currencyCode, string $context = 'Financial'): ?string
    {
        if ($currencyCode === null || trim($currencyCode) === '') {
            return null;
        }

        return $this->requireValid($currencyCode, $context);
    }
}
