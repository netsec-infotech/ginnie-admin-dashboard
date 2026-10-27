<?php

namespace App\Support\Currency;

use Illuminate\Support\Collection;

class CurrencyGroupedTotals
{
    public const UNKNOWN_CURRENCY = 'UNKNOWN';

    public function __construct(
        private readonly CurrencyCodeValidator $validator,
    ) {}

    /**
     * @param  iterable<mixed>  $items
     * @param  callable(mixed): mixed  $amountResolver
     * @param  callable(mixed): mixed  $currencyResolver
     * @return array<int, array<string, mixed>>
     */
    public function fromItems(iterable $items, callable $amountResolver, callable $currencyResolver): array
    {
        $totals = [];

        foreach ($items as $item) {
            $amount = $amountResolver($item);
            if (! is_numeric($amount)) {
                $amount = 0;
            }

            $currencyCode = $this->normalizeCurrency($currencyResolver($item));

            if (! isset($totals[$currencyCode])) {
                $totals[$currencyCode] = [
                    'currency_code' => $currencyCode,
                    'raw_total' => 0.0,
                    'record_count' => 0,
                    'is_unknown_currency' => $currencyCode === self::UNKNOWN_CURRENCY,
                ];
            }

            $totals[$currencyCode]['raw_total'] += (float) $amount;
            $totals[$currencyCode]['record_count']++;
        }

        return $this->finalize($totals);
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function fromGroupedRows(iterable $rows, string $amountKey = 'raw_total', string $currencyKey = 'currency_code', string $countKey = 'record_count'): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $amount = data_get($row, $amountKey, 0);
            $recordCount = data_get($row, $countKey, 0);
            $currencyCode = $this->normalizeCurrency(data_get($row, $currencyKey));

            if (! isset($totals[$currencyCode])) {
                $totals[$currencyCode] = [
                    'currency_code' => $currencyCode,
                    'raw_total' => 0.0,
                    'record_count' => 0,
                    'is_unknown_currency' => $currencyCode === self::UNKNOWN_CURRENCY,
                ];
            }

            $totals[$currencyCode]['raw_total'] += is_numeric($amount) ? (float) $amount : 0.0;
            $totals[$currencyCode]['record_count'] += is_numeric($recordCount) ? (int) $recordCount : 0;
        }

        return $this->finalize($totals);
    }

    /**
     * @param  array<int, array<string, mixed>>  $totals
     * @return array<string, mixed>
     */
    public function summary(array $totals): array
    {
        $totalsCollection = collect($totals);
        $formatted = $totalsCollection
            ->pluck('formatted_total')
            ->filter()
            ->values()
            ->all();
        $hasMultipleCurrencies = $totalsCollection->count() > 1;
        $displayTotals = $totalsCollection
            ->map(function (array $total) use ($hasMultipleCurrencies): ?string {
                $formattedTotal = $total['formatted_total'] ?? null;
                if (! $formattedTotal) {
                    return null;
                }

                if (! $hasMultipleCurrencies || ($total['is_unknown_currency'] ?? false)) {
                    return $formattedTotal;
                }

                return $total['currency_code'].': '.$formattedTotal;
            })
            ->filter()
            ->values()
            ->all();

        $unknown = $totalsCollection->firstWhere('is_unknown_currency', true);

        return [
            'currency_totals' => array_values($totals),
            'formatted_currency_totals' => $formatted,
            'currency_totals_display' => $displayTotals === [] ? null : implode(' / ', $displayTotals),
            'has_multiple_currencies' => $hasMultipleCurrencies,
            'unknown_currency_total' => $unknown ?: null,
        ];
    }

    public function display(array $totals, ?string $fallback = null): ?string
    {
        return $this->summary($totals)['currency_totals_display'] ?? $fallback;
    }

    private function normalizeCurrency(mixed $currencyCode): string
    {
        $normalized = $this->validator->normalize(is_string($currencyCode) ? $currencyCode : null);

        return $this->validator->isValid($normalized) ? $normalized : self::UNKNOWN_CURRENCY;
    }

    /**
     * @param  array<string, array<string, mixed>>  $totals
     * @return array<int, array<string, mixed>>
     */
    private function finalize(array $totals): array
    {
        return Collection::make($totals)
            ->sortBy(fn (array $total): string => $total['currency_code'] === self::UNKNOWN_CURRENCY ? 'ZZZZ' : $total['currency_code'])
            ->map(function (array $total): array {
                $currencyCode = $total['currency_code'];
                $rawTotal = round((float) $total['raw_total'], 2);

                return [
                    'currency_code' => $currencyCode,
                    'raw_total' => $rawTotal,
                    'formatted_total' => $currencyCode === self::UNKNOWN_CURRENCY
                        ? 'Needs review '.number_format($rawTotal, 2, '.', ',')
                        : CurrencySnapshotPayload::formatAmount($rawTotal, $currencyCode),
                    'record_count' => (int) $total['record_count'],
                    'is_unknown_currency' => (bool) $total['is_unknown_currency'],
                ];
            })
            ->values()
            ->all();
    }
}
