<?php

use App\Services\AdServingService;
use App\Services\SettingService;

beforeEach(function () {
    $this->settingService = Mockery::mock(SettingService::class);
    $this->service = new AdServingService($this->settingService);
});

describe('findCampaignsForProducts', function () {
    it('returns running campaigns keyed by product_id', function () {
        $settingResource = Mockery::mock(\Illuminate\Http\Resources\Json\JsonResource::class);
        $settingResource->shouldReceive('toArray')->andReturn([
            'value' => ['featureEnabled' => true],
        ]);
        $this->settingService->shouldReceive('getSettingByVariable')->andReturn($settingResource);

        $seller = \App\Models\Seller::factory()->create();
        $product1 = \App\Models\Product::factory()->create(['seller_id' => $seller->id]);
        $product2 = \App\Models\Product::factory()->create(['seller_id' => $seller->id]);
        $product3 = \App\Models\Product::factory()->create(['seller_id' => $seller->id]);

        $campaign1 = \App\Models\AdCampaign::factory()->running()->create([
            'seller_id'  => $seller->id,
            'product_id' => $product1->id,
            'budget'     => 500,
            'spent'      => 100,
            'placements' => [\App\Enums\Advertisement\AdPlacementEnum::SEARCH()],
        ]);

        // Completed campaign — should not be returned
        \App\Models\AdCampaign::factory()->completed()->create([
            'seller_id'  => $seller->id,
            'product_id' => $product2->id,
            'placements' => [\App\Enums\Advertisement\AdPlacementEnum::SEARCH()],
        ]);

        $result = $this->service->findCampaignsForProducts(
            [$product1->id, $product2->id, $product3->id],
            \App\Enums\Advertisement\AdPlacementEnum::SEARCH(),
        );

        expect($result)->toHaveCount(1)
            ->and($result->has($product1->id))->toBeTrue()
            ->and($result->get($product1->id)['campaign_id'])->toBe($campaign1->id);
    });

    it('returns empty when feature is disabled', function () {
        $settingResource = Mockery::mock(\Illuminate\Http\Resources\Json\JsonResource::class);
        $settingResource->shouldReceive('toArray')->andReturn([
            'value' => ['featureEnabled' => false],
        ]);
        $this->settingService->shouldReceive('getSettingByVariable')->andReturn($settingResource);

        $result = $this->service->findCampaignsForProducts([1, 2], \App\Enums\Advertisement\AdPlacementEnum::SEARCH());
        expect($result)->toBeEmpty();
    });

    it('returns empty for empty product ids', function () {
        $result = $this->service->findCampaignsForProducts([], \App\Enums\Advertisement\AdPlacementEnum::SEARCH());
        expect($result)->toBeEmpty();
    });
});
