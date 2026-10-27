<?php

use App\Http\Resources\Product\ProductVariantResource;
use App\Http\Resources\User\PaymentSummaryResource;
use App\Models\CurrencyMetadata;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    CurrencyMetadata::create([
        'code' => 'AED',
        'name' => 'UAE Dirham',
        'symbol' => 'د.إ',
        'decimal_places' => 2,
        'minor_unit_factor' => 100,
        'is_enabled' => true,
    ]);

    CurrencyMetadata::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'minor_unit_factor' => 100,
        'is_enabled' => true,
    ]);
});

it('adds store currency formatted prices to customer product variant resources', function () {
    $store = new Store(['id' => 10, 'name' => 'Dubai Store', 'currency_code' => 'AED']);
    $storeVariant = new StoreProductVariant([
        'id' => 20,
        'store_id' => 10,
        'price' => 110.50,
        'special_price' => 99.25,
        'price_exclude_tax' => 105.00,
        'special_price_exclude_tax' => 95.00,
        'stock' => 5,
    ]);
    $storeVariant->setRelation('store', $store);

    $variant = new ProductVariant([
        'id' => 30,
        'title' => ['en' => 'Default'],
        'slug' => 'default',
    ]);
    $variant->setRelation('storeProductVariants', collect([$storeVariant]));
    $variant->setRelation('attributes', collect());

    $payload = (new ProductVariantResource($variant))->toArray(request());

    expect($payload)
        ->toMatchArray([
            'currency_code' => 'AED',
            'formatted_price' => 'د.إ 110.50',
            'formatted_special_price' => 'د.إ 99.25',
            'formatted_display_price' => 'د.إ 110.50',
            'formatted_display_special_price' => 'د.إ 99.25',
        ])
        ->and($payload['price'])->toBe(110.50)
        ->and($payload['display_price'])->toBe(110.50);
});

it('adds formatted currency values to cart payment summaries without changing raw totals', function () {
    $summary = [
        'currency_code' => 'AED',
        'currency_codes' => ['AED'],
        'items_total' => 100,
        'delivery_charges' => 12.5,
        'handling_charges' => 3,
        'promo_discount' => 5,
        'wallet_balance' => 40,
        'wallet_amount_used' => 10,
        'payable_amount' => 100.5,
    ];

    $payload = (new PaymentSummaryResource($summary))->toArray(request());

    expect($payload['items_total'])->toBe(100)
        ->and($payload['payable_amount'])->toBe(100.5)
        ->and($payload['formatted_items_total'])->toBe('د.إ 100.00')
        ->and($payload['formatted_delivery_charges'])->toBe('د.إ 12.50')
        ->and($payload['formatted_promo_discount'])->toBe('د.إ 5.00')
        ->and($payload['formatted_wallet_amount_used'])->toBe('د.إ 10.00')
        ->and($payload['formatted_payable_amount'])->toBe('د.إ 100.50');
});

it('renders COD payment page amounts with order currency', function () {
    $order = (object) [
        'slug' => 'ORD-1',
        'currency_code' => 'AED',
        'base_currency_code' => 'AED',
        'payment_status' => 'pending',
        'is_cod_paid_online' => false,
        'total_payable' => 123.45,
        'subtotal' => 100,
        'delivery_charge' => 10,
        'handling_charges' => 3,
        'promo_discount' => 0,
        'items' => collect(),
    ];

    $html = view('cod.payment-page', [
        'order' => $order,
        'gateways' => [],
        'verifyUrl' => '/verify',
        'statusUrl' => '/status',
        'initUrlT' => '/init/__GW__',
    ])->render();

    expect($html)
        ->toContain('د.إ 123.45')
        ->toContain('د.إ 100.00')
        ->not->toContain('$123.45');
});

it('renders generic payment status amount with transaction currency before system currency', function () {
    $transaction = (object) [
        'id' => 44,
        'amount' => 50,
        'gateway_amount' => 50,
        'gateway_currency_code' => 'AED',
        'payment_status' => 'completed',
        'transaction_id' => 'txn_test',
    ];

    $html = view('payments.payment-status', [
        'transaction' => $transaction,
        'paymentContext' => [
            'title' => 'Payment Summary',
            'lineItems' => [],
            'backUrl' => '#',
            'backLabel' => 'Back',
        ],
        'systemSettings' => [
            'currency' => 'USD',
            'currencySymbol' => '$',
            'demoMode' => false,
            'logo' => null,
            'appName' => 'Hyperlocal',
        ],
        'completedStatus' => 'completed',
        'failedStatus' => 'failed',
    ])->render();

    expect($html)
        ->toContain('د.إ 50.00')
        ->not->toContain('50.00 USD');
});
