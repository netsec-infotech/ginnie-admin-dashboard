<?php

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderPaymentTransaction;
use App\Models\PosRefund;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Http\Resources\User\OrderPaymentResource;
use App\Http\Resources\User\OrderResource;
use App\Http\Resources\User\WalletResource;
use App\Http\Resources\User\WalletTransactionResource;
use App\Services\CurrencyService;
use App\Services\SellerStatementService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedCurrencyCountry('US Dollar', 'USA', 'US', '840', 'USD', '$');
    seedCurrencyCountry('UAE Dirham', 'ARE', 'AE', '784', 'AED', 'د.إ');
    seedCurrencyCountry('Indian Rupee', 'IND', 'IN', '356', 'INR', '₹');

    Setting::create([
        'variable' => 'system',
        'value' => json_encode([
            'currency' => 'AED',
            'currencySymbol' => 'د.إ',
            'countryCode' => 'AE',
        ]),
    ]);
});

function seedCurrencyCountry(string $name, string $iso3, string $iso2, string $numeric, string $currency, string $symbol): void
{
    DB::table('countries')->insert([
        'name' => $name,
        'iso3' => $iso3,
        'iso2' => $iso2,
        'numeric_code' => $numeric,
        'phonecode' => '+1',
        'currency' => $currency,
        'currency_name' => $name,
        'currency_symbol' => $symbol,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeAuditOrder(array $attributes = []): Order
{
    $user = $attributes['user'] ?? User::factory()->create();
    unset($attributes['user']);
    $zoneId = $attributes['delivery_zone_id'] ?? DeliveryZone::factory()->create()->id;

    return Order::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'slug' => 'audit-order-' . Str::random(10),
        'email' => 'audit@example.test',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'AED',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'delivery_zone_id' => $zoneId,
        'wallet_balance' => 0,
        'subtotal' => 100,
        'total_payable' => 100,
        'final_total' => 100,
        'billing_name' => 'Audit Customer',
        'billing_address_1' => 'Hidden',
        'billing_landmark' => 'Hidden',
        'billing_zip' => '00000',
        'billing_phone' => '0000000000',
        'billing_address_type' => 'home',
        'billing_latitude' => 1,
        'billing_longitude' => 1,
        'billing_city' => 'Hidden',
        'billing_state' => 'Hidden',
        'billing_country' => 'Hidden',
        'billing_country_code' => 'AE',
        'shipping_name' => 'Audit Customer',
        'shipping_address_1' => 'Hidden',
        'shipping_landmark' => 'Hidden',
        'shipping_zip' => '00000',
        'shipping_phone' => '0000000000',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 1,
        'shipping_longitude' => 1,
        'shipping_city' => 'Hidden',
        'shipping_state' => 'Hidden',
        'shipping_country' => 'Hidden',
        'shipping_country_code' => 'AE',
    ], $attributes));
}

function currencyAuditReport(): array
{
    expect(Artisan::call('hyperlocal:currency-audit', ['--json' => true]))->toBe(0);

    return json_decode(Artisan::output(), true);
}

function currencyBackfillReport(array $options = []): array
{
    expect(Artisan::call('hyperlocal:currency-backfill', array_merge(['--json' => true], $options)))->toBe(0);

    return json_decode(Artisan::output(), true);
}

it('uses the configured system currency as the base candidate and does not default to INR', function () {
    $report = currencyAuditReport();

    expect($report['base_currency_candidate']['code'])->toBe('AED')
        ->and($report['base_currency_candidate']['code'])->not->toBe('INR');
});

it('keeps ambiguous seller-created orders as unknown unless POS signals exist', function () {
    makeAuditOrder(['created_by' => 'seller', 'payment_method' => 'cod']);
    makeAuditOrder(['created_by' => 'seller', 'payment_method' => 'posCash']);

    $report = currencyAuditReport();

    expect($report['summary']['totals']['orders_by_source']['unknown'])->toBe(1)
        ->and($report['summary']['totals']['orders_by_source']['pos'])->toBe(1);
});

it('classifies malformed unknown iso and missing country metadata separately', function () {
    Store::factory()->create(['currency_code' => '12', 'country_code' => 'AE']);
    Store::factory()->create(['currency_code' => 'ZZZ', 'country_code' => 'AE']);
    Store::factory()->create(['currency_code' => 'EUR', 'country_code' => 'DE']);

    $codes = collect(currencyAuditReport()['findings'])->pluck('code');

    expect($codes)->toContain('malformed_code')
        ->and($codes)->toContain('unknown_iso_code')
        ->and($codes)->toContain('missing_country_metadata');
});

