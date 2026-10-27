<?php

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\CurrencyMetadata;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\Currency\CurrencyGroupedTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([['AED', 'د.إ'], ['USD', '$'], ['INR', '₹']] as [$code, $symbol]) {
        CurrencyMetadata::query()->create([
            'code' => $code,
            'name' => $code,
            'symbol' => $symbol,
            'decimal_places' => 2,
            'minor_unit_factor' => 100,
            'is_enabled' => true,
        ]);
    }
});

function phase15Order(array $attributes = []): Order
{
    $user = $attributes['user'] ?? User::factory()->create();
    unset($attributes['user']);

    return Order::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'slug' => 'phase15-order-'.Str::random(10),
        'email' => 'phase15@example.test',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'AED',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'completed',
        'delivery_zone_id' => DeliveryZone::factory()->create()->id,
        'wallet_balance' => 0,
        'subtotal' => 100,
        'total_payable' => 100,
        'final_total' => 100,
        'status' => OrderStatusEnum::DELIVERED(),
        'billing_name' => 'Phase 15 Customer',
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
        'shipping_name' => 'Phase 15 Customer',
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

it('groups mixed reporting totals by currency and keeps unknown amounts under review', function () {
    $totals = app(CurrencyGroupedTotals::class)->fromItems([
        (object) ['amount' => 100, 'currency' => 'AED'],
        (object) ['amount' => 25, 'currency' => 'usd'],
        (object) ['amount' => 10, 'currency' => 'BADCODE'],
    ], fn ($row) => $row->amount, fn ($row) => $row->currency);

    expect($totals)->toHaveCount(3)
        ->and($totals[0]['formatted_total'])->toBe('د.إ 100.00')
        ->and($totals[1]['formatted_total'])->toBe('$ 25.00')
        ->and($totals[2]['currency_code'])->toBe(CurrencyGroupedTotals::UNKNOWN_CURRENCY)
        ->and($totals[2]['formatted_total'])->toContain('Needs review');
});

it('adds grouped currency totals to admin revenue reports without changing legacy totals', function () {
    phase15Order(['currency_code' => 'AED', 'total_payable' => 100, 'final_total' => 100]);
    phase15Order(['currency_code' => 'USD', 'total_payable' => 25, 'final_total' => 25]);

    $report = app(DashboardService::class)->getRevenueData(1);

    expect((float) $report['total'])->toBe(125.0)
        ->and($report['formatted_total'])->not->toBeNull()
        ->and($report['currency_totals'])->toHaveCount(2)
        ->and($report['formatted_currency_totals'])->toContain('د.إ 100.00')
        ->and($report['formatted_currency_totals'])->toContain('$ 25.00')
        ->and($report['has_multiple_currencies'])->toBeTrue();
});

it('groups seller settlement dashboard amounts by statement currency', function () {
    $seller = Seller::factory()->create();

    SellerStatement::query()->create([
        'seller_id' => $seller->id,
        'entry_type' => SellerSettlementTypeEnum::CREDIT(),
        'amount' => 40,
        'currency_code' => 'AED',
        'posted_at' => now(),
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
    ]);

    SellerStatement::query()->create([
        'seller_id' => $seller->id,
        'entry_type' => SellerSettlementTypeEnum::CREDIT(),
        'amount' => 60,
        'currency_code' => 'USD',
        'posted_at' => now(),
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
    ]);

    $report = app(DashboardService::class)->getSellerSettlementsData(1);

    expect($report['pending_currency_totals'])->toHaveCount(2)
        ->and($report['pending_amount_display'])->toContain('AED: د.إ 40.00')
        ->and($report['pending_amount_display'])->toContain('USD: $ 60.00');
});
