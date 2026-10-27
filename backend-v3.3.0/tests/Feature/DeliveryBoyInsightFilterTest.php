<?php

use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Http\Controllers\DeliveryBoyController;
use App\Models\DeliveryBoy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters delivery boys by insight status groups properly in controller', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    $verifiedBoy = DeliveryBoy::factory()->create([
        'user_id' => $user1->id,
        'verification_status' => DeliveryBoyVerificationStatusEnum::VERIFIED(),
        'status' => 'inactive',
    ]);

    $activeBoy = DeliveryBoy::factory()->create([
        'user_id' => $user2->id,
        'verification_status' => DeliveryBoyVerificationStatusEnum::REJECTED(),
        'status' => 'active',
    ]);

    $pendingBoy = DeliveryBoy::factory()->create([
        'user_id' => $user3->id,
        'verification_status' => DeliveryBoyVerificationStatusEnum::PENDING(),
        'status' => 'inactive',
    ]);

    $verifiedQuery = DeliveryBoyController::applyInsightStatusGroup(DeliveryBoy::query(), 'verified');
    expect($verifiedQuery->pluck('id')->all())
        ->toContain($verifiedBoy->id)
        ->not->toContain($activeBoy->id)
        ->not->toContain($pendingBoy->id);

    $activeQuery = DeliveryBoyController::applyInsightStatusGroup(DeliveryBoy::query(), 'active');
    expect($activeQuery->pluck('id')->all())
        ->toContain($activeBoy->id)
        ->not->toContain($verifiedBoy->id)
        ->not->toContain($pendingBoy->id);

    $pendingQuery = DeliveryBoyController::applyInsightStatusGroup(DeliveryBoy::query(), 'pending');
    expect($pendingQuery->pluck('id')->all())
        ->toContain($pendingBoy->id)
        ->not->toContain($verifiedBoy->id)
        ->not->toContain($activeBoy->id);
});

it('keeps delivery boy card links and frontend scripts synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/delivery_boys/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/delivery-boy.js'));

    expect($adminView)
        ->toContain("'status_group' => 'verified'")
        ->toContain("'status_group' => 'active'")
        ->toContain("'status_group' => 'pending'")
        ->toContain('delivery-boy-insight-card-link')
        ->and($script)
        ->toContain("validStatusGroups")
        ->toContain("data.status_group = activeStatusGroup")
        ->toContain("window.addEventListener('popstate'");
});
