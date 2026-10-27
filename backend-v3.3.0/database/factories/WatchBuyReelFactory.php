<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use Illuminate\Database\Eloquent\Factories\Factory;

class WatchBuyReelFactory extends Factory
{
    protected $model = WatchBuyReel::class;

    public function definition(): array
    {
        return [
            'profile_id' => WatchBuyProfile::factory(),
            'source' => 'seller',
            'caption' => fake()->sentence(),
            'status' => 'published',
            'duration_ms' => 15_000,
            'video_width' => 1080,
            'video_height' => 1920,
            'video_size_bytes' => 1_024,
            'published_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
