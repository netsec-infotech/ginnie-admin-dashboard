<?php

use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\ProductController;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters products by insight status groups properly in controller', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);

    $approvedProduct = Product::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => ProductVarificationStatusEnum::APPROVED(),
    ]);

    $pendingProduct = Product::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => ProductVarificationStatusEnum::PENDING(),
    ]);

    $rejectedProduct = Product::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => ProductVarificationStatusEnum::REJECTED(),
    ]);

    $approvedQuery = ProductController::applyInsightStatusGroup(Product::query(), 'approved');
    expect($approvedQuery->pluck('id')->all())->toContain($approvedProduct->id)
        ->not->toContain($pendingProduct->id)
        ->not->toContain($rejectedProduct->id);

    $pendingQuery = ProductController::applyInsightStatusGroup(Product::query(), 'pending');
    expect($pendingQuery->pluck('id')->all())->toContain($pendingProduct->id)
        ->not->toContain($approvedProduct->id)
        ->not->toContain($rejectedProduct->id);

    $rejectedQuery = ProductController::applyInsightStatusGroup(Product::query(), 'rejected');
    expect($rejectedQuery->pluck('id')->all())->toContain($rejectedProduct->id)
        ->not->toContain($approvedProduct->id)
        ->not->toContain($pendingProduct->id);
});

it('keeps product card links and frontend scripts synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/products/index.blade.php'));
    $sellerView = file_get_contents(resource_path('views/seller/products/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/product.js'));

    expect($adminView)
        ->toContain("'status_group' => 'approved'")
        ->toContain("'status_group' => 'pending'")
        ->toContain("'status_group' => 'rejected'")
        ->toContain('product-insight-card-link')
        ->and($sellerView)
        ->toContain("'status_group' => 'approved'")
        ->toContain("'status_group' => 'pending'")
        ->toContain("'status_group' => 'rejected'")
        ->toContain('product-insight-card-link')
        ->and($script)
        ->toContain("validStatusGroups")
        ->toContain("data.status_group = activeStatusGroup")
        ->toContain("window.addEventListener('popstate'");
});
