<?php

use App\Helpers\SidebarBadgeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sidebar badge helper returns array structure for admin panel', function () {
    $counts = SidebarBadgeHelper::getBadgeCounts('admin');

    expect($counts)->toBeArray()
        ->toHaveKey('orders')
        ->toHaveKey('scheduled_orders')
        ->toHaveKey('return_requests')
        ->toHaveKey('return_disputes')
        ->toHaveKey('stores')
        ->toHaveKey('products')
        ->toHaveKey('ad_campaigns')
        ->toHaveKey('sellers')
        ->toHaveKey('delivery_boys')
        ->toHaveKey('settlements')
        ->toHaveKey('withdrawals');
});

test('sidebar badge helper returns array structure for seller panel', function () {
    $counts = SidebarBadgeHelper::getBadgeCounts('seller');

    expect($counts)->toBeArray();
});
