<?php

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['bulk_upload.file_disk' => 'local']);

    // The download endpoint is admin-guarded — set up a SUPER_ADMIN actor that
    // every HTTP test in this file can use. Cron-driven tests don't need it
    // but the setup is cheap and isolated by RefreshDatabase.
    $this->withoutMiddleware();
    $user = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $user->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $this->actingAs($user, GuardNameEnum::ADMIN->value);
});

function writeErrorsCsv(string $path, array $headers, array $rows): void
{
    $content = implode(',', $headers) . "\n";
    foreach ($rows as $r) {
        $content .= implode(',', $r) . "\n";
    }
    Storage::disk('local')->put($path, $content);
}

it('writes failed rows to the per-job errors CSV during the cron tick', function () {
    Brand::create(['title' => 'Acme', 'status' => 'active']);

    $path = 'bulk-uploads/with-errors/sample.csv';
    writeErrorsCsv($path, ['name'], [
        ['Acme'],   // duplicate → fail
        ['Fresh'],  // succeed
        ['Acme'],   // duplicate → fail (again)
    ]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 3,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::COMPLETED());
    expect($job->failed_count)->toBe(2);
    expect($job->failed_rows_path)->toBe('bulk-uploads/with-errors/_errors.csv');

    Storage::disk('local')->assertExists('bulk-uploads/with-errors/_errors.csv');
    $content = Storage::disk('local')->get('bulk-uploads/with-errors/_errors.csv');
    // Header: _row, original headers (name), _error
    expect($content)->toContain('_row,name,_error');
    // Both failed rows present, with their original row numbers (1 and 3).
    expect($content)->toContain("1,Acme,");
    expect($content)->toContain("3,Acme,");
});

it('does not create the errors CSV when no rows fail', function () {
    $path = 'bulk-uploads/all-ok/sample.csv';
    writeErrorsCsv($path, ['name'], [['Acme'], ['Beta']]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 2,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    $job->refresh();
    expect($job->failed_count)->toBe(0);
    expect($job->failed_rows_path)->toBeNull();
});

it('keeps the errors CSV after the job lands in a terminal state (input CSV gone, errors CSV survives)', function () {
    Brand::create(['title' => 'Acme', 'status' => 'active']);

    $path = 'bulk-uploads/keep-errors/sample.csv';
    writeErrorsCsv($path, ['name'], [['Acme']]); // fails — only one row, all dupes

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 1,
        'status' => BulkUploadJobStatusEnum::PENDING(),
    ]);

    artisan('bulk-uploads:run')->assertExitCode(0);

    // Input CSV deleted by terminal-state cleanup, errors CSV survives.
    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertExists('bulk-uploads/keep-errors/_errors.csv');
});

it('streams the errors CSV from the download endpoint', function () {
    $path = 'bulk-uploads/dl-errors/_errors.csv';
    writeErrorsCsv($path, ['_row', 'name', '_error'], [
        ['1', 'Acme', 'duplicate'],
        ['3', 'Acme', 'duplicate'],
    ]);

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/dl-errors/sample.csv',
        'failed_rows_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 3,
        'processed_rows' => 3,
        'failed_count' => 2,
        'success_count' => 1,
        'status' => BulkUploadJobStatusEnum::COMPLETED(),
        'finished_at' => now(),
    ]);

    $resp = $this->get(route('admin.brands.bulk-upload.jobs.errors', ['id' => $job->id]));

    $resp->assertOk();
    $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($resp->headers->get('Content-Disposition'))
        ->toContain('brands_job_'.$job->id.'_errors.csv');
});

it('returns 404 when the job has no errors file', function () {
    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/no-errors/sample.csv',
        'failed_rows_path' => null,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 1,
        'processed_rows' => 1,
        'failed_count' => 0,
        'status' => BulkUploadJobStatusEnum::COMPLETED(),
        'finished_at' => now(),
    ]);

    $this->get(route('admin.brands.bulk-upload.jobs.errors', ['id' => $job->id]))
        ->assertNotFound();
});

it('returns 404 when the errors file has been cleaned up', function () {
    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/swept/sample.csv',
        'failed_rows_path' => 'bulk-uploads/swept/_errors.csv', // path set but file isn't on disk
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 1,
        'processed_rows' => 1,
        'failed_count' => 1,
        'status' => BulkUploadJobStatusEnum::COMPLETED(),
        'finished_at' => now(),
    ]);

    $this->get(route('admin.brands.bulk-upload.jobs.errors', ['id' => $job->id]))
        ->assertNotFound();
});

it('prune force-deletes the row and clears the errors CSV', function () {
    $path = 'bulk-uploads/prune-errors/_errors.csv';
    writeErrorsCsv($path, ['_row', 'name', '_error'], [['1', 'Acme', 'dup']]);
    // Back-date the file so it's older than the orphan threshold too.
    touch(Storage::disk('local')->path($path), now()->subDays(20)->getTimestamp());

    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/prune-errors/sample.csv',
        'failed_rows_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 1,
        'processed_rows' => 1,
        'failed_count' => 1,
        'status' => BulkUploadJobStatusEnum::COMPLETED(),
        'finished_at' => now()->subDays(10),
    ]);

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($job->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});
