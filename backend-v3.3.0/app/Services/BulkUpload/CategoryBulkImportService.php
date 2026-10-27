<?php

namespace App\Services\BulkUpload;

use App\Enums\CategoryStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Helpers\TranslationHelper;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Interfaces\BulkImportable;
use App\Interfaces\TranslationBulkImportable;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Services\CategoryService;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use App\Services\SpatieMediaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Category-specific CSV import adapter for the generic bulk-upload pipeline.
 * It owns row mapping and update diffs; CategoryService owns persistence.
 */
class CategoryBulkImportService implements BulkImportable, TranslationBulkImportable
{
    public function __construct(protected CategoryService $categoryService)
    {
    }

    public function moduleKey(): string
    {
        return 'categories';
    }

    public function modelClass(): string
    {
        return Category::class;
    }

    public function csvTemplate(): array
    {
        $columns = ['title', 'parent_id', 'parent_title', 'description', 'status', 'requires_approval', 'commission', 'image_url', 'icon_url', 'active_icon_url', 'background_type', 'background_color_web', 'background_color_app', 'background_image_url', 'background_image_web_url', 'background_image_web_mobile_url', 'background_image_app_url', 'font_color', 'search_labels', 'seo_title', 'seo_keywords', 'seo_description'];
        $sample = ['Fruits', '', '', 'Fresh fruits', 'active', 'false', '5', 'https://example.com/category.jpg', 'https://example.com/icon.png', 'https://example.com/active-icon.png', 'color', '#FFFFFF', '#FFFFFF', '', '', '', '', '#000000', 'fruit,fresh,produce', 'Fresh Fruits', 'fruit,fresh,produce', 'Fresh fruit category.'];
        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code === 'en') {
                continue;
            }
            $columns[] = "title_{$language->code}";
            $columns[] = "description_{$language->code}";
            $sample[] = '';
            $sample[] = '';
        }

        return [
            'columns' => $columns,
            'sample' => $sample,
        ];
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

        return ['uuid', 'id', 'slug', 'parent_id', 'parent_title_en', 'title_en', 'description_en', "title_{$locale}", "description_{$locale}"];
    }

    public function translationExportRow($model, string $locale): array
    {
        $locale = app(LanguageService::class)->normalizeCode($locale);
        $this->validateTranslationLocale($locale);
        $titles = $model->getTranslations('title') ?: [];
        $descriptions = $model->getTranslations('description') ?: [];

        return [
            'uuid' => $model->uuid,
            'id' => $model->id,
            'slug' => $model->slug,
            'parent_id' => $model->parent_id,
            'parent_title_en' => $model->parent?->getTranslation('title', 'en', false) ?? '',
            'title_en' => $titles['en'] ?? '',
            'description_en' => $descriptions['en'] ?? '',
            "title_{$locale}" => $titles[$locale] ?? '',
            "description_{$locale}" => $descriptions[$locale] ?? '',
        ];
    }

    public function exportColumns(): array
    {
        $columns = ['id', 'title', 'parent_id', 'parent_title', 'description', 'status', 'requires_approval', 'commission', 'image_url', 'icon_url', 'active_icon_url', 'background_type', 'background_color_web', 'background_color_app', 'background_image_url', 'background_image_web_url', 'background_image_web_mobile_url', 'background_image_app_url', 'font_color', 'search_labels', 'seo_title', 'seo_keywords', 'seo_description', 'uuid'];
        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code !== 'en') {
                $columns[] = "title_{$language->code}";
                $columns[] = "description_{$language->code}";
            }
        }

        return $columns;
    }

    public function exportRow($model): array
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $keywords = $metadata['seo_keywords'] ?? null;
        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        $titles = $model->getTranslations('title') ?: [];
        $descriptions = $model->getTranslations('description') ?: [];
        $row = [
            'id' => $model->id,
            'title' => $titles['en'] ?? '',
            'parent_id' => $model->parent_id,
            'parent_title' => $model->parent?->getTranslation('title', 'en', false) ?? '',
            'description' => $descriptions['en'] ?? '',
            'uuid' => $model->uuid,
        ];
        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code !== 'en') {
                $row["title_{$language->code}"] = $titles[$language->code] ?? '';
                $row["description_{$language->code}"] = $descriptions[$language->code] ?? '';
            }
        }

        return array_merge($row, [
            'status' => is_string($model->status) ? $model->status : ($model->status?->value ?? ''),
            'requires_approval' => $model->requires_approval ? 'true' : 'false',
            'commission' => $model->commission,
            // Keep media URL columns blank on export so re-import preserves existing media unless admins paste new public URLs.
            'image_url' => '',
            'icon_url' => '',
            'active_icon_url' => '',
            'background_type' => is_string($model->background_type) ? $model->background_type : ($model->background_type?->value ?? ''),
            'background_color_web' => $model->background_color_web,
            'background_color_app' => $model->background_color_app,
            'background_image_url' => '',
            'background_image_web_url' => '',
            'background_image_web_mobile_url' => '',
            'background_image_app_url' => '',
            'font_color' => $model->font_color,
            'search_labels' => implode(',', $model->searchLabelSetting?->search_labels ?? []),
            'seo_title' => $metadata['seo_title'] ?? '',
            'seo_keywords' => $keywords ?: '',
            'seo_description' => $metadata['seo_description'] ?? '',
        ]);
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
                    ['value' => CategoryStatusEnum::ACTIVE(), 'label' => 'labels.active'],
                    ['value' => CategoryStatusEnum::INACTIVE(), 'label' => 'labels.inactive'],
                ],
            ],
            [
                'key' => 'parent_id',
                'label' => 'labels.parent',
                'type' => 'select_remote',
                'route' => 'admin.categories.search',
            ],
        ];
    }

    public function applyExportFilters($query, array $filters)
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (array_key_exists('parent_id', $filters) && $filters['parent_id'] !== '') {
            is_numeric($filters['parent_id'])
                ? $query->where('parent_id', (int) $filters['parent_id'])
                : $query->whereNull('parent_id');
        }

        return $query->with(['parent', 'searchLabelSetting']);
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

    /**
     * @return array<int, array{key:string,label:string,required:bool,type:string}>
     */
    public function availableFields(): array
    {
        $fields = [
            ['key' => 'uuid', 'label' => 'labels.uuid', 'required' => false, 'type' => 'string'],
            ['key' => 'title', 'label' => 'labels.title', 'required' => true, 'type' => 'string'],
            ['key' => 'parent_id', 'label' => 'labels.parent_id', 'required' => false, 'type' => 'integer'],
            ['key' => 'parent_title', 'label' => 'labels.parent', 'required' => false, 'type' => 'string'],
            ['key' => 'description', 'label' => 'labels.description', 'required' => false, 'type' => 'string'],
        ];
        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code !== 'en') {
                $fields[] = ['key' => "title_{$language->code}", 'label' => __('labels.title') . " ({$language->name})", 'required' => false, 'type' => 'string', 'locale' => $language->code];
                $fields[] = ['key' => "description_{$language->code}", 'label' => __('labels.description') . " ({$language->name})", 'required' => false, 'type' => 'string', 'locale' => $language->code];
            }
        }

        return array_merge($fields, [
            ['key' => 'status', 'label' => 'labels.status', 'required' => false, 'type' => 'enum'],
            ['key' => 'requires_approval', 'label' => 'labels.requires_approval', 'required' => false, 'type' => 'boolean'],
            ['key' => 'commission', 'label' => 'labels.commission', 'required' => false, 'type' => 'decimal'],
            ['key' => 'image_url', 'label' => 'labels.image_url', 'required' => false, 'type' => 'url'],
            ['key' => 'icon_url', 'label' => 'labels.icon_url', 'required' => false, 'type' => 'url'],
            ['key' => 'active_icon_url', 'label' => 'labels.active_icon_url', 'required' => false, 'type' => 'url'],
            ['key' => 'background_type', 'label' => 'labels.background_type', 'required' => false, 'type' => 'enum'],
            ['key' => 'background_color_web', 'label' => 'labels.background_color_web', 'required' => false, 'type' => 'string'],
            ['key' => 'background_color_app', 'label' => 'labels.background_color_app', 'required' => false, 'type' => 'string'],
            ['key' => 'background_image_url', 'label' => 'labels.background_image_url', 'required' => false, 'type' => 'url'],
            ['key' => 'background_image_web_url', 'label' => 'labels.background_image_web_url', 'required' => false, 'type' => 'url'],
            ['key' => 'background_image_web_mobile_url', 'label' => 'labels.background_image_web_mobile_url', 'required' => false, 'type' => 'url'],
            ['key' => 'background_image_app_url', 'label' => 'labels.background_image_app_url', 'required' => false, 'type' => 'url'],
            ['key' => 'font_color', 'label' => 'labels.font_color', 'required' => false, 'type' => 'string'],
            ['key' => 'search_labels', 'label' => 'labels.search_labels', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_title', 'label' => 'labels.seo_title', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_keywords', 'label' => 'labels.seo_keywords', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_description', 'label' => 'labels.seo_description', 'required' => false, 'type' => 'string'],
        ]);
    }

    public function validateMapping(array $mapping, array $headers): void
    {
        foreach ($mapping as $header) {
            if ($header !== null && $header !== '' && ! in_array($header, $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_unknown_csv_column');
            }
        }
    }

    public function processRow(array $row, BulkUploadJob $job): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $defaults = is_array($job->default_values) ? $job->default_values : [];

        return match ($job->operation?->value ?? 'create') {
            'update' => $this->processUpdateRow($row, $job, $mapping),
            'translation' => $this->processTranslationRow($row, $job, $mapping),
            default => $this->processCreateRow($row, $job, $mapping, $defaults),
        };
    }

    private function processCreateRow(array $row, BulkUploadJob $job, array $mapping, array $defaults): array
    {
        try {
            $attributes = $this->buildCreateAttributes($row, $mapping, $defaults);
            $title = (string) data_get($attributes, 'title.en', '');
            $searchLabels = $this->searchLabelsFromRow($row, $mapping);

            $validator = Validator::make($attributes, $this->bulkCreateRules(), $this->bulkValidatorMessages($title));
            if ($validator->fails()) {
                return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
            }

            $category = $this->categoryService->createCategory($attributes, $searchLabels);
            $mediaResult = $this->attachMediaFromRow($category, $row, $mapping);

            if ($mediaResult !== null) {
                return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_category_row_created_with_media_skipped', [
                    'title' => $title,
                    'reason' => $mediaResult,
                ])];
            }

            return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_category_row_created', ['title' => $title])];
        } catch (\Throwable $e) {
            Log::warning('CategoryBulkImportService::processCreateRow failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function processUpdateRow(array $row, BulkUploadJob $job, array $mapping): array
    {
        $matchField = $job->match_field ?: 'id';
        $matchColumn = collect($this->availableMatchFields())->firstWhere('key', $matchField)['column'] ?? $matchField;
        $matchValue = $this->stringFromRow($row, $mapping, $matchField);

        if ($matchValue === '') {
            return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_row_missing_match_value', ['field' => $matchField])];
        }

        try {
            $category = $matchField === 'title'
                ? Category::query()->whereEnglishTitle($matchValue)->first()
                : Category::query()->where($matchColumn, $matchValue)->first();
            if (! $category) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => $matchField, 'value' => $matchValue])];
            }

            [$diff, $changes] = $this->buildUpdateDiff($category, $row, $mapping);
            $searchLabels = $this->hasMappedValue($row, $mapping, 'search_labels')
                ? $this->searchLabelsFromRow($row, $mapping)
                : null;
            $hasMedia = $this->hasMappedMediaValue($row, $mapping);

            if (empty($diff) && $searchLabels === null && ! $hasMedia) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_category_row_no_changes', ['title' => $category->getTranslation('title', 'en', false)])];
            }

            if (! empty($diff)) {
                $validator = Validator::make(
                    $diff,
                    $this->bulkUpdateRules($category->id, array_keys($diff)),
                    $this->bulkValidatorMessages((string) data_get($diff, 'title.en', $category->getTranslation('title', 'en', false))),
                );

                if ($validator->fails()) {
                    return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
                }
            }

            $this->categoryService->updateCategory($category, $diff, $searchLabels);

            if ($searchLabels !== null) {
                $changes[] = 'search_labels';
            }

            $mediaResult = null;
            if ($hasMedia) {
                $mediaResult = $this->attachMediaFromRow($category, $row, $mapping);
                $changes[] = 'media';
            }

            if ($mediaResult !== null) {
                return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_category_row_updated_media_skipped', [
                    'title' => $category->getTranslation('title', 'en', false),
                    'fields' => implode(', ', array_unique($changes)),
                    'reason' => $mediaResult,
                ])];
            }

            return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_category_row_updated', [
                'title' => $category->getTranslation('title', 'en', false),
                'fields' => implode(', ', array_unique($changes)),
            ])];
        } catch (\Throwable $e) {
            Log::warning('CategoryBulkImportService::processUpdateRow failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function processTranslationRow(array $row, BulkUploadJob $job, array $mapping): array
    {
        $locale = app(LanguageService::class)->normalizeCode((string) $job->target_locale);

        try {
            $this->validateTranslationLocale($locale);
            $uuid = $this->stringFromRow($row, $mapping, 'uuid');
            if ($uuid === '') {
                return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_row_missing_match_value', ['field' => 'uuid'])];
            }

            $category = Category::query()->where('uuid', $uuid)->first();
            if (! $category) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => 'uuid', 'value' => $uuid])];
            }

            $attributes = [];
            $changes = [];
            foreach (['title' => 255, 'description' => 10000] as $field => $limit) {
                $value = $this->stringFromRow($row, $mapping, "{$field}_{$locale}");
                if ($value === '') {
                    continue;
                }

                if ($value === '__DELETE__') {
                    if (! array_key_exists($locale, $category->getTranslations($field))) {
                        continue;
                    }
                    $attributes[$field] = [$locale => ''];
                    $changes[] = $field;

                    continue;
                }

                if (mb_strlen($value) > $limit) {
                    return [BulkImportable::OUTCOME_FAILED, __('validation.max.string', ['attribute' => $field, 'max' => $limit])];
                }

                if (($category->getTranslations($field)[$locale] ?? null) !== $value) {
                    $attributes[$field] = [$locale => $value];
                    $changes[] = $field;
                }
            }

            if ($attributes === []) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_category_row_no_changes', ['title' => $category->getTranslation('title', 'en', false)])];
            }

            $this->categoryService->updateCategory($category, $attributes);

            return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_category_row_updated', [
                'title' => $category->getTranslation('title', 'en', false),
                'fields' => implode(', ', array_unique($changes)),
            ])];
        } catch (\Throwable $e) {
            Log::warning('CategoryBulkImportService::processTranslationRow failed', [
                'job_id' => $job->id,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return [BulkImportable::OUTCOME_FAILED, $e->getMessage()];
        }
    }

    private function buildCreateAttributes(array $row, array $mapping, array $defaults): array
    {
        $titles = [];
        $descriptions = [];
        $englishTitle = $this->stringFromRow($row, $mapping, 'title');
        $englishDescription = $this->stringFromRow($row, $mapping, 'description');
        if ($englishTitle !== '') {
            $titles['en'] = $englishTitle;
        }
        if ($englishDescription !== '') {
            $descriptions['en'] = $englishDescription;
        }
        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code === 'en') {
                continue;
            }
            $title = $this->stringFromRow($row, $mapping, "title_{$language->code}");
            $description = $this->stringFromRow($row, $mapping, "description_{$language->code}");
            if ($title !== '') {
                $titles[$language->code] = $title;
            }
            if ($description !== '') {
                $descriptions[$language->code] = $description;
            }
        }

        $attributes = [
            'title' => $titles,
            'description' => $descriptions === [] ? ($defaults['description'] ?? null) : $descriptions,
            'status' => $this->stringFromRow($row, $mapping, 'status') ?: ($defaults['status'] ?? CategoryStatusEnum::INACTIVE()),
            'requires_approval' => $this->stringFromRow($row, $mapping, 'requires_approval') ?: ($defaults['requires_approval'] ?? false),
            'commission' => $this->stringFromRow($row, $mapping, 'commission') ?: ($defaults['commission'] ?? null),
            'background_type' => $this->stringFromRow($row, $mapping, 'background_type') ?: ($defaults['background_type'] ?? null),
            'background_color_web' => $this->stringFromRow($row, $mapping, 'background_color_web') ?: ($defaults['background_color_web'] ?? null),
            'background_color_app' => $this->stringFromRow($row, $mapping, 'background_color_app') ?: ($defaults['background_color_app'] ?? null),
            'font_color' => $this->stringFromRow($row, $mapping, 'font_color') ?: ($defaults['font_color'] ?? null),
        ];

        $attributes['parent_id'] = $this->resolveParentId($row, $mapping, $englishTitle);
        $metadata = $this->buildSeoMetadata($row, $mapping, $defaults);
        if (! empty($metadata)) {
            $attributes['metadata'] = $metadata;
        }

        return $attributes;
    }

    /**
     * Empty mapped cells are intentionally ignored here so Update mode preserves
     * the existing category value unless the CSV provides a replacement.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>}
     */
    private function buildUpdateDiff(Category $category, array $row, array $mapping): array
    {
        $diff = [];
        $changes = [];
        $existingTitles = $category->getTranslations('title') ?: [];
        $existingDescriptions = $category->getTranslations('description') ?: [];
        $titleChanges = [];
        $descriptionChanges = [];

        $englishTitle = $this->stringFromRow($row, $mapping, 'title');
        if ($englishTitle !== '' && $englishTitle !== ($existingTitles['en'] ?? '')) {
            $titleChanges['en'] = $englishTitle;
        }
        $englishDescription = $this->stringFromRow($row, $mapping, 'description');
        if ($englishDescription !== '' && $englishDescription !== ($existingDescriptions['en'] ?? '')) {
            $descriptionChanges['en'] = $englishDescription;
        }
        foreach (TranslationHelper::getClientLanguages() as $language) {
            if ($language->code === 'en') {
                continue;
            }
            $title = $this->stringFromRow($row, $mapping, "title_{$language->code}");
            $description = $this->stringFromRow($row, $mapping, "description_{$language->code}");
            if ($title !== '' && $title !== ($existingTitles[$language->code] ?? '')) {
                $titleChanges[$language->code] = $title;
            }
            if ($description !== '' && $description !== ($existingDescriptions[$language->code] ?? '')) {
                $descriptionChanges[$language->code] = $description;
            }
        }

        if ($titleChanges !== []) {
            $diff['title'] = $titleChanges;
            $changes[] = 'title';
        }
        if ($descriptionChanges !== []) {
            $diff['description'] = $descriptionChanges;
            $changes[] = 'description';
        }

        $fontColor = $this->stringFromRow($row, $mapping, 'font_color');
        if ($fontColor !== '' && $fontColor !== (string) ($category->font_color ?? '')) {
            $diff['font_color'] = $fontColor;
            $changes[] = 'font_color';
        }

        $parentId = $this->resolveParentId($row, $mapping, $existingTitles['en'] ?? '', $category->id);
        if ($parentId !== null && (int) $parentId !== (int) $category->parent_id) {
            $diff['parent_id'] = $parentId;
            $changes[] = 'parent_id';
        }

        $status = strtolower($this->stringFromRow($row, $mapping, 'status'));
        if ($status !== '' && $status !== (string) $category->status) {
            $diff['status'] = $status;
            $changes[] = 'status';
        }

        if ($this->hasMappedValue($row, $mapping, 'requires_approval')) {
            $requiresApproval = filter_var($this->stringFromRow($row, $mapping, 'requires_approval'), FILTER_VALIDATE_BOOLEAN);
            if ($requiresApproval !== (bool) $category->requires_approval) {
                $diff['requires_approval'] = $requiresApproval;
                $changes[] = 'requires_approval';
            }
        }

        $commission = $this->stringFromRow($row, $mapping, 'commission');
        if ($commission !== '' && (float) $commission !== (float) $category->commission) {
            $diff['commission'] = $commission;
            $changes[] = 'commission';
        }

        $backgroundType = strtolower($this->stringFromRow($row, $mapping, 'background_type'));
        $currentBackgroundType = $this->backgroundTypeValue($category);
        if ($backgroundType !== '' && $backgroundType !== $currentBackgroundType) {
            $diff['background_type'] = $backgroundType;
            $changes[] = 'background_type';
        }

        foreach (['background_color_web', 'background_color_app'] as $field) {
            $value = $this->stringFromRow($row, $mapping, $field);
            if ($value !== '' && $value !== (string) ($category->{$field} ?? '')) {
                $diff[$field] = $value;
                $changes[] = $field;
            }
        }

        $mergedMetadata = $this->mergeSeoMetadata($category, $row, $mapping);
        if ($mergedMetadata !== null) {
            $diff['metadata'] = $mergedMetadata;
            $changes[] = 'seo';
        }

        return [$diff, $changes];
    }

    private function resolveParentId(array $row, array $mapping, string $title, ?int $selfId = null): ?int
    {
        $parentIdRaw = $this->stringFromRow($row, $mapping, 'parent_id');
        if ($parentIdRaw !== '') {
            if (! ctype_digit($parentIdRaw)) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_category_invalid_parent'));
            }
            $parentId = (int) $parentIdRaw;
            if ($selfId !== null && $parentId === $selfId) {
                throw new \InvalidArgumentException(__('labels.parent_cannot_be_self'));
            }

            return Category::query()->whereKey($parentId)->exists()
                ? $parentId
                : throw new \InvalidArgumentException(__('labels.bulk_upload_category_invalid_parent'));
        }

        $parentTitle = $this->stringFromRow($row, $mapping, 'parent_title');
        if ($parentTitle === '') {
            return null;
        }
        if (strcasecmp($parentTitle, $title) === 0) {
            throw new \InvalidArgumentException(__('labels.parent_cannot_be_self'));
        }

        $parent = Category::query()->whereEnglishTitle($parentTitle)->first();
        if (! $parent) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_category_invalid_parent'));
        }

        return $parent?->id;
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
            $metadata['seo_keywords'] = array_values(array_filter(array_map('trim', explode(',', (string) $keywordsRaw))));
        }

        return $metadata;
    }

    private function mergeSeoMetadata(Category $category, array $row, array $mapping): ?array
    {
        $current = is_array($category->metadata) ? $category->metadata : [];
        $next = $current;
        $changed = false;

        foreach (['seo_title' => 'seo_title', 'seo_description' => 'seo_description'] as $field => $key) {
            $value = $this->stringFromRow($row, $mapping, $field);
            if ($value !== '' && $value !== (string) ($current[$key] ?? '')) {
                $next[$key] = $value;
                $changed = true;
            }
        }

        $keywordsRaw = $this->stringFromRow($row, $mapping, 'seo_keywords');
        if ($keywordsRaw !== '') {
            $next['seo_keywords'] = array_values(array_filter(array_map('trim', explode(',', $keywordsRaw))));
            $existing = is_array($current['seo_keywords'] ?? null) ? $current['seo_keywords'] : [];
            $changed = $changed || array_values($existing) !== $next['seo_keywords'];
        }

        return $changed ? $next : null;
    }

    private function bulkCreateRules(): array
    {
        $rules = StoreCategoryRequest::baseRules();
        foreach (['image', 'icon', 'active_icon', 'background_image', 'background_image_web', 'background_image_web_mobile', 'background_image_app', 'background_animation', 'background_animation_web', 'background_animation_app', 'meta_title', 'meta_keywords', 'meta_description', 'searchLabels', 'searchLabels.*'] as $key) {
            unset($rules[$key]);
        }

        return $rules;
    }

    /**
     * @param  array<int, string>  $changedKeys
     * @return array<string, mixed>
     */
    private function bulkUpdateRules(int $categoryId, array $changedKeys): array
    {
        $rules = UpdateCategoryRequest::baseRules($categoryId);
        foreach (['image', 'icon', 'active_icon', 'background_image', 'background_image_web', 'background_image_web_mobile', 'background_image_app', 'background_animation', 'background_animation_web', 'background_animation_app', 'meta_title', 'meta_keywords', 'meta_description', 'searchLabels', 'searchLabels.*'] as $key) {
            unset($rules[$key]);
        }

        $kept = [];
        foreach ($rules as $key => $rule) {
            if (in_array(explode('.', $key)[0], $changedKeys, true)) {
                $kept[$key] = $this->relaxRequired($rule);
            }
        }

        return $kept;
    }

    private function bulkValidatorMessages(string $title): array
    {
        return [
            'title.required' => __('labels.bulk_upload_row_missing_title'),
            'title.unique' => __('labels.bulk_upload_category_title_already_exists', ['title' => $title]),
            'parent_id.exists' => __('labels.bulk_upload_category_invalid_parent'),
        ];
    }

    private function attachMediaFromRow(Category $category, array $row, array $mapping): ?string
    {
        foreach ($this->mediaUrlFields() as $field => $collectionName) {
            $url = $this->stringFromRow($row, $mapping, $field);
            if ($url === '') {
                continue;
            }

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return __('labels.bulk_upload_media_invalid_url', ['field' => $field]);
            }

            try {
                SpatieMediaService::uploadFromUrl($category, $url, $collectionName);
            } catch (\Throwable $e) {
                Log::warning('CategoryBulkImportService::attachMediaFromRow failed', [
                    'category_id' => $category->id,
                    'field' => $field,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                return $e->getMessage();
            }
        }

        return null;
    }

    private function hasMappedMediaValue(array $row, array $mapping): bool
    {
        foreach (array_keys($this->mediaUrlFields()) as $field) {
            if ($this->hasMappedValue($row, $mapping, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function mediaUrlFields(): array
    {
        return [
            'image_url' => SpatieMediaCollectionName::CATEGORY_IMAGE(),
            'icon_url' => SpatieMediaCollectionName::CATEGORY_ICON(),
            'active_icon_url' => SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON(),
            'background_image_url' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE(),
            'background_image_web_url' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB(),
            'background_image_web_mobile_url' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE(),
            'background_image_app_url' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP(),
        ];
    }

    private function searchLabelsFromRow(array $row, array $mapping): array
    {
        $raw = $this->stringFromRow($row, $mapping, 'search_labels');

        return $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function hasMappedValue(array $row, array $mapping, string $field): bool
    {
        $header = $mapping[$field] ?? null;

        return $header && trim((string) ($row[$header] ?? '')) !== '';
    }

    private function relaxRequired(array|string $rule): array|string
    {
        if (is_string($rule)) {
            return implode('|', array_filter(explode('|', $rule), fn ($part) => $part !== 'required'));
        }

        return array_values(array_filter($rule, fn ($part) => $part !== 'required'));
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

    private function backgroundTypeValue(Category $category): string
    {
        return is_string($category->background_type)
            ? $category->background_type
            : (string) ($category->background_type?->value ?? '');
    }
}
