<?php

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Interfaces\BulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Services\BulkUpload\BrandBulkImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeJob(array $mapping, array $defaults = [], string $operation = 'create', ?string $matchField = null): BulkUploadJob
{
    return new BulkUploadJob([
        'uploadable_type' => Brand::class,
        'column_mapping' => $mapping,
        'default_values' => $defaults,
        'total_rows' => 1,
        'operation' => $operation,
        'match_field' => $matchField,
    ]);
}

it('creates a brand from a mapped row', function () {
    $importer = app(BrandBulkImportService::class);
    $job = makeJob(['title' => 'name', 'description' => 'desc']);

    [$outcome, $msg] = $importer->processRow(['name' => 'Acme', 'desc' => 'Test brand'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_CREATED);
    expect(Brand::where('title', 'Acme')->exists())->toBeTrue();
    expect($msg)->toContain('Acme');
});

it('fails rows when title is empty in create mode', function () {
    $importer = app(BrandBulkImportService::class);
    $job = makeJob(['title' => 'name']);

    [$outcome, $msg] = $importer->processRow(['name' => ''], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_FAILED);
    expect($msg)->not->toBeEmpty();
});

it('rejects duplicate brand titles', function () {
    Brand::create(['title' => 'DuplicateBrand', 'status' => BrandStatusEnum::ACTIVE()]);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(['title' => 'name']);

    [$outcome, $msg] = $importer->processRow(['name' => 'DuplicateBrand'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_FAILED);
    expect($msg)->toContain('already exists');
});

it('flattens seo columns into the metadata JSON', function () {
    $importer = app(BrandBulkImportService::class);
    $job = makeJob([
        'title' => 'name',
        'seo_title' => 'st',
        'seo_keywords' => 'sk',
        'seo_description' => 'sd',
    ]);

    [$outcome] = $importer->processRow([
        'name' => 'SeoBrand',
        'st' => 'My SEO title',
        'sk' => 'a, b, c',
        'sd' => 'My SEO description',
    ], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_CREATED);
    $brand = Brand::where('title', 'SeoBrand')->first();
    expect($brand->metadata)->toBeArray();
    expect($brand->metadata['seo_title'])->toBe('My SEO title');
    expect($brand->metadata['seo_description'])->toBe('My SEO description');
    expect($brand->metadata['seo_keywords'])->toEqual(['a', 'b', 'c']);
});

it('fails a category-scoped row missing scope_id', function () {
    $importer = app(BrandBulkImportService::class);
    $job = makeJob(['title' => 'name', 'scope_type' => 'st']);

    [$outcome, $msg] = $importer->processRow(['name' => 'ScopedBrand', 'st' => 'category'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_FAILED);
    expect($msg)->not->toBeEmpty();
});

it('attaches a category by id when scope_type=category and scope_id is valid', function () {
    $category = Category::create(['title' => 'Fashion', 'status' => 'active']);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(['title' => 'name', 'scope_type' => 'st', 'scope_id' => 'sid']);

    [$outcome] = $importer->processRow([
        'name' => 'CatBrand',
        'st' => 'category',
        'sid' => (string) $category->id,
    ], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_CREATED);
    $brand = Brand::where('title', 'CatBrand')->first();
    expect($brand->scope_type)->toBe(HomePageScopeEnum::CATEGORY());
    expect((int) $brand->scope_id)->toBe($category->id);
});

it('falls back to the default status when no status column is mapped', function () {
    $importer = app(BrandBulkImportService::class);
    $job = makeJob(['title' => 'name'], ['status' => 'active']);

    [$outcome] = $importer->processRow(['name' => 'StatusBrand'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_CREATED);
    expect(Brand::where('title', 'StatusBrand')->value('status'))->toBe(BrandStatusEnum::ACTIVE());
});

// ---------- Update mode tests ----------

it('updates an existing brand matched by id', function () {
    $brand = Brand::create([
        'title' => 'OldTitle',
        'description' => 'Old desc',
        'status' => BrandStatusEnum::INACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ]);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(
        mapping: ['id' => 'id_col', 'title' => 'name', 'description' => 'desc'],
        operation: 'update',
        matchField: 'id',
    );

    [$outcome, $msg] = $importer->processRow([
        'id_col' => (string) $brand->id,
        'name' => 'NewTitle',
        'desc' => 'New desc',
    ], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_UPDATED);
    expect($msg)->toContain('NewTitle');
    $brand->refresh();
    expect($brand->title)->toBe('NewTitle');
    expect($brand->description)->toBe('New desc');
});

it('updates an existing brand matched by title', function () {
    $brand = Brand::create([
        'title' => 'KnownBrand',
        'description' => 'old',
        'status' => BrandStatusEnum::INACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ]);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(
        mapping: ['title' => 'name', 'description' => 'desc'],
        operation: 'update',
        matchField: 'title',
    );

    [$outcome] = $importer->processRow(['name' => 'KnownBrand', 'desc' => 'new'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_UPDATED);
    $brand->refresh();
    expect($brand->description)->toBe('new');
});

it('skips an update when no match is found', function () {
    $importer = app(BrandBulkImportService::class);
    $job = makeJob(
        mapping: ['id' => 'id_col', 'description' => 'desc'],
        operation: 'update',
        matchField: 'id',
    );

    [$outcome, $msg] = $importer->processRow(['id_col' => '999999', 'desc' => 'whatever'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_SKIPPED);
    expect($msg)->toContain('999999');
});

it('skips an update when nothing changed', function () {
    Brand::create([
        'title' => 'Stable',
        'description' => 'Same desc',
        'status' => BrandStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ]);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(
        mapping: ['title' => 'name', 'description' => 'desc'],
        operation: 'update',
        matchField: 'title',
    );

    [$outcome] = $importer->processRow(['name' => 'Stable', 'desc' => 'Same desc'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_SKIPPED);
});

it('leaves empty cells unchanged in update mode', function () {
    $brand = Brand::create([
        'title' => 'PreserveBrand',
        'description' => 'Important description',
        'status' => BrandStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ]);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(
        mapping: ['id' => 'id_col', 'title' => 'name', 'description' => 'desc'],
        operation: 'update',
        matchField: 'id',
    );

    // Title is empty => leave existing. Description is set => change it.
    [$outcome] = $importer->processRow([
        'id_col' => (string) $brand->id,
        'name' => '',
        'desc' => 'Updated description',
    ], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_UPDATED);
    $brand->refresh();
    expect($brand->title)->toBe('PreserveBrand');           // unchanged
    expect($brand->description)->toBe('Updated description');
});

it('updates seo metadata in place without clobbering untouched keys', function () {
    $brand = Brand::create([
        'title' => 'SeoBrand',
        'status' => BrandStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
        'metadata' => ['seo_title' => 'Old SEO', 'seo_description' => 'Old desc', 'extra' => 'keep'],
    ]);

    $importer = app(BrandBulkImportService::class);
    $job = makeJob(
        mapping: ['id' => 'id_col', 'seo_title' => 'st'],
        operation: 'update',
        matchField: 'id',
    );

    [$outcome] = $importer->processRow(['id_col' => (string) $brand->id, 'st' => 'New SEO'], $job);

    expect($outcome)->toBe(BulkImportable::OUTCOME_UPDATED);
    $brand->refresh();
    expect($brand->metadata['seo_title'])->toBe('New SEO');
    expect($brand->metadata['seo_description'])->toBe('Old desc');
    expect($brand->metadata['extra'])->toBe('keep');
});
