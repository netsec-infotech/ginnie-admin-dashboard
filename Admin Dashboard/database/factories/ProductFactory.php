<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = \App\Models\Category::first();
        if (!$category) {
            $category = new \App\Models\Category();
            $category->uuid = $this->faker->uuid();
            $category->title = 'Test Category ' . uniqid();
            $category->slug = 'test-cat-' . uniqid();
            $category->status = 'active';
            $category->save();
        }

        $condition = \App\Models\ProductCondition::first();
        if (!$condition) {
            $condition = new \App\Models\ProductCondition();
            $condition->uuid = $this->faker->uuid();
            $condition->category_id = $category->id;
            $condition->title = 'New ' . uniqid();
            $condition->slug = 'new-' . uniqid();
            $condition->alignment = 'strip';
            $condition->save();
        }

        return [
            'uuid' => $this->faker->uuid(),
            'seller_id' => \App\Models\Seller::factory(),
            'category_id' => $category->id,
            'product_condition_id' => $condition->id,
            'title' => $this->faker->words(3, true),
            'slug' => $this->faker->slug() . '-' . uniqid(),
            'type' => 'physical',
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'tags' => json_encode(['test', 'product']),
            'metadata' => json_encode([]),
            'base_prep_time' => 15,
            'status' => \App\Enums\Product\ProductStatusEnum::ACTIVE(),
        ];
    }
}
