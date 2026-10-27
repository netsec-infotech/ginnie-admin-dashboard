<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Services\PaymentCurrencyAvailabilityService;
use App\Support\Currency\CurrencyWriteGuard;
use App\Support\Currency\CurrencyCodeValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HyperlocalCurrencyAuditCommand extends Command
{
    private const REPORT_VERSION = '1.0';

    private const SAMPLE_LIMIT = 10;

    protected $signature = 'hyperlocal:currency-audit
        {--json : Print the audit report as JSON}
        {--output= : Write the JSON report to a safe file under storage/app/currency-audits}';

    protected $description = 'Read-only audit of currency configuration and financial currency consistency.';

    /** @var array<int, array<string, mixed>> */
    private array $findings = [];

    /** @var array<string, mixed> */
    private array $totals = [];

    /** @var array<string, true> */
    private array $countryCurrencyCodes = [];

    public function __construct(private readonly CurrencyCodeValidator $currencyValidator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->findings = [];
        $this->totals = [];
        $this->countryCurrencyCodes = [];

        $base = $this->baseCurrencyCandidate();
        $this->countryCurrencyCodes = $this->loadCountryCurrencyCodes();

        $this->auditSystemCurrency($base);
        $this->auditStores($base);
        $this->auditOrders($base);
        $this->auditWallets($base);
        $this->auditWalletTransactions($base);
        $this->auditPaymentTransactions();
        $this->auditSellerStatements();
        $this->auditPosSessions();
        $this->auditPosRefunds();
        $this->auditDeliveryZones($base);
        $this->auditGateways($base);
        $this->auditPaymentSnapshotIntegrity();
        $this->auditPhase2SnapshotCoverage();
        $this->auditPhase4WriteGuardCoverage();

        $report = $this->buildReport($base);

        if ($this->option('json')) {
            $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($json)) {
                $this->error('Unable to encode currency audit report.');

                return self::FAILURE;
            }

            $output = $this->option('output');
            if (is_string($output) && $output !== '') {
                $path = $this->safeOutputPath($output);

                if ($path === null) {
                    $this->error('Unsafe output path. Use storage/app/currency-audits/*.json.');

                    return self::FAILURE;
                }

                if (File::exists($path)) {
                    $this->error("Refusing to overwrite existing report: {$path}");

                    return self::FAILURE;
                }

                File::ensureDirectoryExists(dirname($path));
                File::put($path, $json . PHP_EOL);
                $this->info("Currency audit JSON report written to: {$path}");

                return self::SUCCESS;
            }

            $this->line($json);

            return self::SUCCESS;
        }

        $this->renderHumanReport($report);

        return self::SUCCESS;
    }

    /**
     * @return array{code:?string,symbol:?string,country_code:?string,source:string}
     */
    private function baseCurrencyCandidate(): array
    {
        $value = $this->settingValue('system');

        return [
            'code' => $this->currencyValidator->normalize($value['currency'] ?? null),
            'symbol' => isset($value['currencySymbol']) ? (string) $value['currencySymbol'] : null,
            'country_code' => isset($value['countryCode']) ? (string) $value['countryCode'] : null,
            'source' => 'settings.system.currency',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingValue(string $variable): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $raw = DB::table('settings')->where('variable', $variable)->value('value');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, true>
     */
    private function loadCountryCurrencyCodes(): array
    {
        if (! $this->hasColumns('countries', ['currency'])) {
            return [];
        }

        return DB::table('countries')
            ->whereNotNull('currency')
            ->pluck('currency')
            ->map(fn ($code) => $this->currencyValidator->normalize((string) $code))
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($code) => [$code => true])
            ->all();
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditSystemCurrency(array $base): void
    {
        $this->totals['system_settings'] = Schema::hasTable('settings')
            ? DB::table('settings')->where('variable', 'system')->count()
            : 0;

        $classification = $this->currencyValidator->classify($base['code']);

        if ($classification !== 'valid') {
            $this->addFinding('system_settings', 'manual_review', $classification, 'Configured system currency is missing or not a valid ISO 4217 code.', $this->totals['system_settings']);

            return;
        }

        if (! isset($this->countryCurrencyCodes[$base['code']])) {
            $this->addFinding('system_settings', 'manual_review', 'missing_country_metadata', 'Configured system currency is valid ISO 4217 but is missing from countries.currency metadata.', 1);
        }
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditStores(array $base): void
    {
        if (! $this->hasColumns('stores', ['id'])) {
            $this->totals['stores'] = 0;

            return;
        }

        $this->totals['stores'] = DB::table('stores')->count();

        $this->auditCurrencyColumn('stores', 'currency_code', 'stores');

        if ($base['code'] && $this->hasColumns('stores', ['id', 'currency_code'])) {
            $ids = DB::table('stores')
                ->whereNotNull('currency_code')
                ->whereRaw('UPPER(currency_code) <> ?', [$base['code']])
                ->limit(self::SAMPLE_LIMIT)
                ->pluck('id')
                ->all();
            $count = DB::table('stores')
                ->whereNotNull('currency_code')
                ->whereRaw('UPPER(currency_code) <> ?', [$base['code']])
                ->count();
            $this->addFindingIf($count > 0, 'stores', 'manual_review', 'store_system_currency_mismatch', 'Store currency differs from configured system currency.', $count, $ids);
        }

        if ($this->hasColumns('stores', ['id', 'country_code'])) {
            $count = DB::table('stores')->where(fn ($q) => $q->whereNull('country_code')->orWhere('country_code', ''))->count();
            $ids = DB::table('stores')->where(fn ($q) => $q->whereNull('country_code')->orWhere('country_code', ''))->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
            $this->addFindingIf($count > 0, 'stores', 'manual_review', 'missing_store_country_metadata', 'Store country_code is missing, so country/currency mapping cannot be verified.', $count, $ids);
        }
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditOrders(array $base): void
    {
        if (! $this->hasColumns('orders', ['id'])) {
            $this->totals['orders'] = 0;

            return;
        }

        $this->totals['orders'] = DB::table('orders')->count();
        $this->auditCurrencyColumn('orders', 'currency_code', 'orders');

        $sourceCounts = [];
        $currencyBySource = [];
        $unknownIds = [];

        $columns = $this->selectExistingColumns('orders', [
            'id',
            'currency_code',
            'created_by',
            'payment_method',
            'walkin_customer_name',
            'walkin_customer_mobile',
            'pos_savings',
            'pos_split_cash',
        ]);

        DB::table('orders')
            ->select($columns)
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$sourceCounts, &$currencyBySource, &$unknownIds) {
                foreach ($orders as $order) {
                    $source = $this->classifyOrderSource($order);
                    $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
                    $currency = $this->currencyValidator->normalize($order->currency_code ?? null) ?? 'MISSING';
                    $currencyBySource[$source][$currency] = ($currencyBySource[$source][$currency] ?? 0) + 1;

                    if ($source === 'unknown' && count($unknownIds) < self::SAMPLE_LIMIT) {
                        $unknownIds[] = $order->id;
                    }
                }
            });

        $this->totals['orders_by_source'] = $sourceCounts;
        $this->totals['order_currencies_by_source'] = $currencyBySource;

        $unknownCount = $sourceCounts['unknown'] ?? 0;
        $this->addFindingIf($unknownCount > 0, 'orders', 'manual_review', 'unknown_order_source', 'Some orders cannot be reliably classified as customer checkout, POS, admin, or other.', $unknownCount, $unknownIds);

        if ($base['code'] && $this->hasColumns('orders', ['id', 'currency_code'])) {
            $count = DB::table('orders')->whereNotNull('currency_code')->whereRaw('UPPER(currency_code) <> ?', [$base['code']])->count();
            $ids = DB::table('orders')->whereNotNull('currency_code')->whereRaw('UPPER(currency_code) <> ?', [$base['code']])->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
            $this->addFindingIf($count > 0, 'orders', 'manual_review', 'order_system_currency_difference', 'Order currency differs from configured system currency. This may be valid historical data and requires review by order source.', $count, $ids);
        }
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditWallets(array $base): void
    {
        if (! $this->hasColumns('wallets', ['id', 'currency_code'])) {
            $this->totals['wallets'] = 0;

            return;
        }

        $this->totals['wallets'] = DB::table('wallets')->count();
        $this->auditCurrencyColumn('wallets', 'currency_code', 'wallets');

        if ($base['code']) {
            $count = DB::table('wallets')->whereNotNull('currency_code')->whereRaw('UPPER(currency_code) <> ?', [$base['code']])->count();
            $ids = DB::table('wallets')->whereNotNull('currency_code')->whereRaw('UPPER(currency_code) <> ?', [$base['code']])->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
            $this->addFindingIf($count > 0, 'wallets', 'manual_review', 'wallet_currency_differs_from_system', 'Wallet currency differs from configured system currency. This is an investigation finding, not proof that the balance is wrong.', $count, $ids);

            if ($base['code'] !== 'USD') {
                $usdCount = DB::table('wallets')->whereRaw('UPPER(currency_code) = ?', ['USD'])->count();
                $usdIds = DB::table('wallets')->whereRaw('UPPER(currency_code) = ?', ['USD'])->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
                $this->addFindingIf($usdCount > 0, 'wallets', 'manual_review', 'usd_wallets_on_non_usd_system', 'USD wallet currency exists while the configured system currency is not USD. Investigate before any correction.', $usdCount, $usdIds);
            }
        }
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditWalletTransactions(array $base): void
    {
        if (! $this->hasColumns('wallet_transactions', ['id', 'wallet_id', 'currency_code'])) {
            $this->totals['wallet_transactions'] = 0;

            return;
        }

        $this->totals['wallet_transactions'] = DB::table('wallet_transactions')->count();
        $this->auditCurrencyColumn('wallet_transactions', 'currency_code', 'wallet_transactions');

        if ($this->hasColumns('wallets', ['id', 'currency_code'])) {
            $query = DB::table('wallet_transactions')
                ->join('wallets', 'wallet_transactions.wallet_id', '=', 'wallets.id')
                ->whereNotNull('wallet_transactions.currency_code')
                ->whereNotNull('wallets.currency_code')
                ->whereRaw('UPPER(wallet_transactions.currency_code) <> UPPER(wallets.currency_code)');
            $count = (clone $query)->count();
            $ids = (clone $query)->limit(self::SAMPLE_LIMIT)->pluck('wallet_transactions.id')->all();
            $this->addFindingIf($count > 0, 'wallet_transactions', 'conflict', 'wallet_transaction_wallet_currency_mismatch', 'Wallet transaction currency differs from its wallet currency.', $count, $ids);
        }

        if ($base['code']) {
            $count = DB::table('wallet_transactions')->whereNotNull('currency_code')->whereRaw('UPPER(currency_code) <> ?', [$base['code']])->count();
            $ids = DB::table('wallet_transactions')->whereNotNull('currency_code')->whereRaw('UPPER(currency_code) <> ?', [$base['code']])->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
            $this->addFindingIf($count > 0, 'wallet_transactions', 'manual_review', 'wallet_transaction_currency_differs_from_system', 'Wallet transaction currency differs from configured system currency. Review separately from wallet balance correctness.', $count, $ids);
        }
    }

    private function auditPaymentTransactions(): void
    {
        if (! $this->hasColumns('order_payment_transactions', ['id', 'order_id', 'currency'])) {
            $this->totals['order_payment_transactions'] = 0;

            return;
        }

        $this->totals['order_payment_transactions'] = DB::table('order_payment_transactions')->count();
        $this->auditCurrencyColumn('order_payment_transactions', 'currency', 'payment_transactions');

        if ($this->hasColumns('orders', ['id', 'currency_code'])) {
            $query = DB::table('order_payment_transactions')
                ->join('orders', 'order_payment_transactions.order_id', '=', 'orders.id')
                ->whereNotNull('order_payment_transactions.currency')
                ->whereNotNull('orders.currency_code')
                ->whereRaw('UPPER(order_payment_transactions.currency) <> UPPER(orders.currency_code)');
            $count = (clone $query)->count();
            $ids = (clone $query)->limit(self::SAMPLE_LIMIT)->pluck('order_payment_transactions.id')->all();
            $this->addFindingIf($count > 0, 'payment_transactions', 'conflict', 'payment_order_currency_mismatch', 'Payment transaction currency differs from linked order currency.', $count, $ids);
        }

        $this->auditGatewayTransactionSupport();
    }

    private function auditSellerStatements(): void
    {
        if (! $this->hasColumns('seller_statements', ['id', 'currency_code'])) {
            $this->totals['seller_statements'] = 0;

            return;
        }

        $this->totals['seller_statements'] = DB::table('seller_statements')->count();
        $this->auditCurrencyColumn('seller_statements', 'currency_code', 'seller_statements');

        if ($this->hasColumns('seller_statements', ['id', 'order_id', 'currency_code']) && $this->hasColumns('orders', ['id', 'currency_code'])) {
            $query = DB::table('seller_statements')
                ->join('orders', 'seller_statements.order_id', '=', 'orders.id')
                ->whereNotNull('seller_statements.currency_code')
                ->whereNotNull('orders.currency_code')
                ->whereRaw('UPPER(seller_statements.currency_code) <> UPPER(orders.currency_code)');
            $count = (clone $query)->count();
            $ids = (clone $query)->limit(self::SAMPLE_LIMIT)->pluck('seller_statements.id')->all();
            $this->addFindingIf($count > 0, 'seller_statements', 'conflict', 'seller_statement_order_currency_mismatch', 'Seller statement currency differs from linked order currency.', $count, $ids);

            $review = DB::table('seller_statements')
                ->whereNull('order_id')
                ->orWhereNull('currency_code');
            $reviewCount = (clone $review)->count();
            $reviewIds = (clone $review)->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
            $this->addFindingIf($reviewCount > 0, 'seller_statements', 'manual_review', 'seller_statement_currency_not_verifiable', 'Some seller statements are unlinked or missing currency, so order-currency comparison is not possible.', $reviewCount, $reviewIds);
        }
    }

    private function auditPosSessions(): void
    {
        if (! $this->hasColumns('pos_payment_sessions', ['id', 'currency_code'])) {
            $this->totals['pos_payment_sessions'] = 0;

            return;
        }

        $this->totals['pos_payment_sessions'] = DB::table('pos_payment_sessions')->count();
        $this->auditCurrencyColumn('pos_payment_sessions', 'currency_code', 'pos_sessions');

        if ($this->hasColumns('stores', ['id', 'currency_code']) && $this->hasColumns('pos_payment_sessions', ['id', 'store_id', 'currency_code'])) {
            $query = DB::table('pos_payment_sessions')
                ->join('stores', 'pos_payment_sessions.store_id', '=', 'stores.id')
                ->whereNotNull('pos_payment_sessions.currency_code')
                ->whereNotNull('stores.currency_code')
                ->whereRaw('UPPER(pos_payment_sessions.currency_code) <> UPPER(stores.currency_code)');
            $count = (clone $query)->count();
            $ids = (clone $query)->limit(self::SAMPLE_LIMIT)->pluck('pos_payment_sessions.id')->all();
            $this->addFindingIf($count > 0, 'pos_sessions', 'conflict', 'pos_session_store_currency_mismatch', 'POS session currency differs from linked store currency.', $count, $ids);
        }

        if ($this->hasColumns('orders', ['id', 'currency_code']) && $this->hasColumns('pos_payment_sessions', ['id', 'order_id', 'currency_code'])) {
            $query = DB::table('pos_payment_sessions')
                ->join('orders', 'pos_payment_sessions.order_id', '=', 'orders.id')
                ->whereNotNull('pos_payment_sessions.currency_code')
                ->whereNotNull('orders.currency_code')
                ->whereRaw('UPPER(pos_payment_sessions.currency_code) <> UPPER(orders.currency_code)');
            $count = (clone $query)->count();
            $ids = (clone $query)->limit(self::SAMPLE_LIMIT)->pluck('pos_payment_sessions.id')->all();
            $this->addFindingIf($count > 0, 'pos_sessions', 'conflict', 'pos_session_order_currency_mismatch', 'POS session currency differs from linked order currency.', $count, $ids);
        }
    }

    private function auditPosRefunds(): void
    {
        if (! Schema::hasTable('pos_refunds')) {
            $this->totals['pos_refunds'] = 0;

            return;
        }

        $this->totals['pos_refunds'] = DB::table('pos_refunds')->count();

        if (! $this->hasColumns('orders', ['id', 'currency_code']) || ! Schema::hasColumn('pos_refunds', 'order_id')) {
            $this->addFinding('pos_refunds', 'manual_review', 'pos_refund_currency_not_established', 'POS refund order links are unavailable, so currency cannot be established reliably.', $this->totals['pos_refunds']);

            return;
        }

        if (! Schema::hasColumn('pos_refunds', 'currency_code')) {
            $linked = DB::table('pos_refunds')->join('orders', 'pos_refunds.order_id', '=', 'orders.id')->whereNotNull('orders.currency_code');
            $linkedCount = (clone $linked)->count();
            $linkedIds = (clone $linked)->limit(self::SAMPLE_LIMIT)->pluck('pos_refunds.id')->all();
            $this->addFindingIf($linkedCount > 0, 'pos_refunds', 'schema_limitation', 'pos_refund_currency_inherited_from_order', 'POS refunds do not store explicit currency; linked refunds inherit currency from their order for audit purposes.', $linkedCount, $linkedIds);
        } else {
            $this->auditCurrencyColumn('pos_refunds', 'currency_code', 'pos_refunds');

            $mismatch = DB::table('pos_refunds')
                ->join('orders', 'pos_refunds.order_id', '=', 'orders.id')
                ->whereNotNull('pos_refunds.currency_code')
                ->whereNotNull('orders.currency_code')
                ->whereRaw('UPPER(pos_refunds.currency_code) <> UPPER(orders.currency_code)');
            $mismatchCount = (clone $mismatch)->count();
            $mismatchIds = (clone $mismatch)->limit(self::SAMPLE_LIMIT)->pluck('pos_refunds.id')->all();
            $this->addFindingIf($mismatchCount > 0, 'pos_refunds', 'conflict', 'pos_refund_order_currency_mismatch', 'POS refund currency differs from linked order currency.', $mismatchCount, $mismatchIds);

            $missingButLinked = DB::table('pos_refunds')
                ->join('orders', 'pos_refunds.order_id', '=', 'orders.id')
                ->whereNull('pos_refunds.currency_code')
                ->whereNotNull('orders.currency_code')
                ->where('orders.currency_code', '<>', '');
            $linkedCount = (clone $missingButLinked)->count();
            $linkedIds = (clone $missingButLinked)->limit(self::SAMPLE_LIMIT)->pluck('pos_refunds.id')->all();
            $this->addFindingIf($linkedCount > 0, 'pos_refunds', 'schema_limitation', 'pos_refund_currency_inherited_from_order', 'POS refunds are missing explicit currency; linked refunds inherit currency from their order for audit purposes.', $linkedCount, $linkedIds);
        }

        $review = DB::table('pos_refunds')
            ->leftJoin('orders', 'pos_refunds.order_id', '=', 'orders.id')
            ->where(fn ($q) => $q->whereNull('orders.id')->orWhereNull('orders.currency_code')->orWhere('orders.currency_code', ''));
        $reviewCount = (clone $review)->count();
        $reviewIds = (clone $review)->limit(self::SAMPLE_LIMIT)->pluck('pos_refunds.id')->all();
        $this->addFindingIf($reviewCount > 0, 'pos_refunds', 'manual_review', 'pos_refund_currency_not_established', 'Some POS refunds cannot reliably inherit currency from a linked order.', $reviewCount, $reviewIds);
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditDeliveryZones(array $base): void
    {
        if (! Schema::hasTable('delivery_zones')) {
            $this->totals['delivery_zones'] = 0;

            return;
        }

        $this->totals['delivery_zones'] = DB::table('delivery_zones')->count();

        if ($this->hasColumns('delivery_zones', ['id', 'country_id', 'country_code', 'currency_code'])) {
            $missingZoneContext = DB::table('delivery_zones')
                ->where(fn ($query) => $query
                    ->whereNull('country_id')
                    ->orWhereNull('country_code')
                    ->orWhere('country_code', '')
                    ->orWhereNull('currency_code')
                    ->orWhere('currency_code', ''))
                ->limit(self::SAMPLE_LIMIT)
                ->pluck('id')
                ->all();
            $missingZoneContextCount = DB::table('delivery_zones')
                ->where(fn ($query) => $query
                    ->whereNull('country_id')
                    ->orWhereNull('country_code')
                    ->orWhere('country_code', '')
                    ->orWhereNull('currency_code')
                    ->orWhere('currency_code', ''))
                ->count();
            $this->addFindingIf($missingZoneContextCount > 0, 'delivery_zones', 'manual_review', 'zone_country_currency_missing', 'Delivery zones missing explicit country or currency context require assignment before zone-driven multi-currency can be used.', $missingZoneContextCount, $missingZoneContext);

            $this->auditCurrencyColumn('delivery_zones', 'currency_code', 'delivery_zones');
        }

        if (! $this->hasColumns('store_zone', ['zone_id', 'store_id']) || ! $this->hasColumns('stores', ['id'])) {
            $this->addFinding('delivery_zones', 'schema_limitation', 'zone_store_links_unavailable', 'Zone/store links are unavailable, so zone country and currency coverage cannot be audited.', $this->totals['delivery_zones']);

            return;
        }

        $zoneCurrencySelect = Schema::hasColumn('delivery_zones', 'currency_code')
            ? 'delivery_zones.currency_code as zone_currency_code'
            : DB::raw('NULL as zone_currency_code');

        $rows = DB::table('delivery_zones')
            ->leftJoin('store_zone', 'delivery_zones.id', '=', 'store_zone.zone_id')
            ->leftJoin('stores', 'store_zone.store_id', '=', 'stores.id')
            ->select('delivery_zones.id', $zoneCurrencySelect, 'stores.country_code', 'stores.currency_code')
            ->get()
            ->groupBy('id');

        $multipleCountries = [];
        $multipleCurrencies = [];
        $storeSystemMismatch = [];
        $missingMetadata = [];
        $storeZoneMismatch = [];

        foreach ($rows as $zoneId => $stores) {
            $countries = $stores->pluck('country_code')->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
            $currencies = $stores->pluck('currency_code')->map(fn ($v) => $this->currencyValidator->normalize($v))->filter()->unique()->values();

            if ($countries->count() > 1) {
                $multipleCountries[] = (int) $zoneId;
            }

            if ($currencies->count() > 1) {
                $multipleCurrencies[] = (int) $zoneId;
            }

            if ($base['code'] && $currencies->contains(fn ($currency) => $currency !== $base['code'])) {
                $storeSystemMismatch[] = (int) $zoneId;
            }

            if ($stores->contains(fn ($row) => empty($row->country_code) || empty($row->currency_code))) {
                $missingMetadata[] = (int) $zoneId;
            }

            $zoneCurrency = $this->currencyValidator->normalize($stores->first()->zone_currency_code ?? null);
            if ($zoneCurrency && $currencies->contains(fn ($currency) => $currency !== $zoneCurrency)) {
                $storeZoneMismatch[] = (int) $zoneId;
            }
        }

        $this->addFindingIf(count($multipleCountries) > 0, 'delivery_zones', 'manual_review', 'zone_multiple_store_countries', 'Delivery zone is linked to stores from multiple countries. No country is inferred from zone geometry.', count($multipleCountries), array_slice($multipleCountries, 0, self::SAMPLE_LIMIT));
        $this->addFindingIf(count($multipleCurrencies) > 0, 'delivery_zones', 'manual_review', 'zone_multiple_store_currencies', 'Delivery zone is linked to stores with multiple currencies.', count($multipleCurrencies), array_slice($multipleCurrencies, 0, self::SAMPLE_LIMIT));
        $this->addFindingIf(count($storeSystemMismatch) > 0, 'delivery_zones', 'manual_review', 'zone_store_system_currency_mismatch', 'Delivery zone has linked stores whose currency differs from configured system currency.', count($storeSystemMismatch), array_slice($storeSystemMismatch, 0, self::SAMPLE_LIMIT));
        $this->addFindingIf(count($missingMetadata) > 0, 'delivery_zones', 'manual_review', 'zone_store_metadata_missing', 'Delivery zone has linked stores with missing country or currency metadata.', count($missingMetadata), array_slice($missingMetadata, 0, self::SAMPLE_LIMIT));
        $this->addFindingIf(count($storeZoneMismatch) > 0, 'delivery_zones', 'conflict', 'zone_store_currency_mismatch', 'Delivery zone currency differs from one or more linked store currencies.', count($storeZoneMismatch), array_slice($storeZoneMismatch, 0, self::SAMPLE_LIMIT));
    }

    /**
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     */
    private function auditGateways(array $base): void
    {
        $settings = $this->settingValue('payment');
        $safeGatewayCurrencies = [
            'stripe' => $settings['stripeCurrencyCode'] ?? null,
            'paystack' => $settings['paystackCurrencyCode'] ?? null,
            'flutterwave' => $settings['flutterwaveCurrencyCode'] ?? null,
            'xendit' => $settings['xenditCurrencyCode'] ?? null,
            'mercado_pago' => $settings['mercadoPagoCurrencyCode'] ?? null,
        ];

        $configured = array_filter($safeGatewayCurrencies, fn ($currency) => $currency !== null && $currency !== '');
        $this->totals['gateways_configured_with_currency'] = count($configured);

        foreach ($configured as $gateway => $currency) {
            $normalized = $this->currencyValidator->normalize((string) $currency);
            $classification = $this->currencyValidator->classify($normalized);

            if ($classification !== 'valid') {
                $this->addFinding('gateways', 'manual_review', "gateway_{$gateway}_{$classification}", "Configured {$gateway} currency is missing or invalid.", 1);
                continue;
            }

            if ($base['code'] && $normalized !== $base['code']) {
                $this->addFinding('gateways', 'warning', 'gateway_currency_differs_from_system', "Configured {$gateway} currency differs from configured system currency.", 1);
            }
        }
    }

    private function auditGatewayTransactionSupport(): void
    {
        if (! $this->hasColumns('order_payment_transactions', ['id', 'currency', 'payment_method'])) {
            return;
        }

        $availabilityService = app(PaymentCurrencyAvailabilityService::class);
        $unsupportedIds = [];
        $unsupportedCount = 0;

        DB::table('order_payment_transactions')
            ->select(['id', 'currency', 'payment_method'])
            ->whereNotNull('currency')
            ->orderBy('id')
            ->chunk(500, function ($transactions) use ($availabilityService, &$unsupportedIds, &$unsupportedCount) {
                foreach ($transactions as $transaction) {
                    $availability = $availabilityService->availabilityForMethod((string) $transaction->payment_method, (string) $transaction->currency);
                    if (in_array($availability['reason_code'] ?? null, ['currency_not_supported', 'razorpay_allowed_currency_required'], true)) {
                        $unsupportedCount++;
                        if (count($unsupportedIds) < self::SAMPLE_LIMIT) {
                            $unsupportedIds[] = (int) $transaction->id;
                        }
                    }
                }
            });

        $this->addFindingIf($unsupportedCount > 0, 'payment_transactions', 'conflict', 'unsupported_gateway_transaction_currency', 'Payment transaction currency is not supported by the configured gateway currency rules.', $unsupportedCount, $unsupportedIds);
    }

    private function auditPhase2SnapshotCoverage(): void
    {
        $coverage = [];

        foreach ([
            'orders' => ['base_currency_code', 'display_currency_code', 'display_exchange_rate'],
            'order_payment_transactions' => ['order_currency_code', 'gateway_currency_code', 'gateway_amount', 'gateway_exchange_rate'],
            'pos_refunds' => ['currency_code'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $coverage[$table] = ['total' => DB::table($table)->count()];
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $coverage[$table][$column] = 'missing_column';
                    $this->addFinding('phase2_snapshots', 'schema_limitation', 'phase2_snapshot_column_missing', "{$table}.{$column} is not available, so Phase 2 snapshot coverage cannot be audited.", 1);
                    continue;
                }

                $coverage[$table][$column] = [
                    'populated' => DB::table($table)->whereNotNull($column)->count(),
                    'missing' => DB::table($table)->whereNull($column)->count(),
                ];
            }
        }

        $this->totals['phase2_snapshot_coverage'] = $coverage;
    }

    private function auditPaymentSnapshotIntegrity(): void
    {
        if (! $this->hasColumns('order_payment_transactions', ['id', 'order_id', 'amount', 'currency', 'order_currency_code', 'gateway_currency_code', 'gateway_amount', 'payment_status'])) {
            return;
        }

        $missingSnapshots = [];
        $snapshotMismatch = [];
        $amountMismatch = [];
        $missingSnapshotCount = 0;
        $snapshotMismatchCount = 0;
        $amountMismatchCount = 0;

        DB::table('order_payment_transactions')
            ->leftJoin('orders', 'order_payment_transactions.order_id', '=', 'orders.id')
            ->select([
                'order_payment_transactions.id',
                'order_payment_transactions.amount',
                'order_payment_transactions.currency',
                'order_payment_transactions.order_currency_code',
                'order_payment_transactions.gateway_currency_code',
                'order_payment_transactions.gateway_amount',
                'order_payment_transactions.payment_status',
                'orders.currency_code as linked_order_currency_code',
            ])
            ->where('order_payment_transactions.payment_status', PaymentStatusEnum::COMPLETED())
            ->orderBy('order_payment_transactions.id')
            ->chunk(500, function ($transactions) use (&$missingSnapshots, &$snapshotMismatch, &$amountMismatch, &$missingSnapshotCount, &$snapshotMismatchCount, &$amountMismatchCount) {
                foreach ($transactions as $transaction) {
                    if (blank($transaction->order_currency_code) || blank($transaction->gateway_currency_code) || $transaction->gateway_amount === null) {
                        $missingSnapshotCount++;
                        if (count($missingSnapshots) < self::SAMPLE_LIMIT) {
                            $missingSnapshots[] = (int) $transaction->id;
                        }
                    }

                    $linkedOrderCurrency = $this->currencyValidator->normalize($transaction->linked_order_currency_code ?? null);
                    $snapshotOrderCurrency = $this->currencyValidator->normalize($transaction->order_currency_code ?? null);
                    $gatewayCurrency = $this->currencyValidator->normalize($transaction->gateway_currency_code ?? $transaction->currency ?? null);
                    if (($linkedOrderCurrency && $snapshotOrderCurrency && $linkedOrderCurrency !== $snapshotOrderCurrency)
                        || ($snapshotOrderCurrency && $gatewayCurrency && $snapshotOrderCurrency !== $gatewayCurrency)) {
                        $snapshotMismatchCount++;
                        if (count($snapshotMismatch) < self::SAMPLE_LIMIT) {
                            $snapshotMismatch[] = (int) $transaction->id;
                        }
                    }

                    if ($transaction->gateway_amount !== null && abs((float) $transaction->amount - (float) $transaction->gateway_amount) > 0.01) {
                        $amountMismatchCount++;
                        if (count($amountMismatch) < self::SAMPLE_LIMIT) {
                            $amountMismatch[] = (int) $transaction->id;
                        }
                    }
                }
            });

        $this->addFindingIf($missingSnapshotCount > 0, 'payment_transactions', 'manual_review', 'completed_payment_missing_gateway_snapshots', 'Completed payment transaction is missing one or more gateway snapshot fields.', $missingSnapshotCount, $missingSnapshots);
        $this->addFindingIf($snapshotMismatchCount > 0, 'payment_transactions', 'conflict', 'payment_snapshot_currency_mismatch', 'Payment transaction snapshot currency differs from linked order or gateway currency.', $snapshotMismatchCount, $snapshotMismatch);
        $this->addFindingIf($amountMismatchCount > 0, 'payment_transactions', 'conflict', 'gateway_amount_mismatch', 'Payment transaction gateway amount differs from transaction amount.', $amountMismatchCount, $amountMismatch);
    }

    private function auditPhase4WriteGuardCoverage(): void
    {
        $this->totals['phase4_write_guardrails'] = [
            'currency_write_guard_available' => class_exists(CurrencyWriteGuard::class),
            'guarded_write_paths' => [
                'order_payment_transactions',
                'wallet_transactions',
                'seller_statements',
                'pos_payment_sessions',
                'pos_refunds',
                'return_expense_records',
            ],
        ];
    }

    private function classifyOrderSource(object $order): string
    {
        $createdBy = isset($order->created_by) ? (string) $order->created_by : null;
        $hasPosSignal = $this->hasPosSignal($order);

        if ($createdBy === 'seller' && $hasPosSignal) {
            return 'pos';
        }

        if (($createdBy === null || $createdBy === '' || $createdBy === 'customer') && ! $hasPosSignal) {
            return 'customer_checkout';
        }

        if ($createdBy === 'admin' && ! $hasPosSignal) {
            return 'admin_or_other';
        }

        return 'unknown';
    }

    private function hasPosSignal(object $order): bool
    {
        $paymentMethod = isset($order->payment_method) ? (string) $order->payment_method : '';

        return in_array($paymentMethod, [PaymentTypeEnum::POS_CASH(), PaymentTypeEnum::POS_UPI(), PaymentTypeEnum::POS_CUSTOM(), 'cash', 'upi', 'custom'], true)
            || ! empty($order->walkin_customer_name ?? null)
            || ! empty($order->walkin_customer_mobile ?? null)
            || (float) ($order->pos_savings ?? 0) > 0
            || (float) ($order->pos_split_cash ?? 0) > 0
            || $this->orderHasPosSession((int) $order->id);
    }

    private function orderHasPosSession(int $orderId): bool
    {
        return $this->hasColumns('pos_payment_sessions', ['order_id'])
            && DB::table('pos_payment_sessions')->where('order_id', $orderId)->exists();
    }

    private function auditCurrencyColumn(string $table, string $column, string $category): void
    {
        if (! $this->hasColumns($table, ['id', $column])) {
            return;
        }

        $seen = DB::table($table)
            ->selectRaw("UPPER({$column}) as currency_code, COUNT(*) as aggregate_count")
            ->groupByRaw("UPPER({$column})")
            ->pluck('aggregate_count', 'currency_code')
            ->all();

        $this->totals["{$category}_currencies"] = $seen;

        foreach ($seen as $code => $count) {
            $normalizedCode = is_string($code) ? $this->currencyValidator->normalize($code) : null;
            $classification = $this->currencyValidator->classify($normalizedCode);
            if ($classification !== 'valid') {
                $ids = DB::table($table)
                    ->where(function ($query) use ($column, $normalizedCode) {
                        if ($normalizedCode === null) {
                            $query->whereNull($column)->orWhere($column, '');

                            return;
                        }

                        $query->whereRaw("UPPER({$column}) = ?", [$normalizedCode]);
                    })
                    ->limit(self::SAMPLE_LIMIT)
                    ->pluck('id')
                    ->all();
                $this->addFinding($category, 'manual_review', $classification, "{$table}.{$column} contains missing or invalid currency codes.", (int) $count, $ids);
                continue;
            }

            if (! isset($this->countryCurrencyCodes[$normalizedCode])) {
                $ids = DB::table($table)->whereRaw("UPPER({$column}) = ?", [$normalizedCode])->limit(self::SAMPLE_LIMIT)->pluck('id')->all();
                $this->addFinding($category, 'manual_review', 'missing_country_metadata', "{$table}.{$column} uses a valid ISO currency that is missing from countries.currency metadata.", (int) $count, $ids);
            }
        }
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

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function selectExistingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
    }

    /**
     * @param  array<int, mixed>  $sampleIds
     */
    private function addFindingIf(bool $condition, string $category, string $severity, string $code, string $message, int $recordCount, array $sampleIds = []): void
    {
        if ($condition) {
            $this->addFinding($category, $severity, $code, $message, $recordCount, $sampleIds);
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
     * @param  array{code:?string,symbol:?string,country_code:?string,source:string}  $base
     * @return array<string, mixed>
     */
    private function buildReport(array $base): array
    {
        $severityCounts = [];
        foreach ($this->findings as $finding) {
            $severityCounts[$finding['severity']] = ($severityCounts[$finding['severity']] ?? 0) + 1;
        }

        $finalStatus = $this->finalStatus($severityCounts);

        return [
            'report_version' => self::REPORT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'base_currency_candidate' => $base,
            'summary' => [
                'final_status' => $finalStatus,
                'totals' => $this->totals,
                'finding_counts_by_severity' => $severityCounts,
            ],
            'findings' => $this->findings,
            'schema_limitations' => array_values(array_filter($this->findings, fn ($finding) => $finding['severity'] === 'schema_limitation')),
            'warnings' => array_values(array_filter($this->findings, fn ($finding) => $finding['severity'] === 'warning')),
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
     * @param  array<string, mixed>  $report
     */
    private function renderHumanReport(array $report): void
    {
        $base = $report['base_currency_candidate'];
        $summary = $report['summary'];

        $this->line('Hyperlocal Currency Audit');
        $this->line(sprintf(
            'Base currency candidate: %s (%s), country: %s',
            $base['code'] ?: 'UNKNOWN',
            $base['symbol'] ?: 'UNKNOWN',
            $base['country_code'] ?: 'UNKNOWN',
        ));
        $this->newLine();

        foreach ($summary['totals'] as $key => $value) {
            if (is_scalar($value)) {
                $this->line(Str::headline($key) . ': inspected ' . $value);
            }
        }

        $this->newLine();
        foreach ($report['findings'] as $finding) {
            $sample = $finding['sample_ids'] ? ' Sample IDs: ' . implode(', ', $finding['sample_ids']) . '.' : '';
            $this->line(sprintf(
                '[%s] %s/%s: %s Records: %d.%s',
                strtoupper($finding['severity']),
                $finding['category'],
                $finding['code'],
                $finding['message'],
                $finding['record_count'],
                $sample,
            ));
        }

        if (empty($report['findings'])) {
            $this->info('No currency audit findings.');
        }

        $this->newLine();
        $this->line('Final status: ' . $summary['final_status']);
    }

    private function safeOutputPath(string $output): ?string
    {
        $base = storage_path('app/currency-audits');
        $path = $output;

        if (! Str::endsWith(strtolower($path), '.json')) {
            return null;
        }

        if (! preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) && ! Str::startsWith($path, ['/', '\\'])) {
            $path = base_path($path);
        }

        $normalizedBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base);
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return Str::startsWith($normalizedPath, $normalizedBase . DIRECTORY_SEPARATOR) ? $normalizedPath : null;
    }
}
