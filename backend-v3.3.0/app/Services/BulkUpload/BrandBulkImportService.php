<?php

namespace App\Services\BulkUpload;

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Helpers\TranslationHelper;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Interfaces\BulkImportable;
use App\Interfaces\TranslationBulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Services\BrandService;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Brand-specific bulk import with dynamic multi-language translation support.
 * Owns CSV → attributes translation and the preserve-on-empty diff semantic for Update mode.
 */
class BrandBulkImportService implements BulkImportable, TranslationBulkImportable
{
    public function __construct(protected BrandService $brandService)
    {
    }

    public function moduleKey(): string
    {
        return 'brands';
    }

    public function modelClass(): string
    {
        return Brand::class;
    }

    public function csvTemplate(): array
    {
        $columns = ['title', 'description'];
        $sample = ['Nike', 'Sports brand'];

        $clientLanguages = TranslationHelper::getClientLanguages();
        $defaultLocale = TranslationHelper::getDefaultLocale();

        foreach ($clientLanguages as $lang) {
            if ($lang->code !== $defaultLocale) {
                $columns[] = "title_{$lang->code}";
                $columns[] = "description_{$lang->code}";
                $sample[] = '';
                $sample[] = '';
            }
        }

        $columns = array_merge($columns, ['status', 'scope_type', 'scope_id', 'logo_url', 'seo_title', 'seo_keywords', 'seo_description']);
        $sample = array_merge($sample, ['active', 'global', '', 'https://example.com/nike.png', 'Nike — Sports gear', 'sports,shoes,apparel', 'Premium sports brand.']);

        return [
            'columns' => $columns,
            'sample' => $sample,
        ];
    }

    public function validateTranslationLocale(string $locale): void
    {
        $normalized = app(LanguageService::class)->normalizeCode($locale);
        if ($normalized === 'en' || ! app(CatalogLanguageService::class)->isValidCatalogLocale($normalized)) {
            throw new \InvalidArgumentException('labels.invalid_language');
        }
    }

    public function translationExportColumns(string $locale): array
    {
        $locale = app(LanguageService::class)->normalizeCode($locale);
        $this->validateTranslationLocale($locale);

        return ['uuid', 'id', 'slug', 'title_en', 'description_en', "title_{$locale}", "description_{$locale}"];
    }

    public function translationExportRow($model, string $locale): array
    {
        $locale = app(LanguageService::class)->normalizeCode($locale);
        $this->validateTranslationLocale($locale);
        $titles = method_exists($model, 'getTranslations') ? ($model->getTranslations('title') ?: []) : [];
        $descriptions = method_exists($model, 'getTranslations') ? ($model->getTranslations('description') ?: []) : [];

        $titleEn = $titles['en'] ?? null;
        if (empty($titleEn)) {
            $rawTitle = $model->getRawOriginal('title');
            if (is_string($rawTitle) && ! empty($rawTitle)) {
                $decoded = json_decode($rawTitle, true);
                $titleEn = is_array($decoded) ? ($decoded['en'] ?? reset($decoded) ?: '') : $rawTitle;
            } else {
                $titleEn = (string) ($model->title ?? '');
            }
        }

        $descEn = $descriptions['en'] ?? null;
        if (empty($descEn)) {
            $rawDesc = $model->getRawOriginal('description');
            if (is_string($rawDesc) && ! empty($rawDesc)) {
                $decoded = json_decode($rawDesc, true);
                $descEn = is_array($decoded) ? ($decoded['en'] ?? reset($decoded) ?: '') : $rawDesc;
            } else {
                $descEn = (string) ($model->description ?? '');
            }
        }

        return [
            'uuid' => $model->uuid,
            'id' => $model->id,
            'slug' => $model->slug,
            'title_en' => (string) $titleEn,
            'description_en' => (string) $descEn,
            "title_{$locale}" => $titles[$locale] ?? '',
            "description_{$locale}" => $descriptions[$locale] ?? '',
        ];
    }

    /**
     * Export column order keeps the legacy id/title/description prefix and adds UUID for stable matching.
     */
    public function exportColumns(): array
    {
        $columns = ['id', 'title', 'description', 'uuid'];
        $clientLanguages = TranslationHelper::getClientLanguages();
        $defaultLocale = TranslationHelper::getDefaultLocale();

        foreach ($clientLanguages as $lang) {
            if ($lang->code !== $defaultLocale) {
                $columns[] = "title_{$lang->code}";
                $columns[] = "description_{$lang->code}";
            }
        }

        return array_merge($columns, ['status', 'scope_type', 'scope_id', 'logo_url', 'seo_title', 'seo_keywords', 'seo_description']);
    }

