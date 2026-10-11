<?php

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config([
        'bulk_upload.file_disk' => 'local',
        'bulk_upload.temp_dir' => 'bulk-uploads',
        'bulk_upload.retention_days' => 7,
        'bulk_upload.retention_failed_days' => 30,
        'bulk_upload.orphan_csv_hours' => 6,
    ]);
});

function writePruneCsv(string $relativePath, string $content = "name\nA\n", ?int $mtime = null): string
{
    Storage::disk('local')->put($relativePath, $content);
    // Storage::fake's local adapter writes to a real temp dir — back-date the
    // file via touch() so the orphan sweep sees it as old when we want it to.
    if ($mtime !== null) {
        $absolute = Storage::disk('local')->path($relativePath);
        touch($absolute, $mtime);
    }

    return $relativePath;
}

function makeTerminalJob(string $status, $finishedAt, string $path = 'bulk-uploads/old/sample.csv'): BulkUploadJob
{
    return BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 1,
        'processed_rows' => 1,
        'status' => $status,
        'finished_at' => $finishedAt,
    ]);
}

it('hard-deletes COMPLETED rows finished more than retention_days ago', function () {
    $old = makeTerminalJob(BulkUploadJobStatusEnum::COMPLETED(), now()->subDays(10), path: 'bulk-uploads/old-completed/sample.csv');
    $fresh = makeTerminalJob(BulkUploadJobStatusEnum::COMPLETED(), now()->subDays(3), path: 'bulk-uploads/fresh-completed/sample.csv');

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($old->id)->exists())->toBeFalse();
    expect(BulkUploadJob::withTrashed()->whereKey($fresh->id)->exists())->toBeTrue();
});

it('hard-deletes FAILED and CANCELLED rows finished more than retention_failed_days ago', function () {
    $oldFailed = makeTerminalJob(BulkUploadJobStatusEnum::FAILED(), now()->subDays(40), path: 'bulk-uploads/old-failed/sample.csv');
    $oldCancelled = makeTerminalJob(BulkUploadJobStatusEnum::CANCELLED(), now()->subDays(40), path: 'bulk-uploads/old-cancelled/sample.csv');
    $freshFailed = makeTerminalJob(BulkUploadJobStatusEnum::FAILED(), now()->subDays(20), path: 'bulk-uploads/fresh-failed/sample.csv');

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($oldFailed->id)->exists())->toBeFalse();
    expect(BulkUploadJob::withTrashed()->whereKey($oldCancelled->id)->exists())->toBeFalse();
    expect(BulkUploadJob::withTrashed()->whereKey($freshFailed->id)->exists())->toBeTrue();
});

it('keeps FAILED rows that are past retention_days but within retention_failed_days', function () {
    // 14 days old: well past 7-day COMPLETED window, but still inside the 30-day FAILED window.
    $job = makeTerminalJob(BulkUploadJobStatusEnum::FAILED(), now()->subDays(14), path: 'bulk-uploads/midlife-failed/sample.csv');

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($job->id)->exists())->toBeTrue();
});

it('also hard-deletes soft-deleted rows once they pass the retention window', function () {
    $job = makeTerminalJob(BulkUploadJobStatusEnum::COMPLETED(), now()->subDays(10), path: 'bulk-uploads/soft-deleted/sample.csv');
    $job->delete(); // soft delete

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($job->id)->exists())->toBeFalse();
});

it('sweeps orphan CSVs older than orphan_csv_hours with no job referencing them', function () {
    $hoursAgo = now()->subHours(12)->getTimestamp();
    writePruneCsv('bulk-uploads/orphan-stale/sample.csv', mtime: $hoursAgo);

    artisan('bulk-uploads:prune')->assertExitCode(0);

    Storage::disk('local')->assertMissing('bulk-uploads/orphan-stale/sample.csv');
    expect(Storage::disk('local')->exists('bulk-uploads/orphan-stale'))->toBeFalse();
});

it('keeps orphan CSVs younger than orphan_csv_hours', function () {
    $hoursAgo = now()->subHours(2)->getTimestamp();
    writePruneCsv('bulk-uploads/orphan-fresh/sample.csv', mtime: $hoursAgo);

    artisan('bulk-uploads:prune')->assertExitCode(0);

    Storage::disk('local')->assertExists('bulk-uploads/orphan-fresh/sample.csv');
});

it('does not touch CSVs that are tied to a non-terminal job, regardless of age', function () {
    $hoursAgo = now()->subHours(48)->getTimestamp();
    writePruneCsv('bulk-uploads/pending-active/sample.csv', mtime: $hoursAgo);

    BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/pending-active/sample.csv',
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 1,
        'status' => BulkUploadJobStatusEnum::PROCESSING(),
    ]);

    artisan('bulk-uploads:prune')->assertExitCode(0);

    // CSV must survive — its job is still running.
    Storage::disk('local')->assertExists('bulk-uploads/pending-active/sample.csv');
});

it('clears any straggling CSV before force-deleting an old terminal row', function () {
    // Pretend the inline cleanup never fired — there's still a CSV on disk
    // for a 10-day-old COMPLETED job. The prune should clean both.
    $hoursAgo = now()->subDays(10)->getTimestamp();
    writePruneCsv('bulk-uploads/straggler/sample.csv', mtime: $hoursAgo);

    $job = makeTerminalJob(
        BulkUploadJobStatusEnum::COMPLETED(),
        now()->subDays(10),
        path: 'bulk-uploads/straggler/sample.csv',
    );

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($job->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('bulk-uploads/straggler/sample.csv');
});

it('does nothing destructive when nothing has expired', function () {
    $hoursAgo = now()->subHours(1)->getTimestamp();
    writePruneCsv('bulk-uploads/recent/sample.csv', mtime: $hoursAgo);

    $job = makeTerminalJob(BulkUploadJobStatusEnum::COMPLETED(), now()->subDay(), path: 'bulk-uploads/recent/sample.csv');

    artisan('bulk-uploads:prune')->assertExitCode(0);

    expect(BulkUploadJob::withTrashed()->whereKey($job->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists('bulk-uploads/recent/sample.csv');
});
