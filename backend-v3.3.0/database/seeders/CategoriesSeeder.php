<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        foreach (range(11, 20) as $index) {
            DB::table('categories')->insert([
                'id' => $index,
                'uuid' => (string) Str::uuid(),
                'parent_id' => null,
                'title' => $faker->words(3, true),
                'slug' => Str::slug($faker->words(3, true)),
                'image' => $faker->imageUrl(300, 300, 'cats', true, 'Faker'),
                'description' => $faker->paragraph,
                'status' => $faker->randomElement(['active', 'inactive']),
                'requires_approval' => $faker->boolean,
                'metadata' => json_encode([
                    'seo_title' => $faker->sentence,
                    'seo_keywords' => $faker->words(5, true),
                    'seo_description' => $faker->paragraph,
                ]),
                'deleted_at' => $faker->optional()->dateTimeBetween('-1 years', 'now'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