    /**
     * Flatten a Brand into the export row with localized columns.
     */
    public function exportRow($model): array
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $keywords = $metadata['seo_keywords'] ?? null;
        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        $defaultLocale = TranslationHelper::getDefaultLocale();
        $titleTranslations = method_exists($model, 'getTranslations') ? ($model->getTranslations('title') ?: []) : [];
        $descTranslations = method_exists($model, 'getTranslations') ? ($model->getTranslations('description') ?: []) : [];

        $row = [
            'id' => $model->id,
            'title' => $titleTranslations[$defaultLocale] ?? ($model->title ?? ''),
            'description' => $descTranslations[$defaultLocale] ?? ($model->description ?? ''),
            'uuid' => $model->uuid,
        ];

        $clientLanguages = TranslationHelper::getClientLanguages();
        foreach ($clientLanguages as $lang) {
            if ($lang->code !== $defaultLocale) {
                $row["title_{$lang->code}"] = $titleTranslations[$lang->code] ?? '';
                $row["description_{$lang->code}"] = $descTranslations[$lang->code] ?? '';
            }
        }

        $row['status'] = is_string($model->status) ? $model->status : ($model->status?->value ?? '');
        $row['scope_type'] = is_string($model->scope_type) ? $model->scope_type : ($model->scope_type?->value ?? '');
        $row['scope_id'] = $model->scope_id;
        $row['logo_url'] = '';
        $row['seo_title'] = $metadata['seo_title'] ?? '';
        $row['seo_keywords'] = $keywords ?: '';
        $row['seo_description'] = $metadata['seo_description'] ?? '';