it('reports usd wallets on non-usd systems as investigation findings and separates transaction mismatches', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'type' => 'customer',
        'balance' => 100,
        'currency_code' => 'USD',
    ]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'transaction_type' => 'deposit',
        'payment_method' => 'test',
        'amount' => 10,
        'currency_code' => 'INR',
        'status' => 'completed',
    ]);

    $findings = collect(currencyAuditReport()['findings']);

    expect($findings->where('code', 'usd_wallets_on_non_usd_system')->first()['severity'])->toBe('manual_review')
        ->and($findings->where('code', 'wallet_transaction_wallet_currency_mismatch')->first()['severity'])->toBe('conflict')
        ->and($findings->where('code', 'wallet_transaction_currency_differs_from_system')->first()['severity'])->toBe('manual_review');
});

it('reports payment transaction order mismatches and gateway config differences separately', function () {
    Setting::create([
        'variable' => 'payment',
        'value' => json_encode([
            'paystackCurrencyCode' => 'NGN',
        ]),
    ]);
    $order = makeAuditOrder(['currency_code' => 'AED']);

    OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'transaction_id' => 'txn-audit',
        'amount' => 100,
        'currency' => 'USD',
        'payment_method' => 'stripePayment',
        'payment_status' => 'pending',
    ]);

    $findings = collect(currencyAuditReport()['findings']);

    expect($findings->where('code', 'payment_order_currency_mismatch')->first()['severity'])->toBe('conflict')
        ->and($findings->where('code', 'gateway_currency_differs_from_system')->first()['severity'])->toBe('warning');
});

it('treats linked pos refunds as inherited currency schema limitations and unlinked refunds as manual review', function () {
    $store = Store::factory()->create(['currency_code' => 'AED', 'country_code' => 'AE']);
    $order = makeAuditOrder(['currency_code' => 'AED', 'created_by' => 'seller', 'payment_method' => 'posCash']);

    PosRefund::create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'total_amount' => 10,
        'reason' => 'Audit linked refund',
    ]);

    $invalidCurrencyOrder = makeAuditOrder(['currency_code' => '', 'created_by' => 'seller', 'payment_method' => 'posCash']);
    PosRefund::create([
        'order_id' => $invalidCurrencyOrder->id,
        'store_id' => $store->id,
        'total_amount' => 5,
        'reason' => 'Audit invalid-currency refund',
    ]);

    $findings = collect(currencyAuditReport()['findings']);

    expect($findings->where('code', 'pos_refund_currency_inherited_from_order')->first()['severity'])->toBe('schema_limitation')
        ->and($findings->where('code', 'pos_refund_currency_not_established')->first()['severity'])->toBe('manual_review');
});

it('reports delivery zone country currency system mismatch and missing metadata separately', function () {
    $zone = DeliveryZone::factory()->create();
    $usdStore = Store::factory()->create(['currency_code' => 'USD', 'country_code' => 'US']);
    $inrStore = Store::factory()->create(['currency_code' => 'INR', 'country_code' => 'IN']);
    $missingStore = Store::factory()->create(['currency_code' => null, 'country_code' => null]);

    DB::table('store_zone')->insert([
        ['zone_id' => $zone->id, 'store_id' => $usdStore->id],
        ['zone_id' => $zone->id, 'store_id' => $inrStore->id],
        ['zone_id' => $zone->id, 'store_id' => $missingStore->id],
    ]);

    $codes = collect(currencyAuditReport()['findings'])->pluck('code');

    expect($codes)->toContain('zone_multiple_store_countries')
        ->and($codes)->toContain('zone_multiple_store_currencies')
        ->and($codes)->toContain('zone_store_system_currency_mismatch')
        ->and($codes)->toContain('zone_store_metadata_missing');
});

