<?php

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Services\BulkUpload\BulkUploadJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['bulk_upload.file_disk' => 'local']);
});

function writeCsv(string $relativePath, array $headers, array $rows): void
{
    $content = implode(',', $headers) . "\n";
    foreach ($rows as $r) {
        $content .= implode(',', $r) . "\n";
    }
    Storage::disk('local')->put($relativePath, $content);
}

it('processes a pending bulk upload job and creates brands', function () {
    $path = 'bulk-uploads/test-token/sample.csv';
    writeCsv($path, ['name', 'desc', 'st'], [
        ['Acme', 'Acme brand', 'active'],
        ['Beta', 'Beta brand', 'inactive'],
    ]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'user_id' => null,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name', 'description' => 'desc', 'status' => 'st'],
        'default_values' => [],
        'total_rows' => 2,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::COMPLETED);
    expect($job->success_count)->toBe(2);
    expect($job->failed_count)->toBe(0);
    expect(Brand::query()->whereEnglishTitle('Acme')->exists())->toBeTrue();
    expect(Brand::query()->whereEnglishTitle('Beta')->exists())->toBeTrue();
    // File should be cleaned up on completion.
    Storage::disk('local')->assertMissing($path);
});

it('strips a UTF-8 BOM from previewed CSV headers', function () {
    $path = 'bulk-uploads/bom-preview/sample.csv';
    writeCsv($path, ["\xEF\xBB\xBFid", 'title'], [
        ['1', 'Acme'],
    ]);

    $preview = app(BulkUploadJobService::class)->previewCsv('local', $path, 10);

    expect($preview['headers'])->toBe(['id', 'title']);
    expect($preview['rows'])->toHaveCount(1);
});

it('records per-row failures without aborting the job', function () {
    $path = 'bulk-uploads/test-fail/sample.csv';
    Brand::create(['title' => 'Acme', 'description' => 'Existing brand', 'status' => 'active']);
    writeCsv($path, ['name'], [
        ['Acme'], // duplicate — should fail
        ['Fresh'], // should succeed
    ]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => ['description' => 'Imported brand'],
        'total_rows' => 2,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::COMPLETED);
    expect($job->success_count)->toBe(1);
    expect($job->failed_count)->toBe(1);
    expect($job->error_log)->toContain('Acme');
});

it('does nothing when there are no runnable jobs', function () {
    artisan('bulk-uploads:run')->assertExitCode(0);
    expect(BulkUploadJob::count())->toBe(0);
});

it('processes an update job — updated/skipped counters bump correctly', function () {
    $brand1 = Brand::create(['title' => 'KeepBrand', 'description' => 'old', 'status' => 'active', 'scope_type' => 'global']);
    $brand2 = Brand::create(['title' => 'ChangeBrand', 'description' => 'old', 'status' => 'active', 'scope_type' => 'global']);

    $path = 'bulk-uploads/update-run/sample.csv';
    writeCsv($path, ['id_col', 'desc'], [
        [(string) $brand1->id, 'old'],          // unchanged → skipped
        [(string) $brand2->id, 'new desc'],     // changed → updated
        ['9999999', 'whatever'],                 // no match → skipped
    ]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['id' => 'id_col', 'description' => 'desc'],
        'default_values' => ['description' => 'Imported brand'],
        'total_rows' => 3,
        'status' => BulkUploadJobStatusEnum::PENDING(),
        'operation' => 'update',
        'match_field' => 'id',
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::COMPLETED);
    expect($job->updated_count)->toBe(1);
    expect($job->skipped_count)->toBe(2);
    expect($job->failed_count)->toBe(0);
    expect($job->success_count)->toBe(0);
    expect(Brand::find($brand2->id)->description)->toBe('new desc');
    expect(Brand::find($brand1->id)->description)->toBe('old');
});

it('processes update jobs when the match header has a UTF-8 BOM', function () {
    $brand = Brand::create(['title' => 'BomBrand', 'description' => 'old', 'status' => 'active', 'scope_type' => 'global']);

    $path = 'bulk-uploads/bom-update/sample.csv';
    writeCsv($path, ["\xEF\xBB\xBFid_col", 'desc'], [
        [(string) $brand->id, 'new desc'],
    ]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['id' => 'id_col', 'description' => 'desc'],
        'default_values' => ['description' => 'Imported brand'],
        'total_rows' => 1,
        'status' => BulkUploadJobStatusEnum::PENDING(),
        'operation' => 'update',
        'match_field' => 'id',
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::COMPLETED);
    expect($job->updated_count)->toBe(1);
    expect($job->failed_count)->toBe(0);
    expect($brand->fresh()->description)->toBe('new desc');
});

