<?php

namespace Database\Factories;

use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'country' => fake()->country(),
            'iso_2' => Str::upper(fake()->lexify('??')),
            'mobile' => fake()->unique()->numerify('##########'),
            'country_code' => '+91',
            'referral_code' => Str::upper(Str::random(8)),
            'friends_code' => Str::upper(Str::random(8)),
            'reward_points' => 0,
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
            'mobile_verified_at' => null,
        ]);
    }
}