it('writes structured json safely and refuses unsafe or existing output paths', function () {
    $safePath = storage_path('app/currency-audits/audit-test.json');
    File::delete($safePath);

    $this->artisan('hyperlocal:currency-audit --json --output=storage/app/currency-audits/audit-test.json')
        ->assertSuccessful();

    $report = json_decode(File::get($safePath), true);

    expect($report)->toHaveKeys(['report_version', 'generated_at', 'base_currency_candidate', 'summary', 'findings'])
        ->and($report['report_version'])->toBe('1.0');

    $this->artisan('hyperlocal:currency-audit --json --output=storage/app/currency-audits/audit-test.json')
        ->assertFailed();

    $this->artisan('hyperlocal:currency-audit --json --output=storage/app/not-audit.json')
        ->assertFailed();
});

it('is repeatable read-only and leaves currency formatting unchanged', function () {
    $order = makeAuditOrder(['currency_code' => 'AED']);
    $wallet = Wallet::factory()->create(['currency_code' => 'AED', 'balance' => 50]);

    $before = [
        'orders' => DB::table('orders')->where('id', $order->id)->first(),
        'wallets' => DB::table('wallets')->where('id', $wallet->id)->first(),
        'format' => app(CurrencyService::class)->format(12.5),
    ];

    $first = currencyAuditReport();

    $second = currencyAuditReport();

    $after = [
        'orders' => DB::table('orders')->where('id', $order->id)->first(),
        'wallets' => DB::table('wallets')->where('id', $wallet->id)->first(),
        'format' => app(CurrencyService::class)->format(12.5),
    ];

    unset($first['generated_at'], $second['generated_at']);

    expect($second)->toEqual($first)
        ->and((array) $after['orders'])->toEqual((array) $before['orders'])
        ->and((array) $after['wallets'])->toEqual((array) $before['wallets'])
        ->and($after['format'])->toBe($before['format']);
});

it('phase 2 schema adds nullable currency snapshot columns without backfilling historical rows', function () {
    expect(Schema::hasColumn('orders', 'base_currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'display_currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'display_exchange_rate'))->toBeTrue()
        ->and(Schema::hasColumn('order_payment_transactions', 'order_currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('order_payment_transactions', 'gateway_currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('order_payment_transactions', 'gateway_amount'))->toBeTrue()
        ->and(Schema::hasColumn('pos_refunds', 'currency_code'))->toBeTrue()
        ->and(Schema::hasTable('currency_metadata'))->toBeTrue();

    $legacyOrder = makeAuditOrder(['currency_code' => 'AED']);

    expect($legacyOrder->base_currency_code)->toBeNull()
        ->and($legacyOrder->display_currency_code)->toBeNull()
        ->and($legacyOrder->display_exchange_rate)->toBeNull();
});

it('creates new wallets using system currency instead of hardcoded usd', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->getWallet($user->id)['data'];

    expect($wallet->currency_code)->toBe('AED');
});

it('stores order and gateway currency snapshots on new order payment transactions', function () {
    $order = makeAuditOrder(['currency_code' => 'AED']);

    app(PaymentService::class)->makeOrderPaymentTransaction($order, [
        'transaction_id' => 'snapshot-test',
        'amount' => 42.50,
        'currency' => 'AED',
        'payment_method' => 'stripePayment',
        'message' => 'Snapshot test',
    ], 'pending');

    $transaction = OrderPaymentTransaction::where('transaction_id', 'snapshot-test')->first();

    expect($transaction->order_currency_code)->toBe('AED')
        ->and($transaction->gateway_currency_code)->toBe('AED')
        ->and((float) $transaction->gateway_amount)->toBe(42.5);
});

