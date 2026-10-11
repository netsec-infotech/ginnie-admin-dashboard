<?php

namespace App\Services\BulkUpload;

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Interfaces\BulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Services\BrandService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Brand-specific bulk import. Owns CSV → attributes translation and the
 * preserve-on-empty diff semantic for Update mode. Validation rules and
 * persistence are NOT reimplemented here — they're borrowed from the FormRequests
 * (StoreBrandRequest / UpdateBrandRequest) and from BrandService respectively,
 * so there's a single source of truth for both.
 */
class BrandBulkImportService implements BulkImportable
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
        return [
            'columns' => ['title', 'description', 'status', 'scope_type', 'scope_id', 'logo_url', 'seo_title', 'seo_keywords', 'seo_description'],
            'sample' => ['Nike', 'Sports brand', 'active', 'global', '', 'https://example.com/nike.png', 'Nike — Sports gear', 'sports,shoes,apparel', 'Premium sports brand.'],
        ];
    }

    /**
     * Export column order — leads with `id` so a re-import maps cleanly to Update mode.
     */
    public function exportColumns(): array
    {
        return ['id', 'title', 'description', 'status', 'scope_type', 'scope_id', 'logo_url', 'seo_title', 'seo_keywords', 'seo_description'];
    }

    /**
     * Flatten a Brand into the export row. `logo_url` is left empty when a logo
     * is attached so that re-importing in Update mode leaves the existing logo
     * unchanged (empty cell = leave as-is). SEO metadata is flattened back to
     * its three discrete columns.
     */
    public function exportRow($model): array
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $keywords = $metadata['seo_keywords'] ?? null;
        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        return [
            'id' => $model->id,
            'title' => $model->title,
            'description' => $model->description,
            'status' => is_string($model->status) ? $model->status : ($model->status?->value ?? ''),
            'scope_type' => is_string($model->scope_type) ? $model->scope_type : ($model->scope_type?->value ?? ''),
            'scope_id' => $model->scope_id,
            'logo_url' => '', // see method doc — empty preserves the existing logo on re-import
            'seo_title' => $metadata['seo_title'] ?? '',
            'seo_keywords' => $keywords ?: '',
            'seo_description' => $metadata['seo_description'] ?? '',
        ];
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

    /**
     * Fields the admin can match on in Update mode. `id` is the safest choice
     * because it's stable; `title` is more human-friendly but breaks if the
     * brand is renamed between export and re-import.
     */
    public function availableMatchFields(): array
    {
        return [
            ['key' => 'id', 'label' => 'labels.id', 'column' => 'id'],
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

    /**
     * @return array<int, array{key:string,label:string,required:bool,type:string}>
     */
    public function availableFields(): array
    {
        return [
            ['key' => 'title',           'label' => 'labels.brand_name',     'required' => true,  'type' => 'string'],
            ['key' => 'description',     'label' => 'labels.description',    'required' => false, 'type' => 'string'],
            ['key' => 'status',          'label' => 'labels.status',         'required' => false, 'type' => 'enum'],
            ['key' => 'scope_type',      'label' => 'labels.scope_type',     'required' => false, 'type' => 'enum'],
            ['key' => 'scope_id',        'label' => 'labels.scope_category', 'required' => false, 'type' => 'integer'],
            ['key' => 'logo_url',        'label' => 'labels.logo_url',       'required' => false, 'type' => 'url'],
            ['key' => 'seo_title',       'label' => 'labels.seo_title',      'required' => false, 'type' => 'string'],
            ['key' => 'seo_keywords',    'label' => 'labels.seo_keywords',   'required' => false, 'type' => 'string'],
            ['key' => 'seo_description', 'label' => 'labels.seo_description', 'required' => false, 'type' => 'string'],
        ];
    }

    public function validateMapping(array $mapping, array $headers): void
    {
        $title = $mapping['title'] ?? null;
        if (! $title || ! in_array($title, $headers, true)) {
            throw new \InvalidArgumentException('labels.bulk_upload_title_mapping_required');
        }

        foreach ($mapping as $field => $header) {
            if ($header === null || $header === '') {
                continue;
            }
            if (! in_array($header, $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_unknown_csv_column');
            }
        }
    }

    /**
     * @param  array<string,mixed>  $row    csv_header => value
     */
    public function processRow(array $row, BulkUploadJob $job): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $defaults = is_array($job->default_values) ? $job->default_values : [];

        $operation = $job->operation?->value ?? 'create';
        if ($operation === 'update') {
            return $this->processUpdateRow($row, $job, $mapping, $defaults);
        }

        return $this->processCreateRow($row, $job, $mapping, $defaults);
    }

    /**
     * CREATE branch: build the attributes payload, validate via the shared
     * StoreBrandRequest rules (logo + description carved out — bulk treats
     * logo as an optional URL and lets unmapped description fall through to
     * an empty string), then hand persistence to BrandService.
     */
    private function processCreateRow(array $row, BulkUploadJob $job, array $mapping, array $defaults): array
    {
        try {
            $attributes = $this->buildCreateAttributes($row, $mapping, $defaults);
            $title = (string) ($attributes['title'] ?? '');

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
                    // Soft-fail: brand created, logo skipped.
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

    /**
     * UPDATE branch: find a brand by match_field, compute the diff against the
     * current model (preserve-on-empty), validate just that subset via the
     * shared UpdateBrandRequest rules, then hand persistence to BrandService.
     * Empty cells leave fields unchanged.
     */
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
            $brand = Brand::where($matchColumn, $matchValue)->first();
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
                $titleForMsg = (string) ($diff['title'] ?? $brand->title);
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

    /**
     * Transform CSV row + mapping into the `$attributes` shape BrandService
     * expects. Defaults (status → INACTIVE, scope_type → GLOBAL) are baked in
     * here so the validator sees a complete payload.
     *
     * @return array<string, mixed>
     */
    private function buildCreateAttributes(array $row, array $mapping, array $defaults): array
    {
        $attributes = [
            'title' => $this->stringFromRow($row, $mapping, 'title'),
            'description' => $this->stringFromRow($row, $mapping, 'description') ?: ($defaults['description'] ?? ''),
        ];

        // Status — mapped value wins; falls back to defaults; finally INACTIVE.
        $status = strtolower($this->stringFromRow($row, $mapping, 'status'));
        if ($status === '' && isset($defaults['status'])) {
            $status = strtolower((string) $defaults['status']);
        }
        $attributes['status'] = $status !== '' ? $status : BrandStatusEnum::INACTIVE();

        // Scope — mapped value wins; falls back to defaults; finally GLOBAL.
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

        // SEO — assemble flat seo_* columns into the nested metadata structure
        // matching what the FormRequest expects.
        $metadata = $this->buildSeoMetadata($row, $mapping, $defaults);
        if (! empty($metadata)) {
            $attributes['metadata'] = $metadata;
        }

        return $attributes;
    }

    /**
     * Compute the "what should change" payload for an update. Only fields that
     * are mapped, non-empty, AND different from the current model value land
     * here. SEO metadata gets a special "merge, don't clobber" pass.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [diff, changedFieldNames]
     */
    private function buildUpdateDiff(Brand $brand, array $row, array $mapping): array
    {
        $diff = [];
        $changes = [];

        $newTitle = $this->stringFromRow($row, $mapping, 'title');
        if ($newTitle !== '' && $newTitle !== (string) $brand->title) {
            $diff['title'] = $newTitle;
            $changes[] = 'title';
        }

        $newDescription = $this->stringFromRow($row, $mapping, 'description');
        if ($newDescription !== '' && $newDescription !== (string) ($brand->description ?? '')) {
            $diff['description'] = $newDescription;
            $changes[] = 'description';
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

    /**
     * Compute the new metadata payload for an update if any SEO field was mapped+set.
     * Returns null when nothing changed.
     */
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

    /**
     * Assemble flat seo_* CSV columns into the nested metadata.seo_* shape that
     * the FormRequest rules expect.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Validation rules for bulk CREATE — borrows from StoreBrandRequest and
     * tailors for bulk semantics (logo carved out; description rule dropped
     * because admins may not map the column).
     *
     * @return array<string, mixed>
     */
    private function bulkCreateRules(): array
    {
        $rules = StoreBrandRequest::baseRules();
        unset($rules['logo'], $rules['description'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        return $rules;
    }

    /**
     * Validation rules for bulk UPDATE — borrows from UpdateBrandRequest and
     * keeps only the rules for fields actually in the diff. Cross-field rules
     * (e.g. `scope_id` required_if `scope_type=category`) survive because
     * they fire from the value of $diff at runtime.
     *
     * @param  array<int, string>  $changedKeys
     * @return array<string, mixed>
     */
    private function bulkUpdateRules(int $brandId, array $changedKeys): array
    {
        $rules = UpdateBrandRequest::baseRules($brandId);
        unset($rules['logo'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        $kept = [];
        foreach ($rules as $key => $rule) {
            $head = explode('.', $key)[0];
            if (in_array($head, $changedKeys, true)) {
                // For diff-driven validation we relax `required` — the field is
                // present in $diff by construction. The cross-field constraint
                // (e.g. required_if) stays meaningful via the other field's
                // value in $diff.
                $kept[$key] = $this->relaxRequired($rule);
            }
        }

        // If scope_type is changing, keep scope_id rule to enforce required_if.
        if (in_array('scope_type', $changedKeys, true) && ! isset($kept['scope_id']) && isset($rules['scope_id'])) {
            $kept['scope_id'] = $this->relaxRequired($rules['scope_id']);
        }

        return $kept;
    }

    /**
     * Custom error messages handed to Validator::make. Replaces Laravel's
     * generic phrasings ("The title has already been taken.") with the
     * bulk-flavoured ones that include the row's title — clients reading the
     * job's error_log can identify which row failed without cross-referencing
     * the row number against the original CSV.
     *
     * @return array<string, string>
     */
    private function bulkValidatorMessages(string $title): array
    {
        return [
            'title.required' => __('labels.bulk_upload_row_missing_title'),
            'title.unique' => __('labels.bulk_upload_title_already_exists', ['title' => $title]),
            'scope_id.required_if' => __('labels.bulk_upload_scope_id_required'),
            'scope_id.exists' => __('labels.bulk_upload_invalid_scope_category', ['id' => '']),
        ];
    }

    /**
     * Strip plain `required` from a rule set so the diff-driven update doesn't
     * fail just because a field is "required" in the FormRequest's full form
     * payload. `required_if` and friends are preserved because they're
     * cross-field.
     */
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
            return '';
        }
        $value = $row[$header] ?? null;
        return is_string($value) ? trim($value) : (is_scalar($value) ? trim((string) $value) : '');
    }
}
