<?php

use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('featured_sections') || !Schema::hasTable('home_layouts')) {
            return;
        }

        DB::transaction(function () {
            $legacySections = FeaturedSection::with(['categories', 'products', 'zones', 'media'])
                ->orderBy('sort_order')
                ->get();

            foreach ($legacySections as $fs) {
                $layout = HomeLayout::firstOrCreate([
                    'platform'   => 'app',
                    'scope_type' => $fs->scope_type ?: 'global',
                    'scope_id'   => ($fs->scope_type === 'category' && $fs->scope_id) ? $fs->scope_id : null,
                ], [
                    'is_active'  => true,
                ]);

                $source = match ($fs->section_type) {
                    'custom_products' => 'custom',
                    default           => $fs->section_type ?: 'newly_added',
                };

                $section = $layout->sections()->create([
                    'type'       => 'products',
                    'title'      => $fs->title,
                    'style'      => $fs->style ?: 'horizontal_carousel',
                    'sort_order' => $fs->sort_order ?: 0,
                    'is_active'  => $fs->status === 'active' || $fs->status === '1' || $fs->status === true,
                    'config'     => [
                        'source'           => $source,
                        'limit'            => 12,
                        'background_type'  => $fs->background_type ?: 'color',
                        'background_color' => $fs->background_color ?: '#ffffff',
                        'text_color'       => $fs->text_color ?: '#000000',
                    ],
                ]);

                if ($fs->zones && $fs->zones->isNotEmpty()) {
                    $section->zones()->sync($fs->zones->pluck('id')->toArray());
                }

                if ($source === 'custom' && $fs->products) {
                    foreach ($fs->products as $index => $product) {
                        $section->items()->create([
                            'purpose'       => 'display',
                            'itemable_type' => Product::class,
                            'itemable_id'   => $product->id,
                            'sort_order'    => $index,
                        ]);
                    }
                } elseif ($fs->categories && $fs->categories->isNotEmpty()) {
                    foreach ($fs->categories as $index => $category) {
                        $section->items()->create([
                            'purpose'       => 'filter',
                            'itemable_type' => Category::class,
                            'itemable_id'   => $category->id,
                            'sort_order'    => $index,
                        ]);
                    }
                }

                $bgMedia = $fs->getFirstMedia(SpatieMediaCollectionName::FEATURED_SECTION_BACKGROUND_IMAGE());
                if ($bgMedia && file_exists($bgMedia->getPath())) {
                    try {
                        $section->addMedia($bgMedia->getPath())
                            ->preservingOriginal()
                            ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
                    } catch (\Throwable $e) {
                        Log::warning("Could not copy media for featured section ID {$fs->id}: " . $e->getMessage());
                    }
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('home_sections')) {
            DB::table('home_sections')->where('type', 'products')->delete();
        }
    }
};
