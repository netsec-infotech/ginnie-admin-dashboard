<?php

namespace Database\Factories;

use App\Enums\Blog\BlogStatusEnum;
use App\Models\Blog;
use App\Models\WatchBuyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Blog>
 */
class BlogFactory extends Factory
{
    protected $model = Blog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'author_profile_id' => WatchBuyProfile::factory(),
            'category_ids' => [],
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'short_description' => fake()->paragraph(2),
            'content' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'product_ids' => [],
            'tags' => ['groceries', 'fresh'],
            'status' => BlogStatusEnum::PUBLISHED,
            'is_editors_pick' => false,
            'published_at' => now(),
            'views_count' => fake()->numberBetween(0, 500),
            'metadata' => [
                'seo_title' => $title,
                'seo_description' => fake()->sentence(10),
                'seo_keywords' => ['groceries', 'fresh', 'recipes'],
            ],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogStatusEnum::DRAFT,
            'published_at' => null,
        ]);
    }

    public function editorsPick(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_editors_pick' => true,
        ]);
    }
}
