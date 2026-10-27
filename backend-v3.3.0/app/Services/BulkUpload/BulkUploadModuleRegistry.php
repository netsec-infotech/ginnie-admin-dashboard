<?php

namespace App\Services\BulkUpload;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

/**
 * Registry of modules that participate in the generic bulk upload pipeline.
 * Add a new entry in defaultModules() to wire up a new module — that's the
 * only place the module catalogue lives.
 */
class BulkUploadModuleRegistry
{
    /** @var array<string, BulkUploadModuleConfig> */
    private array $modules = [];

    public function __construct()
    {
        foreach ($this->defaultModules() as $config) {
            $this->register($config);
        }
    }

    /**
     * @return array<int, BulkUploadModuleConfig>
     */
    private function defaultModules(): array
    {
        return [
            new BulkUploadModuleConfig(
                key: 'brands',
                modelClass: Brand::class,
                importerClass: BrandBulkImportService::class,
                displayNameKey: 'labels.brands',
                indexRouteName: 'admin.brands.index',
                menuKey: 'brands',
            ),
            new BulkUploadModuleConfig(
                key: 'categories',
                modelClass: Category::class,
                importerClass: CategoryBulkImportService::class,
                displayNameKey: 'labels.categories',
                indexRouteName: 'admin.categories.index',
                menuKey: 'categories',
            ),
            new BulkUploadModuleConfig(
                key: 'products',
                modelClass: Product::class,
                importerClass: ProductBulkImportService::class,
                displayNameKey: 'labels.products',
                indexRouteName: 'admin.products.index',
                menuKey: 'products',
            ),
        ];
    }

    public function register(BulkUploadModuleConfig $config): void
    {
        $this->modules[$config->key] = $config;
    }

    public function get(string $key): BulkUploadModuleConfig
    {
        try {
            return $this->modules[$key];
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("No bulk upload module registered for [{$key}].");
        }
    }

    public function tryGet(string $key): ?BulkUploadModuleConfig
    {
        return $this->modules[$key] ?? null;
    }

    /** @return array<string, BulkUploadModuleConfig> */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Reverse lookup by model class — used by the importer registry on the command.
     */
    public function getByModel(string $modelClass): ?BulkUploadModuleConfig
    {
        foreach ($this->modules as $config) {
            if ($config->modelClass === $modelClass) {
                return $config;
            }
        }

        return null;
    }
}