it('currency backfill dry run reports eligible rows without database writes', function () {
    $store = Store::factory()->create(['currency_code' => 'AED', 'country_code' => 'AE']);
    $order = makeAuditOrder(['currency_code' => 'AED']);
    $transaction = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'transaction_id' => 'dry-run-txn',
        'amount' => 100,
        'currency' => 'AED',
        'payment_method' => 'stripePayment',
        'payment_status' => 'pending',
    ]);
    $refund = PosRefund::create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'total_amount' => 10,
        'reason' => 'Dry run refund',
    ]);

    $before = [
        'order' => DB::table('orders')->where('id', $order->id)->first(),
        'transaction' => DB::table('order_payment_transactions')->where('id', $transaction->id)->first(),
        'refund' => DB::table('pos_refunds')->where('id', $refund->id)->first(),
        'metadata_count' => DB::table('currency_metadata')->count(),
        'run_count' => DB::table('currency_backfill_runs')->count(),
    ];

    $report = currencyBackfillReport(['--dry-run' => true]);
    $after = [
        'order' => DB::table('orders')->where('id', $order->id)->first(),
        'transaction' => DB::table('order_payment_transactions')->where('id', $transaction->id)->first(),
        'refund' => DB::table('pos_refunds')->where('id', $refund->id)->first(),
        'metadata_count' => DB::table('currency_metadata')->count(),
        'run_count' => DB::table('currency_backfill_runs')->count(),
    ];

    expect($report['mode'])->toBe('dry-run')
        ->and($report['summary']['totals']['orders']['eligible'])->toBeGreaterThan(0)
        ->and((array) $after['order'])->toEqual((array) $before['order'])
        ->and((array) $after['transaction'])->toEqual((array) $before['transaction'])
        ->and((array) $after['refund'])->toEqual((array) $before['refund'])
        ->and($after['metadata_count'])->toBe($before['metadata_count'])
        ->and($after['run_count'])->toBe($before['run_count']);
});

it('currency backfill execute fills only safe nullable snapshots and is idempotent', function () {
    $store = Store::factory()->create(['currency_code' => 'AED', 'country_code' => 'AE']);
    $order = makeAuditOrder(['currency_code' => 'AED']);
    $transaction = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'transaction_id' => 'execute-txn',
        'amount' => 125.75,
        'currency' => 'USD',
        'payment_method' => 'stripePayment',
        'payment_status' => 'pending',
    ]);
    $refund = PosRefund::create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'total_amount' => 12,
        'reason' => 'Execute refund',
    ]);

    $report = currencyBackfillReport(['--execute' => true]);
    $order->refresh();
    $transaction->refresh();
    $refund->refresh();

    expect($report['mode'])->toBe('execute')
        ->and($report['run_id'])->not->toBeNull()
        ->and($order->base_currency_code)->toBe('AED')
        ->and($order->display_currency_code)->toBeNull()
        ->and($order->display_exchange_rate)->toBeNull()
        ->and($transaction->order_currency_code)->toBe('AED')
        ->and($transaction->gateway_currency_code)->toBe('USD')
        ->and((float) $transaction->gateway_amount)->toBe(125.75)
        ->and($transaction->gateway_exchange_rate)->toBeNull()
        ->and($refund->currency_code)->toBe('AED')
        ->and(DB::table('currency_metadata')->where('code', 'AED')->where('is_enabled', true)->exists())->toBeTrue()
        ->and(DB::table('currency_metadata')->where('code', 'USD')->where('is_enabled', true)->exists())->toBeFalse();

    $secondReport = currencyBackfillReport(['--execute' => true]);

    expect($secondReport['summary']['totals']['orders']['updated'])->toBe(0)
        ->and($secondReport['summary']['totals']['payments']['updated'])->toBe(0)
        ->and($secondReport['summary']['totals']['pos-refunds']['updated'])->toBe(0);
});

it('currency backfill skips invalid currencies and never overwrites conflicting snapshots', function () {
    $order = makeAuditOrder([
        'currency_code' => 'AED',
        'base_currency_code' => 'USD',
    ]);

    $transaction = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'transaction_id' => 'conflict-txn',
        'amount' => 10,
        'currency' => 'ZZZ',
        'order_currency_code' => 'USD',
        'gateway_currency_code' => 'USD',
        'gateway_amount' => 5,
        'payment_method' => 'stripePayment',
        'payment_status' => 'pending',
    ]);

    $findings = collect(currencyBackfillReport(['--execute' => true, '--only' => 'orders,payments'])['findings']);
    $order->refresh();
    $transaction->refresh();

    expect($order->base_currency_code)->toBe('USD')
        ->and($transaction->order_currency_code)->toBe('USD')
        ->and($transaction->gateway_currency_code)->toBe('USD')
        ->and((float) $transaction->gateway_amount)->toBe(5.0)
        ->and($findings->where('code', 'backfill_currency_conflict')->count())->toBeGreaterThan(0)
        ->and($findings->where('code', 'backfill_rows_skipped')->count())->toBeGreaterThan(0);
});

