<?php

namespace App\Services;

use App\Support\Currency\CurrencyWriteGuard;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentCurrencyEnforcementService
{
    public function __construct(
        private readonly PaymentCurrencyAvailabilityService $availabilityService,
        private readonly CurrencyWriteGuard $currencyWriteGuard
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ensureAvailable(string $paymentMethod, ?string $currencyCode, ?string $walletCurrencyCode = null): array
    {
        $currency = $this->currencyWriteGuard->requireValid($currencyCode, 'Payment');
        $availability = $this->availabilityService->availabilityForMethod($paymentMethod, $currency, $walletCurrencyCode);

        if (! $availability['is_available']) {
            throw new RuntimeException($availability['message']);
        }

        return $availability;
    }

    public function ensureAmountMatches(float|int|string|null $expectedAmount, float|int|string|null $receivedAmount, string $context): void
    {
        if ($receivedAmount === null || $receivedAmount === '') {
            Log::warning("{$context} gateway amount could not be verified.", [
                'expected_amount' => $expectedAmount,
            ]);

            return;
        }

        if (abs((float) $expectedAmount - (float) $receivedAmount) > 0.01) {
            throw new RuntimeException("{$context} amount mismatch.");
        }
    }

    public function ensureCurrencyMatches(?string $expectedCurrency, ?string $receivedCurrency, string $context): void
    {
        $expected = $this->currencyWriteGuard->requireValid($expectedCurrency, $context);

        if ($receivedCurrency === null || trim((string) $receivedCurrency) === '') {
            Log::warning("{$context} gateway currency could not be verified.", [
                'expected_currency' => $expected,
            ]);

            return;
        }

        $received = $this->currencyWriteGuard->requireValid($receivedCurrency, "{$context} gateway");

        if ($expected !== $received) {
            throw new RuntimeException("{$context} currency mismatch.");
        }
    }
}
