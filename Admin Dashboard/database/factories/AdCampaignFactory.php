<?php

namespace Database\Factories;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdPlacementEnum;
use App\Models\AdCampaign;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    public function definition(): array
    {
        return [
            'seller_id'         => Seller::factory(),
            'product_id'        => Product::factory(),
            'budget'            => $this->faker->randomFloat(2, 50, 5000),
            'spent'             => 0.00,
            'cpc_rate_snapshot' => $this->faker->randomFloat(4, 0.50, 5.00),
            'placements'        => AdPlacementEnum::all(),
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => ['status' => AdCampaignStatusEnum::RUNNING()]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => AdCampaignStatusEnum::PENDING_APPROVAL()]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => AdCampaignStatusEnum::COMPLETED(),
            'spent'  => $attrs['budget'] ?? 100.00,
        ]);
    }
}
