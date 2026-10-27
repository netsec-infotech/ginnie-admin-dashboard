<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class WatchBuyStatusFactory extends Factory
{
    protected $model = WatchBuyStatus::class;

    public function definition(): array
    {
        return [
            'profile_id' => WatchBuyProfile::factory(),
            'source' => 'seller',
            'content_type' => 'text',
            'text' => fake()->sentence(),
            'created_by' => User::factory(),
            'expires_at' => now()->addDay(),
        ];
    }
}
