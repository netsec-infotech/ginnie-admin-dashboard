<?php

namespace App\Services\BulkUpload;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Helpers\TranslationHelper;
use App\Interfaces\BulkImportable;
use App\Interfaces\GroupedBulkImportable;
use App\Interfaces\TranslationBulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ProductService;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Product adapter for the generic bulk-upload pipeline.
 *
 * Products are grouped by `handle` because one product can contain multiple
 * variant/store pricing rows. The heavy product mapping remains in ProductService
 * so the admin bulk upload follows the existing product creation behavior.
 */
class ProductBulkImportService implements BulkImportable, GroupedBulkImportable, TranslationBulkImportable
{
    public function __construct(protected ProductService $productService) {}

    public function moduleKey(): string
    {
        return 'products';
    }

    public function modelClass(): string
    {
        return Product::class;
    }

    public function csvTemplate(): array
    {
        $columns = $this->productColumns();

        return [
            'columns' => $this->sellerRoute() ? array_values(array_filter($columns, fn (string $column) => $column !== 'seller_id')) : $columns,
            'sample' => $this->sellerRoute() ? $this->withoutSellerSampleValue($this->productSampleRow()) : $this->productSampleRow(),
        ];
    }

    private function productSampleRow(): array
    {
        $row = [
            'classic-t-shirt',
            'Classic T-Shirt',
            1,
            144,
            '',
            ProductTypeEnum::SIMPLE(),
            'cover',
            'https://example.com/products/classic-t-shirt.jpg',
            'A timeless classic t-shirt.',
            'High-quality cotton t-shirt suitable for everyday wear.',
            10,
            1,
            1,
            0,
            1,
            7,
            1,
            'preparing',
            0,
            1,
            0,
            '',
            '',
            '6 months',
            '',
            'India',
            '6109',
            'tshirt,classic,cotton',
            'Material, Care Instructions',
            'Cotton, Machine wash cold',
            'Title',
            'Default Title',
            '',
            '',
            '',
            '',
            1,
            'SIMP-RED-001',
            999.00,
            899.00,
            600.00,
            100,
            'BAR-SIMPLE-001',
            0.25,
            2,
            30,
            25,
            'yes',
            'on',
            'Classic T-Shirt',
            'tshirt,classic,cotton',
            'Premium everyday cotton t-shirt.',
        ];

        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code !== 'en') {
                array_push($row, '', '', '');
            }
        }

        return $row;
    }

    public function validateTranslationLocale(string $locale): void
    {
        $locale = app(LanguageService::class)->normalizeCode($locale);
        if ($locale === 'en' || ! app(CatalogLanguageService::class)->isValidCatalogLocale($locale)) {
            throw new \InvalidArgumentException('labels.invalid_language');
        }
    }

    public function translationExportColumns(string $locale): array
    {
        $locale = app(LanguageService::class)->normalizeCode($locale);
        $this->validateTranslationLocale($locale);

        return [
            'uuid', 'id', 'slug', 'seller_id', 'category_id', 'brand_id',
            'title_en', 'short_description_en', 'description_en',
            "title_{$locale}", "short_description_{$locale}", "description_{$locale}",
        ];
    }

    public function translationExportRow($model, string $locale): array
    {
        $locale = app(LanguageService::class)->normalizeCode($locale);
        $this->validateTranslationLocale($locale);

        return [
            'uuid' => $model->uuid,
            'id' => $model->id,
            'slug' => $model->slug,
            'seller_id' => $model->seller_id,
            'category_id' => $model->category_id,
            'brand_id' => $model->brand_id,
            'title_en' => $model->getTranslation('title', 'en', false),
            'short_description_en' => $model->getTranslation('short_description', 'en', false),
            'description_en' => $model->getTranslation('description', 'en', false),
            "title_{$locale}" => $model->getTranslation('title', $locale, false),
            "short_description_{$locale}" => $model->getTranslation('short_description', $locale, false),
            "description_{$locale}" => $model->getTranslation('description', $locale, false),
        ];
    }

    public function availableFields(): array
    {
        $fields = [
            ['key' => 'handle', 'label' => 'labels.handle', 'required' => true, 'type' => 'string'],
            ['key' => 'title', 'label' => 'labels.title', 'required' => true, 'type' => 'string'],
            ['key' => 'seller_id', 'label' => 'labels.seller_id', 'required' => true, 'type' => 'integer'],
            ['key' => 'category_id', 'label' => 'labels.category_id', 'required' => true, 'type' => 'integer'],
            ['key' => 'brand_id', 'label' => 'labels.brand_id', 'required' => false, 'type' => 'integer'],
            ['key' => 'type', 'label' => 'labels.product_type', 'required' => true, 'type' => 'enum'],
            ['key' => 'image_fit', 'label' => 'labels.image_fit', 'required' => false, 'type' => 'string'],
            ['key' => 'image_src', 'label' => 'labels.image_url', 'required' => false, 'type' => 'url'],
            ['key' => 'short_description', 'label' => 'labels.short_description', 'required' => false, 'type' => 'string'],
            ['key' => 'description', 'label' => 'labels.description', 'required' => false, 'type' => 'string'],
            ['key' => 'base_prep_time', 'label' => 'labels.preparation_time', 'required' => false, 'type' => 'integer'],
            ['key' => 'minimum_order_quantity', 'label' => 'labels.minimum_order_quantity', 'required' => false, 'type' => 'integer'],
            ['key' => 'quantity_step_size', 'label' => 'labels.quantity_step_size', 'required' => false, 'type' => 'integer'],
            ['key' => 'total_allowed_quantity', 'label' => 'labels.total_allowed_quantity', 'required' => false, 'type' => 'integer'],
            ['key' => 'is_returnable', 'label' => 'labels.is_returnable', 'required' => false, 'type' => 'boolean'],
            ['key' => 'returnable_days', 'label' => 'labels.returnable_days', 'required' => false, 'type' => 'integer'],
            ['key' => 'is_cancelable', 'label' => 'labels.is_cancelable', 'required' => false, 'type' => 'boolean'],
            ['key' => 'cancelable_till', 'label' => 'labels.cancelable_till', 'required' => false, 'type' => 'string'],
            ['key' => 'is_attachment_required', 'label' => 'labels.is_attachment_required', 'required' => false, 'type' => 'boolean'],
            ['key' => 'featured', 'label' => 'labels.featured', 'required' => false, 'type' => 'boolean'],
            ['key' => 'requires_otp', 'label' => 'labels.requires_otp', 'required' => false, 'type' => 'boolean'],
            ['key' => 'video_type', 'label' => 'labels.video_type', 'required' => false, 'type' => 'string'],
            ['key' => 'video_link', 'label' => 'labels.video_link', 'required' => false, 'type' => 'string'],
            ['key' => 'warranty_period', 'label' => 'labels.warranty_period', 'required' => false, 'type' => 'string'],
            ['key' => 'guarantee_period', 'label' => 'labels.guarantee_period', 'required' => false, 'type' => 'string'],
            ['key' => 'made_in', 'label' => 'labels.made_in', 'required' => false, 'type' => 'string'],
            ['key' => 'hsn_code', 'label' => 'labels.hsn_code', 'required' => false, 'type' => 'string'],
            ['key' => 'tags', 'label' => 'labels.tags', 'required' => false, 'type' => 'string'],
            ['key' => 'custom_fields_title', 'label' => 'labels.custom_fields_title', 'required' => false, 'type' => 'string'],
            ['key' => 'custom_fields_value', 'label' => 'labels.custom_fields_value', 'required' => false, 'type' => 'string'],
            ['key' => 'option1_name', 'label' => 'labels.option1_name', 'required' => false, 'type' => 'string'],
            ['key' => 'option1_value', 'label' => 'labels.option1_value', 'required' => false, 'type' => 'string'],
            ['key' => 'option2_name', 'label' => 'labels.option2_name', 'required' => false, 'type' => 'string'],
            ['key' => 'option2_value', 'label' => 'labels.option2_value', 'required' => false, 'type' => 'string'],
            ['key' => 'option3_name', 'label' => 'labels.option3_name', 'required' => false, 'type' => 'string'],
            ['key' => 'option3_value', 'label' => 'labels.option3_value', 'required' => false, 'type' => 'string'],
            ['key' => 'store_id', 'label' => 'labels.store_id', 'required' => true, 'type' => 'integer'],
            ['key' => 'variant_sku', 'label' => 'labels.sku', 'required' => false, 'type' => 'string'],
            ['key' => 'variant_price', 'label' => 'labels.price', 'required' => true, 'type' => 'decimal'],
            ['key' => 'variant_special_price', 'label' => 'labels.special_price', 'required' => false, 'type' => 'decimal'],
            ['key' => 'variant_cost', 'label' => 'labels.cost', 'required' => true, 'type' => 'decimal'],
            ['key' => 'variant_stock', 'label' => 'labels.stock', 'required' => false, 'type' => 'integer'],
            ['key' => 'variant_barcode', 'label' => 'labels.barcode', 'required' => false, 'type' => 'string'],
            ['key' => 'variant_weight', 'label' => 'labels.weight', 'required' => false, 'type' => 'decimal'],
            ['key' => 'variant_height', 'label' => 'labels.height', 'required' => false, 'type' => 'decimal'],
            ['key' => 'variant_length', 'label' => 'labels.length', 'required' => false, 'type' => 'decimal'],
            ['key' => 'variant_breadth', 'label' => 'labels.breadth', 'required' => false, 'type' => 'decimal'],
            ['key' => 'variant_availability', 'label' => 'labels.availability', 'required' => false, 'type' => 'string'],
            ['key' => 'variant_is_default', 'label' => 'labels.is_default', 'required' => false, 'type' => 'boolean'],
            ['key' => 'seo_title', 'label' => 'labels.seo_title', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_keywords', 'label' => 'labels.seo_keywords', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_description', 'label' => 'labels.seo_description', 'required' => false, 'type' => 'string'],
        ];

        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code === 'en') {
                continue;
            }

            $fields[] = ['key' => "title_{$language->code}", 'label' => __('labels.title') . " ({$language->name})", 'required' => false, 'type' => 'string', 'locale' => $language->code];
            $fields[] = ['key' => "short_description_{$language->code}", 'label' => __('labels.short_description') . " ({$language->name})", 'required' => false, 'type' => 'string', 'locale' => $language->code];
            $fields[] = ['key' => "description_{$language->code}", 'label' => __('labels.description') . " ({$language->name})", 'required' => false, 'type' => 'string', 'locale' => $language->code];
        }

        return $fields;
    }

    public function validateMapping(array $mapping, array $headers): void
    {
        $operation = strtolower((string) request()->input('operation', 'create'));
        if ($operation === 'translation') {
            if (empty($mapping['uuid']) || ! in_array($mapping['uuid'], $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_match_column_missing');
            }

            return;
        }

        $requiredFields = ['category_id', 'type', 'title', 'store_id', 'variant_price', 'variant_cost'];
        if ($operation !== 'update') {
            array_unshift($requiredFields, 'handle');
        }

        foreach ($requiredFields as $field) {
            if (empty($mapping[$field]) || ! in_array($mapping[$field], $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_product_required_mapping_missing');
            }
        }

        foreach ($mapping as $header) {
            if ($header !== null && $header !== '' && ! in_array($header, $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_unknown_csv_column');
            }
        }
    }

    public function processRow(array $row, BulkUploadJob $job): array
    {
        return $this->processRows([$row], $job);
    }

    public function groupKey(array $row, BulkUploadJob $job): string
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];

        $operation = $job->operation?->value ?? 'create';
        if ($operation === 'translation') {
            return 'uuid:'.$this->stringFromRow($row, $mapping, 'uuid');
        }

        if ($operation === 'update') {
            $matchField = $job->match_field ?: 'id';
            $matchValue = $this->stringFromRow($row, $mapping, $matchField);
            if ($matchValue !== '') {
                return $matchField.':'.$matchValue;
            }
        }

        return $this->stringFromRow($row, $mapping, 'handle');
    }

    public function processRows(array $rows, BulkUploadJob $job): array
    {
        try {
            $operation = $job->operation?->value ?? 'create';
            if ($operation === 'translation') {
                return $this->processTranslationRow($rows[0] ?? [], $job);
            }

            if ($operation === 'update') {
                return $this->processUpdateRows($rows, $job);
            }

            return $this->processCreateRows($rows, $job);
        } catch (\Throwable $e) {
            Log::warning('ProductBulkImportService::processRows failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function processCreateRows(array $rows, BulkUploadJob $job): array
    {
        $payloadResult = $this->buildPayloadForRows($rows, $job);
        if (isset($payloadResult['error'])) {
            return [BulkImportable::OUTCOME_FAILED, $payloadResult['error']];
        }

        $basePayload = $payloadResult['payload'];
        $request = Request::create('/seller/products/bulk-upload', 'POST', $basePayload);
        $result = $this->productService->storeProduct($basePayload, $request);

        if (! ($result['success'] ?? false)) {
            return [BulkImportable::OUTCOME_FAILED, __('labels.something_went_wrong')];
        }

        return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_product_row_created', ['title' => $basePayload['title']['en']])];
    }

    private function processUpdateRows(array $rows, BulkUploadJob $job): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $product = $this->findProductForUpdate($rows[0] ?? [], $job, $mapping);
        if (! $product) {
            return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_product_no_match')];
        }

        $payloadResult = $this->buildPayloadForRows($rows, $job, $product);
        if (isset($payloadResult['error'])) {
            return [BulkImportable::OUTCOME_FAILED, $payloadResult['error']];
        }

        $basePayload = $payloadResult['payload'];
        $request = Request::create('/seller/products/bulk-upload', 'POST', $basePayload);
        $result = $this->productService->updateProduct($product, $basePayload, $request);

        if (! ($result['success'] ?? false)) {
            return [BulkImportable::OUTCOME_FAILED, __('labels.something_went_wrong')];
        }

        return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_product_row_updated', ['title' => $basePayload['title']['en']])];
    }

    private function processTranslationRow(array $row, BulkUploadJob $job): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $locale = app(LanguageService::class)->normalizeCode((string) $job->target_locale);
        $this->validateTranslationLocale($locale);

        $uuid = $this->stringFromRow($row, $mapping, 'uuid');
        if ($uuid === '') {
            return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_row_missing_match_value', ['field' => 'uuid'])];
        }

        $query = Product::query()->where('uuid', $uuid);
        $sellerId = (int) data_get($job->default_values, 'seller_id', 0);
        if ($sellerId > 0) {
            $query->where('seller_id', $sellerId);
        }

        $product = $query->first();
        if (! $product) {
            return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => 'uuid', 'value' => $uuid])];
        }

        $translations = [];
        $changes = [];
        foreach (['title' => 255, 'short_description' => 255, 'description' => 50000] as $field => $limit) {
            $value = $this->stringFromRow($row, $mapping, "{$field}_{$locale}");
            if ($value === '') {
                continue;
            }

            if ($value === '__DELETE__') {
                if (array_key_exists($locale, $product->getTranslations($field))) {
                    $translations[$field] = [$locale => ''];
                    $changes[] = $field;
                }

                continue;
            }

            if (mb_strlen($value) > $limit) {
                return [BulkImportable::OUTCOME_FAILED, __('validation.max.string', [
                    'attribute' => $field,
                    'max' => $limit,
                ])];
            }

            if ($product->getTranslation($field, $locale, false) !== $value) {
                $translations[$field] = [$locale => $value];
                $changes[] = $field;
            }
        }

        if ($translations === []) {
            return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_product_row_no_changes', [
                'title' => $product->getTranslation('title', 'en', false),
            ])];
        }

        $this->productService->updateTranslations($product, $translations);

        return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_product_row_updated', [
            'title' => $product->getTranslation('title', 'en', false),
            'fields' => implode(', ', $changes),
        ])];
    }

    /**
     * @param  array<int, array<string,mixed>>  $rows
     * @return array{payload:array<string,mixed>}|array{error:string}
     */
    private function buildPayloadForRows(array $rows, BulkUploadJob $job, ?Product $existingProduct = null): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $defaults = is_array($job->default_values) ? $job->default_values : [];
        $mappedRows = array_map(fn (array $row) => $this->mappedRow($row, $mapping), $rows);
        if ($existingProduct) {
            $mappedRows = $this->mergeExistingProductDefaults($mappedRows, $existingProduct);
        }

        $firstRow = $mappedRows[0] ?? [];
        $handle = trim((string) ($firstRow['handle'] ?? ''));
        $title = trim((string) ($firstRow['title'] ?? $handle));
        $sellerId = (int) (($firstRow['seller_id'] ?? null) ?: ($defaults['seller_id'] ?? 0));
        $categoryId = (int) ($firstRow['category_id'] ?? 0);
        $brandId = ($firstRow['brand_id'] ?? '') !== '' ? (int) $firstRow['brand_id'] : null;
        $type = strtolower(trim((string) ($firstRow['type'] ?? '')));

        $shortDescription = trim((string) ($firstRow['short_description'] ?? ''));
        $description = trim((string) ($firstRow['description'] ?? ''));
        if ($title === '' || $shortDescription === '' || $description === '' || ! $sellerId || ! $categoryId || $type === '') {
            return ['error' => __('labels.bulk_upload_product_required_values_missing')];
        }
        if (! in_array($type, ProductTypeEnum::values(), true)) {
            return ['error' => __('labels.type_required')];
        }
        if (! Seller::whereKey($sellerId)->exists()) {
            return ['error' => __('labels.invalid_seller')];
        }
        if (! Category::whereKey($categoryId)->exists()) {
            return ['error' => __('labels.invalid_category')];
        }
        if ($brandId && ! Brand::whereKey($brandId)->exists()) {
            return ['error' => __('labels.invalid_brand')];
        }

        [$basePayload, $variantsJson, $pricing] = $this->productService->buildBulkUploadPayload(
            $sellerId,
            array_keys($firstRow),
            $mappedRows
        );

        $translationResult = $this->translationPayload($mappedRows, $basePayload, $existingProduct);
        if (isset($translationResult['error'])) {
            return ['error' => $translationResult['error']];
        }

        // Preserve the validated group identity before ProductService persists the product.
        $basePayload['seller_id'] = $sellerId;
        $basePayload['category_id'] = $categoryId;
        $basePayload['brand_id'] = $brandId;
        $basePayload['title'] = $translationResult['translations']['title'];
        $basePayload['short_description'] = $translationResult['translations']['short_description'];
        $basePayload['description'] = $translationResult['translations']['description'];
        $basePayload['type'] = $type;
        $basePayload['metadata'] = $this->seoMetadata($firstRow, $existingProduct);
        if ($existingProduct) {
            // Keep the current attachment behavior because the CSV template does not expose this field.
            $basePayload['attachment_mode'] = $existingProduct->attachment_mode;
            $existingProduct->loadMissing('taxClasses');
            $basePayload['tax_groups'] = $existingProduct->taxClasses->pluck('id')->all();
        }

        if ($type === ProductTypeEnum::VARIANT()) {
            $basePayload['variants_json'] = json_encode($variantsJson);
            $basePayload['pricing'] = json_encode(['variant_pricing' => $pricing['variant_pricing'] ?? []]);
        } else {
            $basePayload['pricing'] = json_encode(['store_pricing' => $pricing['store_pricing'] ?? []]);
            $basePayload['barcode'] = $variantsJson[0]['barcode'] ?? null;
            $basePayload['weight'] = $variantsJson[0]['weight'] ?? null;
            $basePayload['height'] = $variantsJson[0]['height'] ?? null;
            $basePayload['length'] = $variantsJson[0]['length'] ?? null;
            $basePayload['breadth'] = $variantsJson[0]['breadth'] ?? null;
        }

        return ['payload' => $basePayload];
    }

    /**
     * Product-level values are repeated for variant rows. Reject contradictory
     * translations rather than silently taking whichever variant appears first.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $basePayload
     * @return array{translations?: array<string, array<string, string>>, error?: string}
     */
    private function translationPayload(array $rows, array $basePayload, ?Product $existingProduct): array
    {
        $translations = [];
        $limits = ['title' => 255, 'short_description' => 255, 'description' => 50000];

        foreach ($limits as $field => $limit) {
            $translations[$field] = $existingProduct?->getTranslations($field) ?: [];
            $englishColumn = $field;
            $englishValues = collect($rows)
                ->map(fn (array $row): string => trim((string) ($row[$englishColumn] ?? '')))
                ->filter(fn (string $value): bool => $value !== '')
                ->unique()
                ->values();

            if ($englishValues->count() > 1) {
                return ['error' => __('labels.bulk_upload_conflicting_product_values', ['field' => $englishColumn])];
            }

            $english = (string) ($englishValues->first() ?? trim((string) ($basePayload[$field] ?? '')));
            if ($english !== '') {
                $translations[$field]['en'] = $english;
            }

            foreach (TranslationHelper::getClientLanguages() as $language) {
                if ($language->code === 'en') {
                    continue;
                }

                $column = "{$field}_{$language->code}";
                $values = collect($rows)
                    ->map(fn (array $row): string => trim((string) ($row[$column] ?? '')))
                    ->filter(fn (string $value): bool => $value !== '')
                    ->unique()
                    ->values();

                if ($values->count() > 1) {
                    return ['error' => __('labels.bulk_upload_conflicting_product_values', ['field' => $column])];
                }

                $value = (string) ($values->first() ?? '');
                if ($value === '') {
                    continue;
                }
                if (mb_strlen($value) > $limit) {
                    return ['error' => __('validation.max.string', ['attribute' => $column, 'max' => $limit])];
                }

                $translations[$field][$language->code] = $value;
            }
        }

        return ['translations' => $translations];
    }

    public function exportColumns(): array
    {
        return array_merge(['id'], $this->productColumns());
    }

    public function exportRow($model): array
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $keywords = $metadata['seo_keywords'] ?? null;
        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        $tags = is_string($model->tags) ? json_decode($model->tags, true) : $model->tags;
        $variant = $model->variants->first();
        $storePrice = $variant?->storeProductVariants?->first();
        $customFields = is_array($model->custom_fields) ? $model->custom_fields : [];

        $row = [
            'id' => $model->id,
            'handle' => $model->slug ?: $model->id,
            'title' => $model->getTranslation('title', 'en', false),
            'seller_id' => $model->seller_id,
            'category_id' => $model->category_id,
            'brand_id' => $model->brand_id,
            'type' => is_string($model->type) ? $model->type : ($model->type?->value ?? ''),
            'image_fit' => $model->image_fit,
            'image_src' => '',
            'short_description' => $model->getTranslation('short_description', 'en', false),
            'description' => $model->getTranslation('description', 'en', false),
            'base_prep_time' => $model->base_prep_time,
            'minimum_order_quantity' => $model->minimum_order_quantity,
            'quantity_step_size' => $model->quantity_step_size,
            'total_allowed_quantity' => $model->total_allowed_quantity,
            'is_returnable' => $model->is_returnable,
            'returnable_days' => $model->returnable_days,
            'is_cancelable' => $model->is_cancelable,
            'cancelable_till' => $model->cancelable_till,
            'is_attachment_required' => $model->is_attachment_required,
            'featured' => $model->featured,
            'requires_otp' => $model->requires_otp,
            'video_type' => $model->video_type,
            'video_link' => $model->video_link,
            'warranty_period' => $model->warranty_period,
            'guarantee_period' => $model->guarantee_period,
            'made_in' => $model->made_in,
            'hsn_code' => $model->hsn_code,
            'tags' => is_array($tags) ? implode(',', $tags) : '',
            'custom_fields_title' => implode(',', array_keys($customFields)),
            'custom_fields_value' => implode(',', array_values($customFields)),
            'option1_name' => '',
            'option1_value' => '',
            'option2_name' => '',
            'option2_value' => '',
            'option3_name' => '',
            'option3_value' => '',
            'store_id' => $storePrice?->store_id,
            'variant_sku' => $storePrice?->sku,
            'variant_price' => $storePrice?->price_exclude_tax,
            'variant_special_price' => $storePrice?->special_price_exclude_tax,
            'variant_cost' => $storePrice?->cost,
            'variant_stock' => $storePrice?->stock,
            'variant_barcode' => $variant?->barcode,
            'variant_weight' => $variant?->weight,
            'variant_height' => $variant?->height,
            'variant_length' => $variant?->length,
            'variant_breadth' => $variant?->breadth,
            'variant_availability' => $variant?->availability ? 'yes' : 'no',
            'variant_is_default' => $variant?->is_default ? 'on' : 'off',
            'seo_title' => $metadata['seo_title'] ?? '',
            'seo_keywords' => $keywords ?: '',
            'seo_description' => $metadata['seo_description'] ?? '',
        ];

        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code === 'en') {
                continue;
            }

            foreach (['title', 'short_description', 'description'] as $field) {
                $row["{$field}_{$language->code}"] = $model->getTranslation($field, $language->code, false);
            }
        }

        return $row;
    }

    public function exportFilters(): array
    {
        return [
            [
                'key' => 'type',
                'label' => 'labels.product_type',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => ProductTypeEnum::SIMPLE(), 'label' => 'labels.simple'],
                    ['value' => ProductTypeEnum::VARIANT(), 'label' => 'labels.variant'],
                ],
            ],
            [
                'key' => 'status',
                'label' => 'labels.status',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => ProductStatusEnum::ACTIVE(), 'label' => 'labels.active'],
                    ['value' => ProductStatusEnum::DRAFT(), 'label' => 'labels.draft'],
                ],
            ],
            [
                'key' => 'verification_status',
                'label' => 'labels.verification_status',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => ProductVarificationStatusEnum::APPROVED(), 'label' => 'labels.approved'],
                    ['value' => ProductVarificationStatusEnum::PENDING(), 'label' => 'labels.pending'],
                    ['value' => ProductVarificationStatusEnum::REJECTED(), 'label' => 'labels.rejected'],
                ],
            ],
            ['key' => 'category_id', 'label' => 'labels.category', 'type' => 'select_remote', 'route' => 'admin.categories.search'],
        ];
    }

    public function applyExportFilters($query, array $filters)
    {
        foreach (['type', 'status', 'verification_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['category_id', 'seller_id'] as $field) {
            if (! empty($filters[$field]) && is_numeric($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        return $query->with(['variants.storeProductVariants']);
    }

    public function availableMatchFields(): array
    {
        return [
            ['key' => 'uuid', 'label' => 'labels.uuid', 'column' => 'uuid'],
            ['key' => 'id', 'label' => 'labels.id', 'column' => 'id'],
            ['key' => 'handle', 'label' => 'labels.handle', 'column' => 'slug'],
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,string|null>  $mapping
     */
    private function findProductForUpdate(array $row, BulkUploadJob $job, array $mapping): ?Product
    {
        $matchField = $job->match_field ?: 'id';
        $matchValue = $this->stringFromRow($row, $mapping, $matchField);
        if ($matchValue === '') {
            return null;
        }

        $matchColumn = collect($this->availableMatchFields())
            ->firstWhere('key', $matchField)['column'] ?? $matchField;

        $query = Product::query();

        // Handle update uses product slug; accept both exported slugs and human-entered titles.
        if ($matchField === 'handle') {
            $handleValues = array_values(array_unique(array_filter([
                $matchValue,
                Str::slug($matchValue),
            ])));
            $query->whereIn($matchColumn, $handleValues);
        } else {
            $query->where($matchColumn, $matchValue);
        }

        // Seller uploads may only update products that belong to their seller account.
        $sellerId = (int) ((is_array($job->default_values) ? $job->default_values['seller_id'] ?? 0 : 0));
        if ($sellerId > 0) {
            $query->where('seller_id', $sellerId);
        }

        return $query->first();
    }

    /**
     * @param  array<int, array<string,mixed>>  $rows
     * @return array<int, array<string,mixed>>
     */
    private function mergeExistingProductDefaults(array $rows, Product $product): array
    {
        $metadata = is_array($product->metadata) ? $product->metadata : [];
        $tags = is_string($product->tags) ? json_decode($product->tags, true) : $product->tags;
        $customFields = is_array($product->custom_fields) ? $product->custom_fields : [];

        $defaults = [
            'handle' => $product->slug,
            'title' => $product->getTranslation('title', 'en', false),
            'seller_id' => $product->seller_id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'type' => is_string($product->type) ? $product->type : ($product->type?->value ?? ''),
            'image_fit' => $product->image_fit,
            'short_description' => $product->getTranslation('short_description', 'en', false),
            'description' => $product->getTranslation('description', 'en', false),
            'base_prep_time' => $product->base_prep_time,
            'minimum_order_quantity' => $product->minimum_order_quantity,
            'quantity_step_size' => $product->quantity_step_size,
            'total_allowed_quantity' => $product->total_allowed_quantity,
            'is_returnable' => $product->is_returnable,
            'returnable_days' => $product->returnable_days,
            'is_cancelable' => $product->is_cancelable,
            'cancelable_till' => $product->cancelable_till,
            'is_attachment_required' => $product->is_attachment_required,
            'featured' => $product->featured,
            'requires_otp' => $product->requires_otp,
            'video_type' => $product->video_type,
            'video_link' => $product->video_link,
            'warranty_period' => $product->warranty_period,
            'guarantee_period' => $product->guarantee_period,
            'made_in' => $product->made_in,
            'hsn_code' => $product->hsn_code,
            'tags' => is_array($tags) ? implode(',', $tags) : '',
            'custom_fields_title' => implode(',', array_keys($customFields)),
            'custom_fields_value' => implode(',', array_values($customFields)),
            'seo_title' => $metadata['seo_title'] ?? '',
            'seo_keywords' => isset($metadata['seo_keywords']) && is_array($metadata['seo_keywords']) ? implode(',', $metadata['seo_keywords']) : '',
            'seo_description' => $metadata['seo_description'] ?? '',
        ];

        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code === 'en') {
                continue;
            }

            foreach (['title', 'short_description', 'description'] as $field) {
                $defaults["{$field}_{$language->code}"] = $product->getTranslation($field, $language->code, false);
            }
        }

        return array_map(function (array $row) use ($defaults) {
            foreach ($defaults as $key => $value) {
                if (($row[$key] ?? null) === null || $row[$key] === '') {
                    $row[$key] = $value;
                }
            }

            return $row;
        }, $rows);
    }

    /**
     * @return array<int, string>
     */
    private function productColumns(): array
    {
        $columns = [
            'handle',
            'title',
            'seller_id',
            'category_id',
            'brand_id',
            'type',
            'image_fit',
            'image_src',
            'short_description',
            'description',
            'base_prep_time',
            'minimum_order_quantity',
            'quantity_step_size',
            'total_allowed_quantity',
            'is_returnable',
            'returnable_days',
            'is_cancelable',
            'cancelable_till',
            'is_attachment_required',
            'featured',
            'requires_otp',
            'video_type',
            'video_link',
            'warranty_period',
            'guarantee_period',
            'made_in',
            'hsn_code',
            'tags',
            'custom_fields_title',
            'custom_fields_value',
            'option1_name',
            'option1_value',
            'option2_name',
            'option2_value',
            'option3_name',
            'option3_value',
            'store_id',
            'variant_sku',
            'variant_price',
            'variant_special_price',
            'variant_cost',
            'variant_stock',
            'variant_barcode',
            'variant_weight',
            'variant_height',
            'variant_length',
            'variant_breadth',
            'variant_availability',
            'variant_is_default',
            'seo_title',
            'seo_keywords',
            'seo_description',
        ];

        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code !== 'en') {
                $columns[] = "title_{$language->code}";
                $columns[] = "short_description_{$language->code}";
                $columns[] = "description_{$language->code}";
            }
        }

        return $columns;
    }

    private function sellerRoute(): bool
    {
        return str_starts_with((string) request()->route()?->getName(), 'seller.');
    }

    /**
     * @param  array<int,mixed>  $sample
     * @return array<int,mixed>
     */
    private function withoutSellerSampleValue(array $sample): array
    {
        array_splice($sample, 2, 1);

        return $sample;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,string|null>  $mapping
     * @return array<string,mixed>
     */
    private function mappedRow(array $row, array $mapping): array
    {
        $mapped = [];
        foreach (array_merge(['id'], $this->productColumns()) as $field) {
            $header = $mapping[$field] ?? null;
            $mapped[$field] = $header ? ($row[$header] ?? null) : null;
        }

        return $mapped;
    }

    private function stringFromRow(array $row, array $mapping, string $field): string
    {
        $header = $mapping[$field] ?? null;
        if (! $header) {
            return '';
        }
        $value = $row[$header] ?? null;

        return is_string($value) ? trim($value) : (is_scalar($value) ? trim((string) $value) : '');
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function seoMetadata(array $row, ?Product $existingProduct = null): array
    {
        $metadata = $existingProduct && is_array($existingProduct->metadata) ? $existingProduct->metadata : [];
        if (! empty($row['seo_title'])) {
            $metadata['seo_title'] = $row['seo_title'];
        }
        if (! empty($row['seo_description'])) {
            $metadata['seo_description'] = $row['seo_description'];
        }
        if (! empty($row['seo_keywords'])) {
            $metadata['seo_keywords'] = array_values(array_filter(array_map('trim', explode(',', (string) $row['seo_keywords']))));
        }

        return $metadata;
    }
}