it('stops processing rows after the job is marked CANCELLED mid-run', function () {
    // 5 rows, 1 row per tick — we cancel between ticks 2 and 3 and assert the
    // third tick bails without touching the remaining rows.
    config(['bulk_upload.rows_per_tick' => 1]);

    $path = 'bulk-uploads/cancel-mid/sample.csv';
    writeCsv($path, ['name'], [['A1'], ['A2'], ['A3'], ['A4'], ['A5']]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => ['description' => 'Imported brand'],
        'total_rows' => 5,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);
    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->processed_rows)->toBe(2);
    expect($job->status)->toBe(BulkUploadJobStatusEnum::PROCESSING);

    // Externally cancel — what the controller's cancel endpoint does.
    app(\App\Services\BulkUpload\BulkUploadJobService::class)->markCancelled($job->fresh());

    // Next tick must bail without processing more rows. runnable() skips
    // CANCELLED so the command returns early.
    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::CANCELLED);
    expect($job->processed_rows)->toBe(2);
    expect(Brand::query()->whereEnglishTitle('A3')->exists())->toBeFalse();
    expect(Brand::query()->whereEnglishTitle('A4')->exists())->toBeFalse();
    expect(Brand::query()->whereEnglishTitle('A5')->exists())->toBeFalse();
});

it('honours cancellation issued mid-tick — credits already-processed rows, drops the rest', function () {
    // 4 rows in one tick. Use a one-shot Eloquent listener to flip the job to
    // CANCELLED right after row #2 is inserted; the loop must break before
    // reading rows 3+4. We bind via Event::listen so we can forget() it after
    // the test and avoid leaking into the rest of the suite.
    config(['bulk_upload.rows_per_tick' => 10]);

    $path = 'bulk-uploads/cancel-during-tick/sample.csv';
    writeCsv($path, ['name'], [['B1'], ['B2'], ['B3'], ['B4']]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => ['description' => 'Imported brand'],
        'total_rows' => 4,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    $event = 'eloquent.created: ' . Brand::class;
    \Illuminate\Support\Facades\Event::listen($event, function (Brand $brand) use ($job) {
        if ($brand->title === 'B2') {
            BulkUploadJob::query()->whereKey($job->id)->update([
                'status' => BulkUploadJobStatusEnum::CANCELLED(),
                'finished_at' => now(),
            ]);
        }
    });

    try {
        artisan('bulk-uploads:run')->assertExitCode(0);
    } finally {
        \Illuminate\Support\Facades\Event::forget($event);
    }

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::CANCELLED);
    expect($job->processed_rows)->toBe(2);
    expect(Brand::query()->whereEnglishTitle('B1')->exists())->toBeTrue();
    expect(Brand::query()->whereEnglishTitle('B2')->exists())->toBeTrue();
    expect(Brand::query()->whereEnglishTitle('B3')->exists())->toBeFalse();
    expect(Brand::query()->whereEnglishTitle('B4')->exists())->toBeFalse();
});

it('chunks across multiple ticks when rows_per_tick is small', function () {
    config(['bulk_upload.rows_per_tick' => 1]);

    $path = 'bulk-uploads/chunked/sample.csv';
    writeCsv($path, ['name'], [['A1'], ['A2'], ['A3']]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => ['description' => 'Imported brand'],
        'total_rows' => 3,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    // tick 1: processes 1, still PROCESSING
    artisan('bulk-uploads:run')->assertExitCode(0);
    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::PROCESSING);
    expect($job->processed_rows)->toBe(1);

    // tick 2: processes another, still PROCESSING
    artisan('bulk-uploads:run')->assertExitCode(0);
    $job->refresh();
    expect($job->processed_rows)->toBe(2);

    // tick 3: processes the last, completes
    artisan('bulk-uploads:run')->assertExitCode(0);
    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::COMPLETED);
    expect($job->success_count)->toBe(3);
});
