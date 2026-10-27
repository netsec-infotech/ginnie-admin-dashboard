<?php

use App\Http\Controllers\OrderController;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createOrderInsightScopeOrder(User $user, DeliveryZone $deliveryZone, array $overrides = []): Order
{
    return Order::forceCreate(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'slug' => 'order-insight-'.Str::uuid(),
        'delivery_zone_id' => $deliveryZone->id,
        'email' => $user->email,
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
        'wallet_balance' => 0,
        'subtotal' => 100,
        'total_payable' => 100,
        'final_total' => 100,
        'status' => 'pending',
        'billing_name' => $user->name,
        'billing_address_1' => 'Test street',
        'billing_landmark' => 'Test landmark',
        'billing_zip' => '00000',
        'billing_phone' => '+10000000000',
        'billing_address_type' => 'home',
        'billing_latitude' => 0,
        'billing_longitude' => 0,
        'billing_city' => 'City',
        'billing_state' => 'State',
        'billing_country' => 'Country',
        'billing_country_code' => 'US',
        'shipping_name' => $user->name,
        'shipping_address_1' => 'Test street',
        'shipping_landmark' => 'Test landmark',
        'shipping_zip' => '00000',
        'shipping_phone' => '+10000000000',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 0,
        'shipping_longitude' => 0,
        'shipping_city' => 'City',
        'shipping_state' => 'State',
        'shipping_country' => 'Country',
        'shipping_country_code' => 'US',
    ], $overrides));
}

it('defines the order insight status groups in one server-side mapping', function () {
    $controller = app(OrderController::class);
    $method = new ReflectionMethod($controller, 'orderInsightStatusGroups');
    $method->setAccessible(true);

    expect($method->invoke($controller))
        ->toEqual([
            'preparing' => ['preparing', 'accepted_by_seller', 'out_for_delivery', 'ready_for_pickup'],
            'delivered' => ['delivered'],
            'rejected' => ['rejected', 'cancelled', 'failed'],
        ]);
});

it('keeps existing filters grouped with the regular order schedule scope', function () {
    $user = User::factory()->create();
    $deliveryZone = DeliveryZone::factory()->create();

    $matchingOrder = createOrderInsightScopeOrder($user, $deliveryZone);
    $differentPaymentPastOrder = createOrderInsightScopeOrder($user, $deliveryZone, [
        'payment_method' => 'prepaid',
        'scheduled_date' => now()->subDay()->toDateString(),
    ]);
    $futureScheduledOrder = createOrderInsightScopeOrder($user, $deliveryZone, [
        'scheduled_date' => now()->addDay()->toDateString(),
    ]);

    $query = Order::query()->where('payment_method', 'cod');
    $controller = app(OrderController::class);
    $method = new ReflectionMethod($controller, 'applyRegularOrderScope');
    $method->setAccessible(true);
    $method->invoke($controller, $query);

    expect($query->pluck('id')->all())
        ->toContain($matchingOrder->id)
        ->not->toContain($differentPaymentPastOrder->id)
        ->not->toContain($futureScheduledOrder->id);
});

it('keeps card links and browser history synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/orders/index.blade.php'));
    $sellerView = file_get_contents(resource_path('views/seller/orders/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/order.js'));

    expect($adminView)
        ->toContain("'status_group' => 'preparing'")
        ->toContain('order-insight-card-link')
        ->and($sellerView)
        ->toContain("'status_group' => 'preparing'")
        ->toContain('order-insight-card-link')
        ->and($script)
        ->toContain("url.searchParams.set('status_group', statusGroup)")
        ->toContain("window.addEventListener('popstate'")
        ->toContain("data.status_group = activeStatusGroup || ''");
});
