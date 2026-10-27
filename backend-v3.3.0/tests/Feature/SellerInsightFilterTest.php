<?php

use App\Http\Controllers\SellerController;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters sellers by insight status groups properly in controller', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $approvedSeller = Seller::factory()->create([
        'user_id' => $user1->id,
        'verification_status' => 'approved',
    ]);

    $notApprovedSeller = Seller::factory()->create([
        'user_id' => $user2->id,
        'verification_status' => 'not_approved',
    ]);

    $approvedQuery = SellerController::applyInsightStatusGroup(Seller::query(), 'approved');
    expect($approvedQuery->pluck('id')->all())->toContain($approvedSeller->id)
        ->not->toContain($notApprovedSeller->id);

    $notApprovedQuery = SellerController::applyInsightStatusGroup(Seller::query(), 'not_approved');
    expect($notApprovedQuery->pluck('id')->all())->toContain($notApprovedSeller->id)
        ->not->toContain($approvedSeller->id);
});

it('keeps seller card links and frontend scripts synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/sellers/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/custom.js'));

    expect($adminView)
        ->toContain("'status_group' => 'approved'")
        ->toContain("'status_group' => 'not_approved'")
        ->toContain('seller-insight-card-link')
        ->and($script)
        ->toContain("validSellerStatusGroups")
        ->toContain("data.status_group = activeSellerStatusGroup")
        ->toContain("window.addEventListener('popstate'");
});
