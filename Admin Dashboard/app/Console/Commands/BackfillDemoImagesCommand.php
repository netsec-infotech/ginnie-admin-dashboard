<?php

namespace App\Console\Commands;

use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Backfill real product / category images on existing demo data.
 *
 *   php artisan demo:backfill-images               # products + categories
 *   php artisan demo:backfill-images --only=products
 *   php artisan demo:backfill-images --only=categories
 *   php artisan demo:backfill-images --force       # also re-fetches rows that already have an image
 *
 * Re-runs are safe: by default only rows missing media are touched. Each
 * fetch tries LoremFlickr first (keyword-relevant Flickr photos), falls
 * back to Picsum (seeded random) if that fails. No API key required.
 *
 * Useful when you've already built up demo state (orders, refunds, etc.)
 * that you don't want to drop by re-running DemoDataSeeder.
 */
class BackfillDemoImagesCommand extends Command
{
    protected $signature = 'demo:backfill-images
                            {--only= : "products" | "categories" — limit the scope}
                            {--force : Re-fetch even for rows that already have a media file}
                            {--limit=0 : Cap on rows processed (0 = no cap)}';

    protected $description = 'Attach real photos to demo Products and Categories using Spatie addMediaFromUrl';

    public function handle(): int
    {
        $only  = $this->option('only');
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $touched = 0;

        if ($only !== 'categories') {
            $touched += $this->backfillRows(
                Product::where('metadata', 'like', '%"is_demo":true%')
                    ->orWhere('metadata', 'like', '%"is_demo": true%')
                    ->get(),
                fn (Product $p) => $p->title,
                SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE(),
                'Product',
                $force,
                $limit,
            );
        }

        if ($only !== 'products') {
            $touched += $this->backfillRows(
                Category::where('metadata', 'like', '%"is_demo":true%')
                    ->orWhere('metadata', 'like', '%"is_demo": true%')
                    ->get(),
                fn (Category $c) => $c->title,
                'image',
                'Category',
                $force,
                $limit,
            );
        }

        $this->info("Done. Attached {$touched} image(s).");
        return self::SUCCESS;
    }

    /**
     * @template T of HasMedia
     * @param  iterable<T>      $rows
     * @param  callable(T):string  $keyword
     */
    private function backfillRows(iterable $rows, callable $keyword, string $collection, string $label, bool $force, int $limit): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if ($limit > 0 && $count >= $limit) break;

            if (!$force && $row->getFirstMedia($collection)) {
                continue;
            }
            if ($force && $row->getFirstMedia($collection)) {
                $row->clearMediaCollection($collection);
            }

            $kw = (string) $keyword($row);
            if ($this->attachImageFromUrl($row, $kw, $collection)) {
                $this->line("  ✓ {$label} #{$row->getKey()}: {$kw}");
                $count++;
            } else {
                $this->warn("  ✗ {$label} #{$row->getKey()}: could not fetch image for \"{$kw}\"");
            }
        }
        return $count;
    }

    private function imageKeywordSlug(string $keyword): string
    {
        $kw = preg_replace('/[^a-zA-Z0-9 ]/', '', $keyword);
        $kw = trim((string) preg_replace('/\s+/', ',', (string) $kw));
        return $kw !== '' ? strtolower($kw) : 'product';
    }

    private function attachImageFromUrl(HasMedia $model, string $keyword, string $collection): bool
    {
        $kw = $this->imageKeywordSlug($keyword);
        $sources = [
            "https://loremflickr.com/600/600/{$kw}",
            "https://picsum.photos/seed/{$kw}-" . substr(md5($kw . microtime(true)), 0, 6) . "/600/600",
        ];
        foreach ($sources as $url) {
            try {
                $model->addMediaFromUrl($url)
                    ->usingFileName(Str::slug($keyword) . '-' . substr(md5(uniqid('', true)), 0, 8) . '.jpg')
                    ->toMediaCollection($collection);
                return true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}
