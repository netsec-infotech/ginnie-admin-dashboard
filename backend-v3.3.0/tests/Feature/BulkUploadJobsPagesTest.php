<?php

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Skip the admin middleware stack — these tests focus on the new endpoints, not auth.
    $this->withoutMiddleware();

    $user = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $user->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $this->actingAs($user, GuardNameEnum::ADMIN->value);
});

function makeBulkJob(array $overrides = []): BulkUploadJob
{
    return BulkUploadJob::create(array_merge([
        'uploadable_type' => Brand::class,
        'file_disk' => 'local',
        'file_path' => 'bulk-uploads/fake/sample.csv',
        'original_filename' => 'sample.csv',
        'column_mapping' => ['title' => 'name'],
        'default_values' => [],
        'total_rows' => 10,
        'processed_rows' => 4,
        'success_count' => 3,
        'failed_count' => 1,
        'status' => BulkUploadJobStatusEnum::PROCESSING(),
        'error_log' => "#1 OK\n#2 OK\n#3 OK\n#4 FAILED",
    ], $overrides));
}

it('renders the jobs list page', function () {
    makeBulkJob();
    $this->get(route('admin.brands.bulk-upload.jobs.index'))->assertOk();
});

it('returns the datatable payload', function () {
    makeBulkJob(['original_filename' => 'fileA.csv']);
    makeBulkJob(['original_filename' => 'fileB.csv', 'status' => BulkUploadJobStatusEnum::COMPLETED()]);

    $resp = $this->get(route('admin.brands.bulk-upload.jobs.datatable', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'order' => [['column' => 0, 'dir' => 'desc']],
        'search' => ['value' => ''],
    ]));

    $resp->assertOk();
    $resp->assertJsonPath('recordsTotal', 2);
    $resp->assertJsonCount(2, 'data');
});

it('renders the detail page', function () {
    $job = makeBulkJob();
    $this->get(route('admin.brands.bulk-upload.jobs.show', ['id' => $job->id]))->assertOk();
});

it('returns 404 on the detail page when the job does not exist', function () {
    $this->get(route('admin.brands.bulk-upload.jobs.show', ['id' => 999999]))->assertNotFound();
});

it('returns status JSON with counters and the error log', function () {
    $job = makeBulkJob();

    $resp = $this->getJson(route('admin.brands.bulk-upload.jobs.status', ['id' => $job->id]));

    $resp->assertOk();
    $resp->assertJsonPath('success', true);
    $resp->assertJsonPath('data.status', BulkUploadJobStatusEnum::PROCESSING->value);
    $resp->assertJsonPath('data.is_terminal', false);
    $resp->assertJsonPath('data.total_rows', 10);
    $resp->assertJsonPath('data.processed_rows', 4);
    $resp->assertJsonPath('data.success_count', 3);
    $resp->assertJsonPath('data.failed_count', 1);
    $resp->assertJsonPath('data.progress_percent', 40);
    $resp->assertJsonPath('data.error_log', "#1 OK\n#2 OK\n#3 OK\n#4 FAILED");
});

it('marks is_terminal true for completed jobs', function () {
    $job = makeBulkJob([
        'status' => BulkUploadJobStatusEnum::COMPLETED(),
        'processed_rows' => 10,
        'success_count' => 10,
        'failed_count' => 0,
    ]);

    $resp = $this->getJson(route('admin.brands.bulk-upload.jobs.status', ['id' => $job->id]));

    $resp->assertJsonPath('data.is_terminal', true);
    $resp->assertJsonPath('data.progress_percent', 100);
});

it('returns 404 status JSON for a missing job', function () {
    $resp = $this->getJson(route('admin.brands.bulk-upload.jobs.status', ['id' => 999999]));
    $resp->assertStatus(404);
    $resp->assertJsonPath('success', false);
});

it('shows the active job notice when a brand upload is in progress', function () {
    $job = makeBulkJob([
        'original_filename' => 'in-flight.csv',
        'status' => \App\Enums\BulkUpload\BulkUploadJobStatusEnum::PROCESSING(),
    ]);

    $resp = $this->get(route('admin.brands.bulk-upload.page'));

    $resp->assertOk();
    $resp->assertSee('in-flight.csv');
    $resp->assertDontSee('id="bulk-upload-preview-form"', escape: false);
});

it('rejects a preview attempt when an active brand job exists', function () {
    makeBulkJob(['status' => \App\Enums\BulkUpload\BulkUploadJobStatusEnum::PROCESSING()]);

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
        'brands.csv',
        "name\nAcme\n"
    );

    $resp = $this->postJson(route('admin.brands.bulk-upload.preview'), [
        'csv_file' => $file,
    ]);

    $resp->assertStatus(409);
    $resp->assertJsonPath('success', false);
    $resp->assertJsonPath('message', __('labels.bulk_upload_already_in_progress'));
});

it('rejects a confirm attempt when an active brand job exists', function () {
    makeBulkJob(['status' => \App\Enums\BulkUpload\BulkUploadJobStatusEnum::PENDING()]);

    $resp = $this->postJson(route('admin.brands.bulk-upload.confirm'), [
        'preview_token' => 'whatever-token',
        'original_filename' => 'x.csv',
        'mapping' => ['title' => 'name', 'description' => 'description'],
    ]);

    $resp->assertStatus(409);
    $resp->assertJsonPath('message', __('labels.bulk_upload_already_in_progress'));
});

it('cancels a running job and marks it CANCELLED', function () {
    $job = makeBulkJob(['status' => BulkUploadJobStatusEnum::PROCESSING()]);

    $resp = $this->postJson(route('admin.brands.bulk-upload.jobs.cancel', ['id' => $job->id]));

    $resp->assertOk();
    $resp->assertJsonPath('success', true);
    $resp->assertJsonPath('message', __('labels.bulk_upload_cancelled'));

    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::CANCELLED);
    expect($job->finished_at)->not->toBeNull();
    expect($job->error_log)->toContain('Cancelled by user.');
});

it('cancels a pending (not-yet-running) job', function () {
    $job = makeBulkJob([
        'status' => BulkUploadJobStatusEnum::PENDING(),
        'processed_rows' => 0,
        'success_count' => 0,
        'failed_count' => 0,
    ]);

    $resp = $this->postJson(route('admin.brands.bulk-upload.jobs.cancel', ['id' => $job->id]));

    $resp->assertOk();
    $job->refresh();
    expect($job->status)->toBe(BulkUploadJobStatusEnum::CANCELLED);
});

it('returns 409 when cancelling a job that is already in a terminal state', function () {
    foreach ([BulkUploadJobStatusEnum::COMPLETED(), BulkUploadJobStatusEnum::FAILED(), BulkUploadJobStatusEnum::CANCELLED()] as $terminal) {
        $job = makeBulkJob(['status' => $terminal]);

        $resp = $this->postJson(route('admin.brands.bulk-upload.jobs.cancel', ['id' => $job->id]));

        $resp->assertStatus(409);
        $resp->assertJsonPath('message', __('labels.bulk_upload_cannot_cancel_terminal'));
    }
});

it('returns 404 when cancelling a job that does not exist', function () {
    $resp = $this->postJson(route('admin.brands.bulk-upload.jobs.cancel', ['id' => 999999]));
    $resp->assertStatus(404);
});
