<?php

use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters subscribers by insight status groups properly in controller', function () {
    $user1 = User::factory()->create();
    $seller1 = Seller::factory()->create(['user_id' => $user1->id]);
    $plan1 = SubscriptionPlan::create([
        'name' => 'Plan Pro',
        'price' => 100,
        'duration' => 30,
        'status' => 'active',
    ]);

    $activeSub = SellerSubscription::create([
        'seller_id' => $seller1->id,
        'plan_id' => $plan1->id,
        'price_paid' => 100,
        'status' => SellerSubscriptionStatusEnum::ACTIVE(),
        'start_date' => now(),
        'end_date' => now()->addDays(30),
    ]);

    $cancelledSub = SellerSubscription::create([
        'seller_id' => $seller1->id,
        'plan_id' => $plan1->id,
        'price_paid' => 100,
        'status' => SellerSubscriptionStatusEnum::CANCELLED(),
        'start_date' => now(),
        'end_date' => now()->addDays(30),
    ]);

    $pendingSub = SellerSubscription::create([
        'seller_id' => $seller1->id,
        'plan_id' => $plan1->id,
        'price_paid' => 100,
        'status' => SellerSubscriptionStatusEnum::PENDING(),
        'start_date' => now(),
        'end_date' => now()->addDays(30),
    ]);

    $activeQuery = SubscriptionPlanController::applyInsightStatusGroup(SellerSubscription::query(), 'active');
    expect($activeQuery->pluck('id')->all())
        ->toContain($activeSub->id)
        ->not->toContain($cancelledSub->id)
        ->not->toContain($pendingSub->id);

    $cancelledQuery = SubscriptionPlanController::applyInsightStatusGroup(SellerSubscription::query(), 'cancelled');
    expect($cancelledQuery->pluck('id')->all())
        ->toContain($cancelledSub->id)
        ->not->toContain($activeSub->id)
        ->not->toContain($pendingSub->id);

    $pendingQuery = SubscriptionPlanController::applyInsightStatusGroup(SellerSubscription::query(), 'pending');
    expect($pendingQuery->pluck('id')->all())
        ->toContain($pendingSub->id)
        ->not->toContain($activeSub->id)
        ->not->toContain($cancelledSub->id);
});

it('keeps subscriber card links and frontend scripts synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/subscription-plans/subscribers.blade.php'));
    $script = file_get_contents(public_path('assets/js/subscribers.blade.php.js'));

    expect($adminView)
        ->toContain("'status_group' => 'active'")
        ->toContain("'status_group' => 'cancelled'")
        ->toContain("'status_group' => 'pending'")
        ->toContain('subscriber-insight-card-link')
        ->and($script)
        ->toContain("validStatusGroups")
        ->toContain("data.status_group = activeStatusGroup")
        ->toContain("window.addEventListener('popstate'");
});