        return $row;
    }

    public function exportFilters(): array
    {
        return [
            [
                'key' => 'status',
                'label' => 'labels.status',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => BrandStatusEnum::ACTIVE(), 'label' => 'labels.active'],
                    ['value' => BrandStatusEnum::INACTIVE(), 'label' => 'labels.inactive'],
                ],
            ],
            [
                'key' => 'scope_type',
                'label' => 'labels.scope_type',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => HomePageScopeEnum::GLOBAL(), 'label' => 'labels.global'],
                    ['value' => HomePageScopeEnum::CATEGORY(), 'label' => 'labels.category'],
                ],
            ],
            [
                'key' => 'scope_id',
                'label' => 'labels.scope_category',
                'type' => 'select_remote',
                'route' => 'admin.categories.search',
                'depends_on' => ['key' => 'scope_type', 'value' => HomePageScopeEnum::CATEGORY()],
            ],
        ];
    }

    public function availableMatchFields(): array
    {
        return [
            ['key' => 'uuid', 'label' => 'labels.uuid', 'column' => 'uuid'],
            ['key' => 'id', 'label' => 'labels.id', 'column' => 'id'],
            ['key' => 'slug', 'label' => 'labels.slug', 'column' => 'slug'],
            ['key' => 'title', 'label' => 'labels.title', 'column' => 'title'],
        ];
    }

    public function applyExportFilters($query, array $filters)
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['scope_type'])) {
            $query->where('scope_type', $filters['scope_type']);
        }
        if (! empty($filters['scope_id']) && is_numeric($filters['scope_id'])) {
            $query->where('scope_id', (int) $filters['scope_id']);
        }

        return $query;
    }

    public function availableFields(): array
    {
        $fields = [
            ['key' => 'title',           'label' => 'labels.brand_name',     'required' => true,  'type' => 'string'],
            ['key' => 'description',     'label' => 'labels.description',    'required' => true, 'type' => 'string'],
        ];

        $clientLanguages = TranslationHelper::getClientLanguages();
        $defaultLocale = TranslationHelper::getDefaultLocale();

        foreach ($clientLanguages as $lang) {
            if ($lang->code !== $defaultLocale) {
                $fields[] = ['key' => "title_{$lang->code}", 'label' => __('labels.brand_name') . " ({$lang->name})", 'required' => false, 'type' => 'string', 'locale' => $lang->code];
                $fields[] = ['key' => "description_{$lang->code}", 'label' => __('labels.description') . " ({$lang->name})", 'required' => false, 'type' => 'string', 'locale' => $lang->code];
            }
        }

        return array_merge($fields, [
            ['key' => 'status',          'label' => 'labels.status',         'required' => false, 'type' => 'enum'],
            ['key' => 'scope_type',      'label' => 'labels.scope_type',     'required' => false, 'type' => 'enum'],
            ['key' => 'scope_id',        'label' => 'labels.scope_category', 'required' => false, 'type' => 'integer'],
            ['key' => 'logo_url',        'label' => 'labels.logo_url',       'required' => false, 'type' => 'url'],
            ['key' => 'seo_title',       'label' => 'labels.seo_title',      'required' => false, 'type' => 'string'],
            ['key' => 'seo_keywords',    'label' => 'labels.seo_keywords',   'required' => false, 'type' => 'string'],
            ['key' => 'seo_description', 'label' => 'labels.seo_description', 'required' => false, 'type' => 'string'],
        ]);
    }

    public function validateMapping(array $mapping, array $headers): void
    {
        foreach ($mapping as $field => $header) {
            if ($header === null || $header === '') {
                continue;
            }
            if (! in_array($header, $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_unknown_csv_column');
            }
        }
    }

    public function processRow(array $row, BulkUploadJob $job): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $defaults = is_array($job->default_values) ? $job->default_values : [];

        $operation = $job->operation?->value ?? 'create';
        if ($operation === 'update') {
            return $this->processUpdateRow($row, $job, $mapping, $defaults);
        }

        if ($operation === 'translation') {
            return $this->processTranslationRow($row, $job, $mapping, $defaults);
        }

        return $this->processCreateRow($row, $job, $mapping, $defaults);
    }

    private function processCreateRow(array $row, BulkUploadJob $job, array $mapping, array $defaults): array
    {
        try {
            $attributes = $this->buildCreateAttributes($row, $mapping, $defaults);
            $defaultLocale = TranslationHelper::getDefaultLocale();
            $title = is_array($attributes['title'] ?? null)
                ? ($attributes['title'][$defaultLocale] ?? (reset($attributes['title']) ?: ''))
                : (string) ($attributes['title'] ?? '');

            $rules = $this->bulkCreateRules();
            $validator = Validator::make($attributes, $rules, $this->bulkValidatorMessages($title));
            if ($validator->fails()) {
                return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
            }

            $brand = $this->brandService->createBrand($attributes);

            $logoUrl = $this->stringFromRow($row, $mapping, 'logo_url');
            if ($logoUrl !== '') {
                $logoError = $this->brandService->downloadAndAttachLogoFromUrl($brand, $logoUrl);
                if ($logoError !== null) {
                    return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_row_created_without_logo', ['title' => $title, 'reason' => $logoError])];
                }
            }

            return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_row_created', ['title' => $title])];
        } catch (\Throwable $e) {
            Log::warning('BrandBulkImportService::processCreateRow failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function processUpdateRow(array $row, BulkUploadJob $job, array $mapping, array $defaults): array
    {
        $matchField = $job->match_field ?: 'id';
        $matchColumn = collect($this->availableMatchFields())
            ->firstWhere('key', $matchField)['column'] ?? $matchField;

        $matchValue = $this->stringFromRow($row, $mapping, $matchField);
        if ($matchValue === '') {
            return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_row_missing_match_value', ['field' => $matchField])];
        }

        try {
            $brand = $matchField === 'title'
                ? Brand::query()->where('title->en', $matchValue)->first()
                : Brand::where($matchColumn, $matchValue)->first();
            if (! $brand) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => $matchField, 'value' => $matchValue])];
            }

            [$diff, $changes] = $this->buildUpdateDiff($brand, $row, $mapping);

            $logoUrl = $this->stringFromRow($row, $mapping, 'logo_url');
            if (empty($diff) && $logoUrl === '') {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_changes', ['title' => $brand->title])];
            }

            if (! empty($diff)) {
                $rules = $this->bulkUpdateRules($brand->id, array_keys($diff));
                $titleForMsg = (string) ($brand->title ?? '');
                $validator = Validator::make($diff, $rules, $this->bulkValidatorMessages($titleForMsg));
                if ($validator->fails()) {
                    return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
                }
            }

            $this->brandService->updateBrand($brand, $diff);

            if ($logoUrl !== '') {
                $logoError = $this->brandService->downloadAndAttachLogoFromUrl($brand, $logoUrl);
                if ($logoError === null) {
                    $changes[] = 'logo';
                } else {
                    return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_row_updated_logo_skipped', [
                        'title' => $brand->title,
                        'fields' => implode(', ', $changes) ?: '—',
                        'reason' => $logoError,
                    ])];
                }
            }

            if (empty($changes)) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_changes', ['title' => $brand->title])];
            }

            return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_row_updated', [
                'title' => $brand->title,
                'fields' => implode(', ', $changes),
            ])];
        } catch (\Throwable $e) {
            Log::warning('BrandBulkImportService::processUpdateRow failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function buildCreateAttributes(array $row, array $mapping, array $defaults): array
    {
        $defaultLocale = TranslationHelper::getDefaultLocale();
        $titleArray = [];
        $descArray = [];

        $mainTitle = $this->stringFromRow($row, $mapping, 'title');
        if ($mainTitle !== '') {
            $titleArray[$defaultLocale] = $mainTitle;
        }

        $mainDesc = $this->stringFromRow($row, $mapping, 'description') ?: ($defaults['description'] ?? '');
        if ($mainDesc !== '') {
            $descArray[$defaultLocale] = $mainDesc;
        }

        // Collect other language columns
        $clientLanguages = TranslationHelper::getClientLanguages();
        foreach ($clientLanguages as $lang) {
            if ($lang->code === $defaultLocale) {
                continue;
            }
            $tVal = $this->stringFromRow($row, $mapping, "title_{$lang->code}");
            if ($tVal !== '') {
                $titleArray[$lang->code] = $tVal;
            }
            $dVal = $this->stringFromRow($row, $mapping, "description_{$lang->code}");
            if ($dVal !== '') {
                $descArray[$lang->code] = $dVal;
            }
        }

        $attributes = [
            'title' => ! empty($titleArray) ? $titleArray : $mainTitle,
            'description' => ! empty($descArray) ? $descArray : $mainDesc,
        ];

        $status = strtolower($this->stringFromRow($row, $mapping, 'status'));
        if ($status === '' && isset($defaults['status'])) {
            $status = strtolower((string) $defaults['status']);
        }
        $attributes['status'] = $status !== '' ? $status : BrandStatusEnum::INACTIVE();

        $scopeType = strtolower($this->stringFromRow($row, $mapping, 'scope_type'));
        if ($scopeType === '' && isset($defaults['scope_type'])) {
            $scopeType = strtolower((string) $defaults['scope_type']);
        }
        $attributes['scope_type'] = $scopeType !== '' ? $scopeType : HomePageScopeEnum::GLOBAL();

        $scopeIdRaw = $this->stringFromRow($row, $mapping, 'scope_id');
        if ($scopeIdRaw === '' && isset($defaults['scope_id'])) {
            $scopeIdRaw = (string) $defaults['scope_id'];
        }
        $attributes['scope_id'] = ($scopeIdRaw !== '' && ctype_digit($scopeIdRaw)) ? (int) $scopeIdRaw : null;

        $metadata = $this->buildSeoMetadata($row, $mapping, $defaults);
        if (! empty($metadata)) {
            $attributes['metadata'] = $metadata;
        }

        return $attributes;
    }

    private function buildUpdateDiff(Brand $brand, array $row, array $mapping): array
    {
        $diff = [];
        $changes = [];
        $defaultLocale = TranslationHelper::getDefaultLocale();

        $existingTitles = method_exists($brand, 'getTranslations') ? ($brand->getTranslations('title') ?: []) : [];
        $existingDescs = method_exists($brand, 'getTranslations') ? ($brand->getTranslations('description') ?: []) : [];

        $newTitles = [];
        $newDescs = [];

        $newTitle = $this->stringFromRow($row, $mapping, 'title');
        if ($newTitle !== '' && $newTitle !== (string) ($existingTitles[$defaultLocale] ?? $brand->title)) {
            $newTitles[$defaultLocale] = $newTitle;
            $changes[] = 'title';
        }

        $newDescription = $this->stringFromRow($row, $mapping, 'description');
        if ($newDescription !== '' && $newDescription !== (string) ($existingDescs[$defaultLocale] ?? $brand->description ?? '')) {
            $newDescs[$defaultLocale] = $newDescription;
            $changes[] = 'description';
        }

        $clientLanguages = TranslationHelper::getClientLanguages();
        foreach ($clientLanguages as $lang) {
            if ($lang->code === $defaultLocale) {
                continue;
            }
            $tVal = $this->stringFromRow($row, $mapping, "title_{$lang->code}");
            if ($tVal !== '' && $tVal !== (string) ($existingTitles[$lang->code] ?? '')) {
                $newTitles[$lang->code] = $tVal;
                $changes[] = "title ({$lang->code})";
            }

            $dVal = $this->stringFromRow($row, $mapping, "description_{$lang->code}");
            if ($dVal !== '' && $dVal !== (string) ($existingDescs[$lang->code] ?? '')) {
                $newDescs[$lang->code] = $dVal;
                $changes[] = "description ({$lang->code})";
            }
        }

        if (! empty($newTitles)) {
            $diff['title'] = array_merge($existingTitles, $newTitles);
        }

        if (! empty($newDescs)) {
            $diff['description'] = array_merge($existingDescs, $newDescs);
        }

        $rawStatus = strtolower($this->stringFromRow($row, $mapping, 'status'));
        if ($rawStatus !== '' && $rawStatus !== (string) ($brand->status?->value ?? $brand->status)) {
            $diff['status'] = $rawStatus;
            $changes[] = 'status';
        }

        $rawScopeType = strtolower($this->stringFromRow($row, $mapping, 'scope_type'));
        if ($rawScopeType !== '') {
            $currentScope = (string) ($brand->scope_type?->value ?? $brand->scope_type);
            if ($rawScopeType !== $currentScope) {
                $diff['scope_type'] = $rawScopeType;
                $changes[] = 'scope_type';
            }
            if ($rawScopeType === HomePageScopeEnum::CATEGORY()) {
                $rawScopeId = $this->stringFromRow($row, $mapping, 'scope_id');
                if ($rawScopeId !== '' && ctype_digit($rawScopeId) && (int) $rawScopeId !== (int) $brand->scope_id) {
                    $diff['scope_id'] = (int) $rawScopeId;
                    $changes[] = 'scope_id';
                }
            } elseif ($rawScopeType === HomePageScopeEnum::GLOBAL() && $brand->scope_id !== null) {
                $diff['scope_id'] = null;
                $changes[] = 'scope_id';
            }
        }

        $mergedMetadata = $this->mergeSeoMetadata($brand, $row, $mapping);
        if ($mergedMetadata !== null) {
            $diff['metadata'] = $mergedMetadata;
            $changes[] = 'seo';
        }

        return [$diff, $changes];
    }

    private function mergeSeoMetadata(Brand $brand, array $row, array $mapping): ?array
    {
        $current = is_array($brand->metadata) ? $brand->metadata : [];
        $next = $current;
        $changed = false;

        foreach (['seo_title' => 'seo_title', 'seo_description' => 'seo_description'] as $field => $key) {
            $val = $this->stringFromRow($row, $mapping, $field);
            if ($val !== '' && $val !== (string) ($current[$key] ?? '')) {
                $next[$key] = $val;
                $changed = true;
            }
        }
        $keywordsRaw = $this->stringFromRow($row, $mapping, 'seo_keywords');
        if ($keywordsRaw !== '') {
            $next['seo_keywords'] = array_values(array_filter(array_map('trim', explode(',', $keywordsRaw))));
            $existing = is_array($current['seo_keywords'] ?? null) ? $current['seo_keywords'] : [];
            if (array_values($existing) !== $next['seo_keywords']) {
                $changed = true;
            }
        }

        return $changed ? $next : null;
    }

    private function buildSeoMetadata(array $row, array $mapping, array $defaults): array
    {
        $metadata = [];
        $title = $this->stringFromRow($row, $mapping, 'seo_title') ?: ($defaults['seo_title'] ?? null);
        $description = $this->stringFromRow($row, $mapping, 'seo_description') ?: ($defaults['seo_description'] ?? null);
        $keywordsRaw = $this->stringFromRow($row, $mapping, 'seo_keywords') ?: ($defaults['seo_keywords'] ?? null);

        if ($title) {
            $metadata['seo_title'] = $title;
        }
        if ($description) {
            $metadata['seo_description'] = $description;
        }
        if ($keywordsRaw) {
            $keywords = is_array($keywordsRaw)
                ? $keywordsRaw
                : array_filter(array_map('trim', explode(',', (string) $keywordsRaw)));
            $metadata['seo_keywords'] = array_values($keywords);
        }

        return $metadata;
    }

    private function processTranslationRow(array $row, BulkUploadJob $job, array $mapping, array $defaults): array
    {
        $matchField = 'uuid';
        $matchValue = $this->stringFromRow($row, $mapping, 'uuid');

        if ($matchValue === '') {
            return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_row_missing_match_value', ['field' => $matchField])];
        }

        try {
            $brand = Brand::where('uuid', $matchValue)->first();
            if (! $brand) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => $matchField, 'value' => $matchValue])];
            }

            $targetLocale = $job->target_locale;
            $this->validateTranslationLocale((string) $targetLocale);

            $existingTitles = method_exists($brand, 'getTranslations') ? ($brand->getTranslations('title') ?: []) : [];
            $existingDescs = method_exists($brand, 'getTranslations') ? ($brand->getTranslations('description') ?: []) : [];

            $diff = [];
            $changes = [];

            // Check title for target locale
            $titleKey = "title_{$targetLocale}";
            $rawTitle = $this->stringFromRow($row, $mapping, $titleKey);

            if ($rawTitle !== '') {
                if ($rawTitle === '__DELETE__') {
                    if (isset($existingTitles[$targetLocale])) {
                        $diff['title'] = [$targetLocale => ''];
                        $changes[] = "title ({$targetLocale} removed)";
                    }
                } elseif ($rawTitle !== (string) ($existingTitles[$targetLocale] ?? '')) {
                    $nextTitles = $existingTitles;
                    $nextTitles[$targetLocale] = $rawTitle;
                    $diff['title'] = $nextTitles;
                    $changes[] = "title ({$targetLocale})";
                }
            }

            // Check description for target locale
            $descKey = "description_{$targetLocale}";
            $rawDesc = $this->stringFromRow($row, $mapping, $descKey);

            if ($rawDesc !== '') {
                if ($rawDesc === '__DELETE__') {
                    if (isset($existingDescs[$targetLocale])) {
                        $diff['description'] = [$targetLocale => ''];
                        $changes[] = "description ({$targetLocale} removed)";
                    }
                } elseif ($rawDesc !== (string) ($existingDescs[$targetLocale] ?? '')) {
                    $nextDescs = $existingDescs;
                    $nextDescs[$targetLocale] = $rawDesc;
                    $diff['description'] = $nextDescs;
                    $changes[] = "description ({$targetLocale})";
                }
            }

            if (empty($diff) || empty($changes)) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_changes', ['title' => $brand->title])];
            }

            $this->brandService->updateBrand($brand, $diff);

            return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_row_updated', [
                'title' => $brand->title,
                'fields' => implode(', ', $changes),
            ])];
        } catch (\Throwable $e) {
            Log::warning('BrandBulkImportService::processTranslationRow failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function bulkCreateRules(): array
    {
        $rules = StoreBrandRequest::baseRules();
        unset($rules['logo'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        return $rules;
    }

    private function bulkUpdateRules(int $brandId, array $changedKeys): array
    {
        $rules = UpdateBrandRequest::baseRules($brandId);
        unset($rules['logo'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        $kept = [];
        foreach ($rules as $key => $rule) {
            $head = explode('.', $key)[0];
            if (in_array($head, $changedKeys, true)) {
                $kept[$key] = $this->relaxRequired($rule);
            }
        }

        if (in_array('scope_type', $changedKeys, true) && ! isset($kept['scope_id']) && isset($rules['scope_id'])) {
            $kept['scope_id'] = $this->relaxRequired($rules['scope_id']);
        }

        return $kept;
    }

    private function bulkValidatorMessages(string $title): array
    {
        return [
            'title.required' => __('labels.bulk_upload_row_missing_title'),
            'title.unique' => __('labels.bulk_upload_title_already_exists', ['title' => $title]),
            'scope_id.required_if' => __('labels.bulk_upload_scope_id_required'),
            'scope_id.exists' => __('labels.bulk_upload_invalid_scope_category', ['id' => '']),
        ];
    }

    private function relaxRequired(array|string $rule): array|string
    {
        if (is_string($rule)) {
            return implode('|', array_filter(explode('|', $rule), fn ($r) => $r !== 'required'));
        }

        return array_values(array_filter($rule, fn ($r) => $r !== 'required'));
    }

    private function stringFromRow(array $row, array $mapping, string $field): string
    {
        $header = $mapping[$field] ?? null;
        if (! $header) {
            // Also check if field name is used directly as header
            $header = $field;
        }
        $value = $row[$header] ?? null;

        return is_string($value) ? trim($value) : (is_scalar($value) ? trim((string) $value) : '');
    }
}