it('currency backfill reports pos refunds with invalid order currency for review and leaves them unchanged', function () {
    $store = Store::factory()->create(['currency_code' => 'AED', 'country_code' => 'AE']);
    $order = makeAuditOrder(['currency_code' => '']);
    $refund = PosRefund::create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'total_amount' => 20,
        'reason' => 'Invalid order currency refund',
    ]);

    $report = currencyBackfillReport(['--execute' => true, '--only' => 'pos-refunds']);
    $refund->refresh();

    expect($refund->currency_code)->toBeNull()
        ->and($report['summary']['totals']['pos-refunds']['skipped'])->toBe(1)
        ->and(collect($report['findings'])->where('code', 'backfill_rows_skipped')->first()['severity'])->toBe('manual_review');
});

it('currency backfill json output is safe and refuses unsafe or existing paths', function () {
    $safePath = storage_path('app/currency-audits/backfill-test.json');
    File::delete($safePath);

    $this->artisan('hyperlocal:currency-backfill --dry-run --json --output=storage/app/currency-audits/backfill-test.json')
        ->assertSuccessful();

    $report = json_decode(File::get($safePath), true);

    expect($report)->toHaveKeys(['report_version', 'generated_at', 'mode', 'summary', 'findings'])
        ->and($report['report_version'])->toBe('1.0')
        ->and($report['mode'])->toBe('dry-run');

    $this->artisan('hyperlocal:currency-backfill --dry-run --json --output=storage/app/currency-audits/backfill-test.json')
        ->assertFailed();

    $this->artisan('hyperlocal:currency-backfill --dry-run --json --output=storage/app/not-audit.json')
        ->assertFailed();
});

it('currency audit snapshot coverage improves after executing the backfill', function () {
    $order = makeAuditOrder(['currency_code' => 'AED']);

    OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'transaction_id' => 'coverage-txn',
        'amount' => 35,
        'currency' => 'AED',
        'payment_method' => 'stripePayment',
        'payment_status' => 'pending',
    ]);

    $before = currencyAuditReport();

    currencyBackfillReport(['--execute' => true, '--only' => 'orders,payments']);

    $after = currencyAuditReport();

    expect($before['summary']['totals']['phase2_snapshot_coverage']['orders']['base_currency_code']['populated'])->toBeLessThan(
        $after['summary']['totals']['phase2_snapshot_coverage']['orders']['base_currency_code']['populated']
    )
        ->and($before['summary']['totals']['phase2_snapshot_coverage']['order_payment_transactions']['order_currency_code']['populated'])->toBeLessThan(
            $after['summary']['totals']['phase2_snapshot_coverage']['order_payment_transactions']['order_currency_code']['populated']
        );
});

it('phase 4 payment transactions use order currency instead of hardcoded fallback and reject invalid gateway currency', function () {
    $order = makeAuditOrder(['currency_code' => 'AED']);

    app(PaymentService::class)->makeOrderPaymentTransaction($order, [
        'transaction_id' => 'phase4-payment-default',
        'amount' => 25,
        'payment_method' => 'stripePayment',
    ], 'pending');

    $transaction = OrderPaymentTransaction::where('transaction_id', 'phase4-payment-default')->first();

    expect($transaction->currency)->toBe('AED')
        ->and($transaction->order_currency_code)->toBe('AED')
        ->and($transaction->gateway_currency_code)->toBe('AED');

    expect(fn () => app(PaymentService::class)->makeOrderPaymentTransaction($order, [
        'transaction_id' => 'phase4-payment-invalid',
        'amount' => 25,
        'currency' => 'ZZZ',
        'payment_method' => 'stripePayment',
    ], 'pending'))->toThrow(InvalidArgumentException::class);
});

it('phase 4 direct wallet credits write the wallet currency snapshot', function () {
    $user = User::factory()->create();

    $result = app(WalletService::class)->creditWallet(
        userId: $user->id,
        walletType: null,
        amount: 15,
        description: 'Phase 4 credit',
        referenceId: 'phase4-wallet-credit'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['data']['wallet']->currency_code)->toBe('AED')
        ->and($result['data']['transaction']->currency_code)->toBe('AED');
});

