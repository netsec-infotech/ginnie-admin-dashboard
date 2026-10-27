<?php

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\User;
use App\Notifications\BulkUploadFinishedNotification;
use App\Services\BulkUpload\BulkUploadJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['bulk_upload.file_disk' => 'local']);
});

function makeNotifiableJob(?User $user, bool $notify, array $overrides = []): BulkUploadJob
{
    return BulkUploadJob::create(array_merge([
        'uploadable_type' => Brand::class,
        'user_id' => $user?->id,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/notify/sample.csv',
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 5,
        'processed_rows' => 5,
        'success_count' => 5,
        'updated_count' => 0,
        'skipped_count' => 0,
        'failed_count' => 0,
        'status' => BulkUploadJobStatusEnum::PROCESSING(),
        'notify_on_finish' => $notify,
    ], $overrides));
}

it('dispatches BulkUploadFinishedNotification on completion when notify_on_finish is true', function () {
    Notification::fake();
    $user = User::factory()->create();
    $job = makeNotifiableJob($user, notify: true);

    app(BulkUploadJobService::class)->markCompleted($job);

    Notification::assertSentTo($user, BulkUploadFinishedNotification::class);
});

it('does not dispatch BulkUploadFinishedNotification on completion when notify_on_finish is false', function () {
    Notification::fake();
    $user = User::factory()->create();
    $job = makeNotifiableJob($user, notify: false);

    app(BulkUploadJobService::class)->markCompleted($job);

    Notification::assertNothingSent();
});

it('dispatches BulkUploadFinishedNotification on failure when notify_on_finish is true', function () {
    Notification::fake();
    $user = User::factory()->create();
    $job = makeNotifiableJob($user, notify: true, overrides: [
        'success_count' => 2,
        'failed_count' => 3,
    ]);

    app(BulkUploadJobService::class)->markFailed($job, 'something went wrong');

    Notification::assertSentTo($user, BulkUploadFinishedNotification::class);
});

it('does NOT dispatch a notification on cancel — admin clicked cancel themselves', function () {
    Notification::fake();
    $user = User::factory()->create();
    $job = makeNotifiableJob($user, notify: true);

    app(BulkUploadJobService::class)->markCancelled($job);

    Notification::assertNothingSent();
});

it('skips dispatch silently when user_id is null even if notify_on_finish is true', function () {
    Notification::fake();
    $job = makeNotifiableJob(user: null, notify: true);

    app(BulkUploadJobService::class)->markCompleted($job);

    Notification::assertNothingSent();
});
