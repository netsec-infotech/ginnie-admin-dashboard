<?php

namespace Database\Factories;

use App\Models\SharedCart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedCart>
 */
class SharedCartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => SharedCart::hashToken(SharedCart::generateToken()),
            'user_id' => User::factory(),
            'title' => fake()->optional()->sentence(3),
            'cart_data' => [],
            'expires_at' => now()->addDays(7),
        ];
    }
}