it('phase 4 seller statements inherit linked order currency and reject invalid explicit currency', function () {
    $store = Store::factory()->create(['currency_code' => 'AED', 'country_code' => 'AE']);
    $order = makeAuditOrder(['currency_code' => 'AED']);

    $statement = app(SellerStatementService::class)->addEntry([
        'seller_id' => $store->seller_id,
        'entry_type' => 'credit',
        'amount' => 10,
        'order_id' => $order->id,
        'description' => 'Phase 4 seller statement',
    ]);

    expect($statement->currency_code)->toBe('AED');

    expect(fn () => app(SellerStatementService::class)->addEntry([
        'seller_id' => $store->seller_id,
        'entry_type' => 'credit',
        'amount' => 10,
        'currency_code' => 'ZZZ',
        'description' => 'Invalid currency statement',
    ]))->toThrow(InvalidArgumentException::class);
});

it('phase 5 exposes order currency snapshots without changing legacy order fields', function () {
    $order = makeAuditOrder([
        'currency_code' => 'AED',
        'currency_rate' => 1,
        'status' => 'pending',
        'base_currency_code' => 'AED',
        'display_currency_code' => null,
        'display_exchange_rate' => null,
    ]);

    $payload = (new OrderResource($order))->toArray(request());

    expect($payload['currency_code'])->toBe('AED')
        ->and((float) $payload['currency_rate'])->toBe(1.0)
        ->and($payload['base_currency_code'])->toBe('AED')
        ->and($payload['display_currency_code'])->toBeNull()
        ->and($payload['display_exchange_rate'])->toBeNull()
        ->and($payload['currency_snapshot'])->toMatchArray([
            'order_currency_code' => 'AED',
            'base_currency_code' => 'AED',
            'display_currency_code' => null,
            'display_exchange_rate' => null,
        ])
        ->and($payload['currency_snapshot']['formatted']['final_total'])->toBe('د.إ 100.00');
});

it('phase 5 exposes payment gateway snapshots without changing legacy payment fields', function () {
    $order = makeAuditOrder(['currency_code' => 'AED']);
    $transaction = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'transaction_id' => 'phase5-payment',
        'amount' => 100,
        'currency' => 'USD',
        'order_currency_code' => 'AED',
        'gateway_currency_code' => 'USD',
        'gateway_amount' => 100,
        'gateway_exchange_rate' => null,
        'payment_method' => 'stripePayment',
        'payment_status' => 'pending',
    ]);

    $payload = (new OrderPaymentResource($transaction))->toArray(request());

    expect($payload['amount'])->toBe($transaction->amount)
        ->and($payload['currency'])->toBe('USD')
        ->and($payload['order_currency_code'])->toBe('AED')
        ->and($payload['gateway_currency_code'])->toBe('USD')
        ->and((float) $payload['gateway_amount'])->toBe(100.0)
        ->and($payload['gateway_exchange_rate'])->toBeNull()
        ->and($payload['formatted_amount'])->toBe('$ 100.00')
        ->and($payload['formatted_gateway_amount'])->toBe('$ 100.00')
        ->and($payload['currency_snapshot'])->toMatchArray([
            'legacy_currency' => 'USD',
            'order_currency_code' => 'AED',
            'gateway_currency_code' => 'USD',
        ])
        ->and($payload['currency_snapshot']['formatted'])->toMatchArray([
            'amount' => '$ 100.00',
            'gateway_amount' => '$ 100.00',
        ]);
});

it('phase 5 exposes wallet currency snapshots without changing legacy wallet fields', function () {
    $wallet = Wallet::factory()->create(['currency_code' => 'AED', 'balance' => 50]);
    $transaction = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $wallet->user_id,
        'transaction_type' => 'deposit',
        'payment_method' => 'test',
        'amount' => 10,
        'currency_code' => 'AED',
        'status' => 'completed',
    ]);

    $walletPayload = (new WalletResource($wallet))->toArray(request());
    $transactionPayload = (new WalletTransactionResource($transaction))->toArray(request());

    expect($walletPayload['currency_code'])->toBe('AED')
        ->and($walletPayload['formatted_balance'])->toBe('د.إ 50.00')
        ->and($walletPayload['currency_snapshot'])->toMatchArray(['currency_code' => 'AED'])
        ->and($walletPayload['currency_snapshot']['formatted']['balance'])->toBe('د.إ 50.00')
        ->and($transactionPayload['currency_code'])->toBe('AED')
        ->and($transactionPayload['formatted_amount'])->toBe('د.إ 10.00')
        ->and($transactionPayload['currency_snapshot'])->toMatchArray(['currency_code' => 'AED'])
        ->and($transactionPayload['currency_snapshot']['formatted']['amount'])->toBe('د.إ 10.00');
});
