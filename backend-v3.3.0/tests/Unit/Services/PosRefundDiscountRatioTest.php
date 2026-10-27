<?php

use App\Models\Order;
use App\Services\PosRefundService;

/**
 * The refund amount must reflect what the customer actually paid after any
 * cashier discount / promo, not the original (pre-discount) line subtotal.
 *
 * Discount/promo are applied at the order level (order.subtotal vs
 * order.promo_discount); the per-line refund is the gross OrderItem.subtotal
 * scaled by this ratio.
 */
function ratioFor(float $subtotal, float $promoDiscount): float
{
    $order = new Order;
    $order->subtotal = $subtotal;
    $order->promo_discount = $promoDiscount;

    $method = (new ReflectionClass(PosRefundService::class))->getMethod('discountRatio');
    $method->setAccessible(true);

    return $method->invoke(app(PosRefundService::class), $order);
}

it('returns full ratio when no discount or promo applied', function () {
    expect(ratioFor(1000.0, 0.0))->toBe(1.0);
});

it('scales the ratio by the order-level discount', function () {
    // 200 off a 1000 subtotal => customer paid 80% of gross.
    expect(ratioFor(1000.0, 200.0))->toBe(0.8);
});

it('returns zero ratio when the whole sale was discounted away', function () {
    expect(ratioFor(500.0, 500.0))->toBe(0.0);
});

it('floors the ratio at zero when discount somehow exceeds subtotal', function () {
    // Nothing was paid for goods, so nothing is refundable on the lines.
    expect(ratioFor(500.0, 600.0))->toBe(0.0);
});

it('falls back to a full ratio on a zero subtotal to avoid division by zero', function () {
    expect(ratioFor(0.0, 0.0))->toBe(1.0);
});

it('refund per-unit reflects the discounted price for a discounted sale', function () {
    // Sale: subtotal 1000 (qty 4 @ 250 gross), 200 discount.
    // Customer effectively paid 800 => 200/unit, not 250.
    $ratio = ratioFor(1000.0, 200.0);
    $grossLineSubtotal = 1000.0;
    $qty = 4;

    $perUnit = round(($grossLineSubtotal * $ratio) / $qty, 2);

    expect($perUnit)->toBe(200.0);
});
