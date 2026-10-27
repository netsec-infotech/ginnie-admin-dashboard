<?php

namespace App\Support\Currency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurrencySnapshotPayload
{
    /**
     * Read-only currency context for order-like resources. Legacy scalar fields
     * remain unchanged; this block lets newer clients opt into clearer meaning.
     *
     * @return array<string, mixed>
     */
    public static function order(mixed $order): array
    {
        return [
            'order_currency_code' => $order?->currency_code,
            'base_currency_code' => $order?->base_currency_code,
            'display_currency_code' => $order?->display_currency_code,
            'display_exchange_rate' => $order?->display_exchange_rate,
            'formatted' => [
                'subtotal' => self::formatAmount($order?->subtotal, $order?->currency_code),
                'total_payable' => self::formatAmount($order?->total_payable, $order?->currency_code),
                'final_total' => self::formatAmount($order?->final_total, $order?->currency_code),
                'wallet_balance' => self::formatAmount($order?->wallet_balance, $order?->currency_code),
                'promo_discount' => self::formatAmount($order?->promo_discount, $order?->currency_code),
                'gift_card_discount' => self::formatAmount($order?->gift_card_discount, $order?->currency_code),
                'delivery_charge' => self::formatAmount($order?->delivery_charge, $order?->currency_code),
                'handling_charges' => self::formatAmount($order?->handling_charges, $order?->currency_code),
                'per_store_drop_off_fee' => self::formatAmount($order?->per_store_drop_off_fee, $order?->currency_code),
                'rider_tip_amount' => self::formatAmount($order?->rider_tip_amount, $order?->currency_code),
                'loyalty_points_discount_amount' => self::formatAmount($order?->loyalty_points_discount_amount, $order?->currency_code),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payment(mixed $transaction): array
    {
        $gatewayCurrencyCode = $transaction?->gateway_currency_code ?: $transaction?->currency;

        return [
            'legacy_currency' => $transaction?->currency,
            'order_currency_code' => $transaction?->order_currency_code,
            'gateway_currency_code' => $transaction?->gateway_currency_code,
            'gateway_amount' => $transaction?->gateway_amount,
            'gateway_exchange_rate' => $transaction?->gateway_exchange_rate,
            'formatted' => [
                'amount' => self::formatAmount($transaction?->amount, $transaction?->currency),
                'gateway_amount' => self::formatAmount($transaction?->gateway_amount, $gatewayCurrencyCode),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function wallet(mixed $walletOrTransaction): array
    {
        return [
            'currency_code' => $walletOrTransaction?->currency_code,
            'formatted' => [
                'balance' => self::formatAmount($walletOrTransaction?->balance, $walletOrTransaction?->currency_code),
                'blocked_balance' => self::formatAmount($walletOrTransaction?->blocked_balance, $walletOrTransaction?->currency_code),
                'amount' => self::formatAmount($walletOrTransaction?->amount, $walletOrTransaction?->currency_code),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function posRefund(mixed $refund): array
    {
        return [
            'pos_refund_currency_code' => $refund?->currency_code,
            'formatted' => [
                'total_amount' => self::formatAmount($refund?->total_amount, $refund?->currency_code),
            ],
        ];
    }

    public static function formatAmount(mixed $amount, ?string $currencyCode): ?string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return null;
        }

        $validator = app(CurrencyCodeValidator::class);
        $normalizedCode = $validator->normalize($currencyCode);

        if (! $validator->isValid($normalizedCode)) {
            return null;
        }

        $metadata = $validator->metadata($normalizedCode);
        $decimalPlaces = (int) $metadata['decimal_places'];
        $symbol = self::symbolFor($normalizedCode, $metadata['symbol'] ?? null);
        $prefix = $symbol ?: $normalizedCode;

        return $prefix.' '.number_format((float) $amount, $decimalPlaces, '.', ',');
    }

    private static function symbolFor(string $currencyCode, ?string $fallbackSymbol): ?string
    {
        $symbol = null;

        try {
            if (Schema::hasTable('currency_metadata') && Schema::hasColumn('currency_metadata', 'symbol')) {
                $symbol = DB::table('currency_metadata')
                    ->where('code', $currencyCode)
                    ->value('symbol');
            }

            if (($symbol === null || $symbol === '') && Schema::hasTable('countries')) {
                $symbol = DB::table('countries')
                    ->where('currency', $currencyCode)
                    ->whereNotNull('currency_symbol')
                    ->value('currency_symbol');
            }
        } catch (\Throwable) {
            $symbol = null;
        }

        $symbol = trim((string) ($symbol ?: $fallbackSymbol));

        return $symbol !== '' && $symbol !== $currencyCode ? $symbol : null;
    }
}
