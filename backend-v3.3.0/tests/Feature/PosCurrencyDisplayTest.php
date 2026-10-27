<?php

use App\Enums\Pos\PosPaymentSessionStatusEnum;
use App\Models\CurrencyMetadata;
use App\Models\PosPaymentSession;
use App\Support\Currency\CurrencyDisplay;
use App\Support\Currency\CurrencySnapshotPayload;
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
});

it('formats the public POS payment page with the session currency', function () {
    $session = new PosPaymentSession([
        'amount' => 123.45,
        'currency_code' => 'AED',
        'status' => PosPaymentSessionStatusEnum::PENDING,
    ]);

    $html = view('pos.payment-page', [
        'session' => $session,
        'store' => (object) ['name' => 'Dubai Counter'],
        'systemSettings' => ['favicon' => null],
        'lineItems' => [],
        'breakdown' => [
            'subtotalExTax' => 100,
            'taxByRate' => [5 => 5],
            'tax' => 5,
            'savings' => 10,
            'discount' => 2,
        ],
        'gateways' => [],
        'verifyUrl' => '/verify',
        'statusUrl' => '/status',
        'initUrlT' => '/init/__GW__',
    ])->render();

    expect($html)
        ->toContain('د.إ 123.45')
        ->toContain('د.إ 100.00')
        ->not->toContain('₹123.45');
});

it('passes currency code through to the customer-facing POS display', function () {
    $script = file_get_contents(public_path('assets/js/seller-pos.js'));

    expect($script)
        ->toContain('currency_code: selectedStoreCurrencyCode()')
        ->toContain('currency_symbol: currentCurrencyLabel()')
        ->not->toContain("|| '$'");
});

it('keeps POS display formatting tied to the selected store currency', function () {
    $script = file_get_contents(public_path('assets/js/seller-pos.js'));

    expect($script)
        ->toContain('function selectedStoreCurrencyCode()')
        ->toContain('function currentCurrencyLabel()')
        ->toContain("storeOpt?.dataset.currency")
        ->toContain("storeOpt?.dataset.currencySymbol");
});

it('uses store currency labels for product pricing inputs', function () {
    $controller = file_get_contents(app_path('Http/Controllers/StoreController.php'));
    $script = file_get_contents(public_path('assets/js/product.js'));

    expect($controller)
        ->toContain("'currency_code' => \$currencyCode")
        ->toContain("'currency_symbol' => CurrencyDisplay::label(\$currencyCode)")
        ->and($script)
        ->toContain('function storeCurrencySymbol(store)')
        ->toContain('<span class="input-group-text">${storeCurrency}</span>');
});

it('formats phase ten operational displays with record currency labels', function () {
    expect(CurrencyDisplay::format(88.5, 'AED'))
        ->toBe('د.إ 88.50')
        ->and(CurrencyDisplay::label('AED'))
        ->toBe('د.إ');
});

it('formats amounts with symbols only and preserves ISO decimal precision', function () {
    CurrencyMetadata::insert([
        [
            'code' => 'INR',
            'name' => 'Indian Rupee',
            'symbol' => '₹',
            'decimal_places' => 2,
            'minor_unit_factor' => 100,
            'is_enabled' => true,
        ],
        [
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 0,
            'minor_unit_factor' => 1,
            'is_enabled' => true,
        ],
        [
            'code' => 'KWD',
            'name' => 'Kuwaiti Dinar',
            'symbol' => 'د.ك',
            'decimal_places' => 3,
            'minor_unit_factor' => 1000,
            'is_enabled' => true,
        ],
    ]);

    expect(CurrencySnapshotPayload::formatAmount(75, 'INR'))->toBe('₹ 75.00')
        ->and(CurrencySnapshotPayload::formatAmount(75, 'JPY'))->toBe('¥ 75')
        ->and(CurrencySnapshotPayload::formatAmount(75, 'KWD'))->toBe('د.ك 75.000')
        ->and(CurrencySnapshotPayload::formatAmount(75, 'CHF'))->toBe('CHF 75.00')
        ->and(CurrencySnapshotPayload::formatAmount(75, 'INVALID'))->toBeNull();
});

it('uses the default store currency for seller POS first paint', function () {
    $view = file_get_contents(resource_path('views/seller/pos/index.blade.php'));

    expect($view)
        ->toContain('$posZero')
        ->toContain('$posCurrencyLabel')
        ->toContain('currencySymbol:        @json($posCurrencyLabel)')
        ->not->toContain("currencySymbol:        @json(\$systemSettings['currencySymbol'])");
});
