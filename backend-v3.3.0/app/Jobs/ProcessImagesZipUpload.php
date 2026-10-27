<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessImagesZipUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $token;

    public string $zipPath;

    public ?int $uploaderId = null;

    public bool $isSeller = false;

    /**
     * Create a new job instance.
     */
    public function __construct(string $token, string $zipPath, ?int $uploaderId = null, bool $isSeller = false)
    {
        $this->token = $token;
        $this->zipPath = $zipPath;
        $this->uploaderId = $uploaderId;
        $this->isSeller = $isSeller;
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(ProductService $productService): void
    {
        $this->updateProgress(['status' => 'processing', 'message' => 'Processing ZIP...']);

        try {
            if (! is_file($this->zipPath)) {
                $this->updateProgress(['status' => 'failed', 'message' => 'ZIP file missing on server']);

                return;
            }

            // Extract and index
            [$imagesIndex, $tmpDir] = $this->prepareBulkImagesIndex($this->zipPath);

            // Collect targets by product id, slug, or normalized title.
            $imageKeys = array_unique(array_merge(
                array_keys($imagesIndex['main'] ?? []),
                array_keys($imagesIndex['additional'] ?? []),
                array_keys($imagesIndex['variant'] ?? [])
            ));

            // Build product map by accepted filename keys for efficient lookup.
            $query = Product::query()->select(['id', 'title', 'slug', 'seller_id']);
            // If uploader is a seller, restrict to their own products only
            if ($this->isSeller && ! empty($this->uploaderId)) {
                $query->where('seller_id', $this->uploaderId);
            }
            $allForMap = $query->get();

            // Map filename key => list of product IDs. Title keys can intentionally match duplicates.
            $keyToProducts = [];
            foreach ($allForMap as $p) {
                foreach ($this->productLookupKeys($p) as $key) {
                    if ($key === '') {
                        continue;
                    }
                    $keyToProducts[$key] = $keyToProducts[$key] ?? [];
                    $keyToProducts[$key][] = (int) $p->id;
                }
            }

            // Prepare processing list as [rowKey, productId, label].
            $targets = [];
            foreach ($imageKeys as $key) {
                $pids = array_unique($keyToProducts[$key] ?? []);
                if (empty($pids)) {
                    // Keep a placeholder so we can report not found for this filename key.
                    $targets[] = ['row' => $key, 'productId' => null, 'label' => 'Product "'.$key.'"'];

                    continue;
                }
                foreach ($pids as $pid) {
                    $targets[] = [
                        'row' => $key,
                        'productId' => $pid,
                        'label' => 'Product "'.$key.'" (ID #'.$pid.')',
                    ];
                }
            }

            $total = count($targets);
            $processed = 0;
            $successCount = 0;
            $failed = [];

            $this->updateProgress(compact('total', 'processed', 'successCount') + ['failed_rows' => $failed, 'status' => 'processing']);

            foreach ($targets as $t) {
                try {
                    $pid = $t['productId'];
                    if ($pid === null) {
                        $failed[] = ['row' => $t['row'], 'title' => $t['label'], 'error' => 'Product not found'];
                    } else {
                        $product = Product::find($pid);
                        if (! $product) {
                            $failed[] = ['row' => $t['row'], 'title' => $t['label'], 'error' => 'Product not found'];
                        } else {
                            // Double check ownership just in case
                            if ($this->isSeller && ! empty($this->uploaderId) && (int) $product->seller_id !== (int) $this->uploaderId) {
                                $failed[] = ['row' => $t['row'], 'title' => $t['label'], 'error' => 'Unauthorized: cannot modify other seller\'s product'];
                            } else {
                                $this->attachBulkImagesForProduct($productService, $product, $imagesIndex);
                                $successCount++;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $failed[] = ['row' => $t['row'], 'title' => $t['label'], 'error' => $e->getMessage()];
                }
                $processed++;
                $this->updateProgress(compact('total', 'processed', 'successCount') + ['failed_rows' => $failed, 'status' => 'processing']);
            }

            // Cleanup
            if (! empty($tmpDir) && is_dir($tmpDir)) {
                try {
                    $this->rrmdir($tmpDir);
                } catch (\Throwable) {
                }
            }
            try {
                @unlink($this->zipPath);
            } catch (\Throwable) {
            }

            $this->updateProgress([
                'status' => 'completed',
                'message' => 'Completed',
                'total' => $total,
                'processed' => $processed,
                'successCount' => $successCount,
                'failed_rows' => $failed,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessImagesZipUpload failed: '.$e->getMessage());
            $this->updateProgress(['status' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    private function cacheKey(): string
    {
        return 'images_upload:'.$this->token;
    }

    private function updateProgress(array $data): void
    {
        $key = $this->cacheKey();
        $current = Cache::get($key, []);
        Cache::put($key, array_merge($current, $data), now()->addHours(6));
    }

    /**
     * Extract images ZIP to a temp directory and index by product/variant patterns.
     * Accepts path instead of UploadedFile.
     *
     * @return array [index, tempDir]
     */
    private function prepareBulkImagesIndex(string $zipPath): array
    {
        $tmpBase = storage_path('app/tmp');
        if (! is_dir($tmpBase)) {
            @mkdir($tmpBase, 0777, true);
        }
        $tmpDir = $tmpBase.'/bulk-images-'.uniqid();
        @mkdir($tmpDir, 0777, true);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open images ZIP');
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        // Index by product id, slug, or normalized title.
        $index = [
            'main' => [],          // lookupKey => filepath
            'additional' => [],    // lookupKey => [filepaths]
            'variant' => [],       // lookupKey => [variantId => filepath]
        ];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpDir));
        Log::debug('ZIP contents: '.json_encode(iterator_to_array($rii)));
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            $path = $file->getPathname();
            $name = $file->getFilename();
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowed, true)) {
                continue;
            }

            // Patterns by product id, slug, or title. Order matters: check specific names before main.
            // additional: {productKey}-{n}.{ext}
            if (preg_match('/^(.+)-(\d+)\.(jpg|jpeg|png|webp)$/i', $name, $m)) {
                $productKey = $this->normalizeLookupKey($m[1]);
                $index['additional'][$productKey] = $index['additional'][$productKey] ?? [];
                $index['additional'][$productKey][] = $path;

                continue;
            }
            // variant: {productKey}_{variantId}.{ext}
            if (preg_match('/^(.+)_([0-9]+)\.(jpg|jpeg|png|webp)$/i', $name, $m)) {
                $productKey = $this->normalizeLookupKey($m[1]);
                $vid = (int) $m[2];
                $index['variant'][$productKey] = $index['variant'][$productKey] ?? [];
                $index['variant'][$productKey][$vid] = $path;

                continue;
            }
            // main: {productKey}.{ext}
            if (preg_match('/^(.+)\.(jpg|jpeg|png|webp)$/i', $name, $m)) {
                $productKey = $this->normalizeLookupKey($m[1]);
                $index['main'][$productKey] = $path;

                continue;
            }
        }

        return [$index, $tmpDir];
    }

    private function attachBulkImagesForProduct(ProductService $service, Product $product, array $imagesIndex): void
    {
        $lookupKeys = $this->productLookupKeys($product);

        if ($mainImage = $this->firstImageForProduct($imagesIndex['main'] ?? [], $lookupKeys)) {
            $service->setMainImageFromPath($product, $mainImage);
        }

        if ($additionalImages = $this->firstImageForProduct($imagesIndex['additional'] ?? [], $lookupKeys)) {
            $service->setAdditionalImagesFromPaths($product, $additionalImages);
        }

        // Variant images use the matched product key and the concrete variant id.
        $variantMap = null;
        foreach ($lookupKeys as $lookupKey) {
            if (! empty($imagesIndex['variant'][$lookupKey]) && is_array($imagesIndex['variant'][$lookupKey])) {
                $variantMap = $imagesIndex['variant'][$lookupKey];
                break;
            }
        }

        if ($variantMap !== null) {
            $product->loadMissing('variants');
            foreach ($product->variants as $variant) {
                $vid = (int) $variant->id;
                if (! empty($variantMap[$vid])) {
                    $service->setVariantImageFromPath($variant, $variantMap[$vid]);
                }
            }
        }
    }

    private function normalizeLookupKey(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower((string) $value);
    }

    /**
     * @return array<int,string>
     */
    private function productLookupKeys(Product $product): array
    {
        return array_values(array_unique(array_filter([
            (string) $product->id,
            $this->normalizeLookupKey((string) $product->slug),
            $this->normalizeLookupKey($product->getTranslation('title', 'en', false)),
        ])));
    }

    private function firstImageForProduct(array $images, array $lookupKeys): mixed
    {
        foreach ($lookupKeys as $lookupKey) {
            if (! empty($images[$lookupKey])) {
                return $images[$lookupKey];
            }
        }

        return null;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
