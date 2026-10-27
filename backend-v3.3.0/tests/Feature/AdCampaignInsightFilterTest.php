<?php

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Http\Controllers\Admin\AdCampaignController as AdminAdCampaignController;
use App\Http\Controllers\Seller\AdCampaignController as SellerAdCampaignController;
use App\Models\AdCampaign;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters ad campaigns by insight status groups properly in admin controller', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['seller_id' => $seller->id]);

    $running = AdCampaign::create([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'budget' => 100,
        'cpc_rate_snapshot' => 1,
        'status' => AdCampaignStatusEnum::RUNNING(),
    ]);

    $pending = AdCampaign::create([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'budget' => 100,
        'cpc_rate_snapshot' => 1,
        'status' => AdCampaignStatusEnum::PENDING_APPROVAL(),
    ]);

    $rejected = AdCampaign::create([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'budget' => 100,
        'cpc_rate_snapshot' => 1,
        'status' => AdCampaignStatusEnum::REJECTED(),
    ]);

    $runningQuery = AdminAdCampaignController::applyInsightStatusGroup(AdCampaign::query(), 'running');
    expect($runningQuery->pluck('id')->all())
        ->toContain($running->id)
        ->not->toContain($pending->id)
        ->not->toContain($rejected->id);

    $pendingQuery = AdminAdCampaignController::applyInsightStatusGroup(AdCampaign::query(), 'pending');
    expect($pendingQuery->pluck('id')->all())
        ->toContain($pending->id)
        ->not->toContain($running->id)
        ->not->toContain($rejected->id);

    $rejectedQuery = AdminAdCampaignController::applyInsightStatusGroup(AdCampaign::query(), 'rejected');
    expect($rejectedQuery->pluck('id')->all())
        ->toContain($rejected->id)
        ->not->toContain($running->id)
        ->not->toContain($pending->id);
});

it('filters ad campaigns by insight status groups properly in seller controller', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['seller_id' => $seller->id]);

    $forceStopped = AdCampaign::create([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'budget' => 100,
        'cpc_rate_snapshot' => 1,
        'status' => AdCampaignStatusEnum::FORCE_STOPPED(),
    ]);

    $forceStoppedQuery = SellerAdCampaignController::applyInsightStatusGroup(AdCampaign::query(), 'force_stopped');
    expect($forceStoppedQuery->pluck('id')->all())
        ->toContain($forceStopped->id);
});

it('keeps ad campaign card links and frontend scripts synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/ads/campaigns/index.blade.php'));
    $sellerView = file_get_contents(resource_path('views/seller/ads/campaigns/index.blade.php'));
    $adminScript = file_get_contents(public_path('assets/js/admin-ad-campaigns.js'));
    $sellerScript = file_get_contents(public_path('assets/js/ad-campaigns.js'));

    expect($adminView)
        ->toContain("'status_group' => 'running'")
        ->toContain("'status_group' => 'pending'")
        ->toContain("'status_group' => 'rejected'")
        ->toContain('campaign-insight-card-link')
        ->and($sellerView)
        ->toContain("route('seller.ads.campaigns.index')")
        ->toContain("'status_group' => 'running'")
        ->toContain("'status_group' => 'force_stopped'")
        ->toContain('campaign-insight-card-link')
        ->and($adminScript)
        ->toContain("validStatusGroups")
        ->toContain("data.status_group = activeStatusGroup")
        ->toContain("window.addEventListener('popstate'")
        ->and($sellerScript)
        ->toContain("validStatusGroups")
        ->toContain("data.status_group = activeStatusGroup")
        ->toContain("window.addEventListener('popstate'");
});
