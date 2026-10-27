<?php

namespace App\Services;

use App\Support\Currency\CurrencyCodeValidator;
use App\Support\Currency\SystemCurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurrencyBackfillService
{
    private const REPORT_VERSION = '1.0';

    private const SAMPLE_LIMIT = 10;

    private const DEFAULT_SECTIONS = [
        'orders',
        'payments',
        'pos-refunds',
        'currency-metadata',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $findings = [];

    /** @var array<string, mixed> */
    private array $totals = [];

    /** @var array<string, bool> */
    private array $completedSections = [];

    private ?int $runId = null;

    public function __construct(private readonly CurrencyCodeValidator $currencyValidator) {}

    /**
     * @param  array{execute:bool,batch:int,only:array<int,string>,resume:?int}  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $this->findings = [];
        $this->totals = [];
        $this->completedSections = [];
        $this->runId = null;

        $execute = $options['execute'];
        $sections = $options['only'] ?: self::DEFAULT_SECTIONS;

        if ($execute) {
            $this->startRun($options);
        }

        try {
            foreach ($sections as $section) {
                if ($this->isSectionCompleted($section)) {
                    $this->addFinding($section, 'info', 'section_already_completed', 'Section was already completed in the resumed run.', 0);

                    continue;
                }

                match ($section) {
                    'orders' => $this->backfillOrders($options),
                    'payments' => $this->backfillPayments($options),
                    'pos-refunds' => $this->backfillPosRefunds($options),
                    'currency-metadata' => $this->backfillCurrencyMetadata($options),
                    default => $this->addFinding($section, 'manual_review', 'unknown_backfill_section', 'Requested backfill section is not supported.', 1),
                };

                if ($execute) {
                    $this->markSectionCompleted($section);
                }
            }

            $report = $this->buildReport($execute ? 'execute' : 'dry-run');
            $this->finishRun('completed', $report);

            return $report;
        } catch (\Throwable $throwable) {
            $this->addFinding('command', 'manual_review', 'backfill_failed', $throwable->getMessage(), 1);
            $report = $this->buildReport($execute ? 'execute' : 'dry-run');
            $this->finishRun('failed', $report);

            throw $throwable;
        }
    }

    /**
     * @param  array{execute:bool,batch:int,only:array<int,string>,resume:?int}  $options
     */
    private function backfillOrders(array $options): void
    {
        if (! $this->hasColumns('orders', ['id', 'currency_code', 'base_currency_code'])) {
            $this->addFinding('orders', 'schema_limitation', 'orders_snapshot_columns_missing', 'orders.currency_code or orders.base_currency_code is unavailable.', 0);

            return;
        }

        $stats = $this->blankStats(DB::table('orders')->count());

        DB::table('orders')
            ->select(['id', 'currency_code', 'base_currency_code'])
            ->orderBy('id')
            ->chunkById($options['batch'], function ($orders) use (&$stats, $options): void {
                foreach ($orders as $order) {
                    $currency = $this->currencyValidator->normalize($order->currency_code ?? null);

                    if (! $this->currencyValidator->isValid($currency)) {
                        $this->trackSkipped($stats, (int) $order->id, 'invalid_source_currency');

                        continue;
                    }

                    $existing = $this->currencyValidator->normalize($order->base_currency_code ?? null);
                    if ($existing !== null && $existing !== $currency) {
                        $this->trackConflict($stats, (int) $order->id);

                        continue;
                    }

                    if ($existing === $currency) {
                        $stats['unchanged']++;

                        continue;
                    }

                    $stats['eligible']++;
                    if ($options['execute']) {
                        DB::table('orders')->where('id', $order->id)->whereNull('base_currency_code')->update([
                            'base_currency_code' => $currency,
                        ]);
                        $stats['updated']++;
                    }
                }
            });

        $this->recordStats('orders', $stats);
    }

    /**
     * @param  array{execute:bool,batch:int,only:array<int,string>,resume:?int}  $options
     */
    private function backfillPayments(array $options): void
    {
        if (! $this->hasColumns('order_payment_transactions', ['id', 'order_id', 'currency', 'amount', 'order_currency_code', 'gateway_currency_code', 'gateway_amount']) || ! $this->hasColumns('orders', ['id', 'currency_code'])) {
            $this->addFinding('payments', 'schema_limitation', 'payment_snapshot_columns_missing', 'Payment transaction snapshot columns or linked order currency are unavailable.', 0);

            return;
        }

        $stats = $this->blankStats(DB::table('order_payment_transactions')->count());

        DB::table('order_payment_transactions')
            ->leftJoin('orders', 'order_payment_transactions.order_id', '=', 'orders.id')
            ->select([
                'order_payment_transactions.id',
                'order_payment_transactions.currency',
                'order_payment_transactions.amount',
                'order_payment_transactions.order_currency_code',
                'order_payment_transactions.gateway_currency_code',
                'order_payment_transactions.gateway_amount',
                'orders.currency_code as linked_order_currency_code',
            ])
            ->orderBy('order_payment_transactions.id')
            ->chunkById($options['batch'], function ($transactions) use (&$stats, $options): void {
                foreach ($transactions as $transaction) {
                    $updates = [];
                    $orderCurrency = $this->currencyValidator->normalize($transaction->linked_order_currency_code ?? null);
                    $gatewayCurrency = $this->currencyValidator->normalize($transaction->currency ?? null);

                    if (! $this->currencyValidator->isValid($orderCurrency)) {
                        $this->trackSkipped($stats, (int) $transaction->id, 'invalid_or_missing_order_currency');
                    } else {
                        $this->prepareCurrencyUpdate($stats, $updates, (int) $transaction->id, 'order_currency_code', $transaction->order_currency_code, $orderCurrency);
                    }

                    if (! $this->currencyValidator->isValid($gatewayCurrency)) {
                        $this->trackSkipped($stats, (int) $transaction->id, 'invalid_gateway_currency');
                    } else {
                        $this->prepareCurrencyUpdate($stats, $updates, (int) $transaction->id, 'gateway_currency_code', $transaction->gateway_currency_code, $gatewayCurrency);
                    }

                    if ($orderCurrency !== null && $gatewayCurrency !== null && $this->currencyValidator->isValid($orderCurrency) && $this->currencyValidator->isValid($gatewayCurrency) && $orderCurrency !== $gatewayCurrency) {
                        $this->trackConflict($stats, (int) $transaction->id);
                    }

                    $this->prepareAmountUpdate($stats, $updates, (int) $transaction->id, 'gateway_amount', $transaction->gateway_amount, $transaction->amount);

                    if ($updates === []) {
                        continue;
                    }

                    $stats['eligible']++;
                    if ($options['execute']) {
                        DB::table('order_payment_transactions')->where('id', $transaction->id)->update($updates);
                        $stats['updated']++;
                    }
                }
            }, 'order_payment_transactions.id', 'id');

        $this->recordStats('payments', $stats);
    }

    /**
     * @param  array{execute:bool,batch:int,only:array<int,string>,resume:?int}  $options
     */
    private function backfillPosRefunds(array $options): void
    {
        if (! $this->hasColumns('pos_refunds', ['id', 'order_id', 'currency_code']) || ! $this->hasColumns('orders', ['id', 'currency_code'])) {
            $this->addFinding('pos-refunds', 'schema_limitation', 'pos_refund_snapshot_columns_missing', 'POS refund currency column or linked order currency is unavailable.', 0);

            return;
        }

        $stats = $this->blankStats(DB::table('pos_refunds')->count());

        DB::table('pos_refunds')
            ->leftJoin('orders', 'pos_refunds.order_id', '=', 'orders.id')
            ->select([
                'pos_refunds.id',
                'pos_refunds.currency_code',
                'orders.currency_code as linked_order_currency_code',
            ])
            ->orderBy('pos_refunds.id')
            ->chunkById($options['batch'], function ($refunds) use (&$stats, $options): void {
                foreach ($refunds as $refund) {
                    $currency = $this->currencyValidator->normalize($refund->linked_order_currency_code ?? null);

                    if (! $this->currencyValidator->isValid($currency)) {
                        $this->trackSkipped($stats, (int) $refund->id, 'invalid_or_missing_order_currency');

                        continue;
                    }

                    $existing = $this->currencyValidator->normalize($refund->currency_code ?? null);
                    if ($existing !== null && $existing !== $currency) {
                        $this->trackConflict($stats, (int) $refund->id);

                        continue;
                    }

                    if ($existing === $currency) {
                        $stats['unchanged']++;

                        continue;
                    }

                    $stats['eligible']++;
                    if ($options['execute']) {
                        DB::table('pos_refunds')->where('id', $refund->id)->whereNull('currency_code')->update([
                            'currency_code' => $currency,
                        ]);
                        $stats['updated']++;
                    }
                }
            }, 'pos_refunds.id', 'id');

        $this->recordStats('pos-refunds', $stats);
    }

    /**
     * @param  array{execute:bool,batch:int,only:array<int,string>,resume:?int}  $options
     */
    private function backfillCurrencyMetadata(array $options): void
    {
        if (! Schema::hasTable('currency_metadata')) {
            $this->addFinding('currency-metadata', 'schema_limitation', 'currency_metadata_table_missing', 'currency_metadata table is unavailable.', 0);

            return;
        }

        $systemCurrency = SystemCurrency::code();
        $currencies = $this->discoverCurrencyCodes();
        $stats = $this->blankStats(count($currencies));

        foreach ($currencies as $currency) {
            if (! $this->currencyValidator->isValid($currency)) {
                continue;
            }

            $metadata = $this->currencyValidator->metadata($currency);
            $countryMetadata = $this->countryCurrencyMetadata($currency);
            $existing = DB::table('currency_metadata')->where('code', $currency)->first();

            if ($existing) {
                $updates = [];
                if ($existing->name === null && ($metadata['name'] ?? $countryMetadata['name'] ?? null)) {
                    $updates['name'] = $metadata['name'] ?? $countryMetadata['name'];
                }
                if ($existing->symbol === null && ($metadata['symbol'] ?? $countryMetadata['symbol'] ?? null)) {
                    $updates['symbol'] = $metadata['symbol'] ?? $countryMetadata['symbol'];
                }
                if ($currency === $systemCurrency && ! (bool) $existing->is_enabled) {
                    $updates['is_enabled'] = true;
                }

                if ($updates === []) {
                    $stats['unchanged']++;

                    continue;
                }

                $stats['eligible']++;
                if ($options['execute']) {
                    $updates['updated_at'] = now();
                    DB::table('currency_metadata')->where('code', $currency)->update($updates);
                    $stats['updated']++;
                }

                continue;
            }

            $stats['eligible']++;
            if ($options['execute']) {
                DB::table('currency_metadata')->insert([
                    'code' => $currency,
                    'name' => $metadata['name'] ?? $countryMetadata['name'] ?? $currency,
                    'symbol' => $metadata['symbol'] ?? $countryMetadata['symbol'] ?? null,
                    'decimal_places' => $metadata['decimal_places'] ?? 2,
                    'minor_unit_factor' => $metadata['minor_unit_factor'] ?? 100,
                    'is_enabled' => $currency === $systemCurrency,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stats['updated']++;
            }
        }

        $this->recordStats('currency-metadata', $stats);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $updates
     */
    private function prepareCurrencyUpdate(array &$stats, array &$updates, int $id, string $column, mixed $existingValue, string $derivedValue): void
    {
        $existing = $this->currencyValidator->normalize(is_string($existingValue) ? $existingValue : null);

        if ($existing !== null && $existing !== $derivedValue) {
            $this->trackConflict($stats, $id);

            return;
        }

        if ($existing === $derivedValue) {
            return;
        }

        $updates[$column] = $derivedValue;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $updates
     */
    private function prepareAmountUpdate(array &$stats, array &$updates, int $id, string $column, mixed $existingValue, mixed $derivedValue): void
    {
        if ($derivedValue === null) {
            $this->trackSkipped($stats, $id, 'missing_gateway_amount');

            return;
        }

        $existing = $existingValue === null ? null : number_format((float) $existingValue, 2, '.', '');
        $derived = number_format((float) $derivedValue, 2, '.', '');

        if ($existing !== null && $existing !== $derived) {
            $this->trackConflict($stats, $id);

            return;
        }

        if ($existing === $derived) {
            return;
        }

        $updates[$column] = $derived;
    }

    /**
     * @return array<int, string>
     */
    private function discoverCurrencyCodes(): array
    {
        $codes = [SystemCurrency::code()];

        foreach ([
            'countries' => ['currency'],
            'stores' => ['currency_code'],
            'orders' => ['currency_code', 'base_currency_code', 'display_currency_code'],
            'order_payment_transactions' => ['currency', 'order_currency_code', 'gateway_currency_code'],
            'wallets' => ['currency_code'],
            'wallet_transactions' => ['currency_code'],
            'seller_statements' => ['currency_code'],
            'pos_payment_sessions' => ['currency_code'],
            'pos_refunds' => ['currency_code'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $codes = array_merge($codes, DB::table($table)->whereNotNull($column)->distinct()->pluck($column)->all());
            }
        }

        return collect($codes)
            ->map(fn ($code) => $this->currencyValidator->normalize(is_string($code) ? $code : null))
            ->filter(fn (?string $code) => $code !== null && $this->currencyValidator->isValid($code))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{name:?string,symbol:?string}
     */
    private function countryCurrencyMetadata(string $currency): array
    {
        if (! $this->hasColumns('countries', ['currency', 'currency_name', 'currency_symbol'])) {
            return ['name' => null, 'symbol' => null];
        }

        $row = DB::table('countries')->whereRaw('UPPER(currency) = ?', [$currency])->first(['currency_name', 'currency_symbol']);

        return [
            'name' => $row?->currency_name,
            'symbol' => $row?->currency_symbol,
        ];
    }

    /**
     * @return array{total:int,eligible:int,updated:int,unchanged:int,skipped:int,conflicts:int,sample_ids:array<int, int>,skipped_reasons:array<string, int>}
     */
    private function blankStats(int $total): array
    {
        return [
            'total' => $total,
            'eligible' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'sample_ids' => [],
            'skipped_reasons' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function trackSkipped(array &$stats, int $id, string $reason): void
    {
        $stats['skipped']++;
        $stats['skipped_reasons'][$reason] = ($stats['skipped_reasons'][$reason] ?? 0) + 1;
        $this->trackSampleId($stats, $id);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function trackConflict(array &$stats, int $id): void
    {
        $stats['conflicts']++;
        $this->trackSampleId($stats, $id);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function trackSampleId(array &$stats, int $id): void
    {
        if (count($stats['sample_ids']) < self::SAMPLE_LIMIT) {
            $stats['sample_ids'][] = $id;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function recordStats(string $section, array $stats): void
    {
        $this->totals[$section] = $stats;

        $this->addFinding($section, 'info', 'backfill_section_summary', 'Backfill section inspected records and calculated safe snapshot updates.', $stats['total'], $stats['sample_ids']);

        if ($stats['skipped'] > 0) {
            $this->addFinding($section, 'manual_review', 'backfill_rows_skipped', 'Some rows cannot be backfilled safely and require review.', $stats['skipped'], $stats['sample_ids']);
        }

        if ($stats['conflicts'] > 0) {
            $this->addFinding($section, 'conflict', 'backfill_currency_conflict', 'Related currency facts or existing snapshot data conflict; no value was overwritten.', $stats['conflicts'], $stats['sample_ids']);
        }
    }

    /**
     * @param  array<int, mixed>  $sampleIds
     */
    private function addFinding(string $category, string $severity, string $code, string $message, int $recordCount, array $sampleIds = []): void
    {
        $this->findings[] = [
            'category' => $category,
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'record_count' => $recordCount,
            'sample_ids' => array_values(array_slice(array_map('intval', $sampleIds), 0, self::SAMPLE_LIMIT)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(string $mode): array
    {
        $severityCounts = [];
        foreach ($this->findings as $finding) {
            $severityCounts[$finding['severity']] = ($severityCounts[$finding['severity']] ?? 0) + 1;
        }

        return [
            'report_version' => self::REPORT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'run_id' => $this->runId,
            'summary' => [
                'final_status' => $this->finalStatus($severityCounts),
                'totals' => $this->totals,
                'finding_counts_by_severity' => $severityCounts,
            ],
            'findings' => $this->findings,
        ];
    }

    /**
     * @param  array<string, int>  $severityCounts
     */
    private function finalStatus(array $severityCounts): string
    {
        if (($severityCounts['conflict'] ?? 0) > 0 || ($severityCounts['manual_review'] ?? 0) > 0) {
            return 'REVIEW REQUIRED';
        }

        if (($severityCounts['warning'] ?? 0) > 0 || ($severityCounts['schema_limitation'] ?? 0) > 0) {
            return 'WARNINGS';
        }

        return 'CLEAN';
    }

    /**
     * @param  array{execute:bool,batch:int,only:array<int,string>,resume:?int}  $options
     */
    private function startRun(array $options): void
    {
        if (! Schema::hasTable('currency_backfill_runs')) {
            return;
        }

        if ($options['resume']) {
            $run = DB::table('currency_backfill_runs')->where('id', $options['resume'])->first();
            if ($run) {
                $this->runId = (int) $run->id;
                $checkpoints = json_decode((string) $run->checkpoints, true);
                $this->completedSections = is_array($checkpoints) ? array_fill_keys($checkpoints['completed_sections'] ?? [], true) : [];

                return;
            }
        }

        $this->runId = DB::table('currency_backfill_runs')->insertGetId([
            'command' => 'hyperlocal:currency-backfill',
            'mode' => 'execute',
            'status' => 'running',
            'options' => json_encode($options),
            'checkpoints' => json_encode(['completed_sections' => []]),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function markSectionCompleted(string $section): void
    {
        $this->completedSections[$section] = true;

        if ($this->runId && Schema::hasTable('currency_backfill_runs')) {
            DB::table('currency_backfill_runs')->where('id', $this->runId)->update([
                'checkpoints' => json_encode(['completed_sections' => array_keys($this->completedSections)]),
                'updated_at' => now(),
            ]);
        }
    }

    private function isSectionCompleted(string $section): bool
    {
        return isset($this->completedSections[$section]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function finishRun(string $status, array $report): void
    {
        if (! $this->runId || ! Schema::hasTable('currency_backfill_runs')) {
            return;
        }

        DB::table('currency_backfill_runs')->where('id', $this->runId)->update([
            'status' => $status,
            'summary' => json_encode($report['summary'] ?? []),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
