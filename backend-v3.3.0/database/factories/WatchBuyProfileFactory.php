<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\User;
use App\Models\WatchBuyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class WatchBuyProfileFactory extends Factory
{
    protected $model = WatchBuyProfile::class;

    public function definition(): array
    {
        return [
            'profile_type' => 'seller',
            'seller_id' => Seller::factory(),
            'username' => fake()->unique()->lexify('profile????????????'),
            'bio' => fake()->text(120),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }

    public function platform(): static
    {
        return $this->state(fn (): array => [
            'profile_type' => 'platform',
            'seller_id' => null,
        ]);
    }
}
