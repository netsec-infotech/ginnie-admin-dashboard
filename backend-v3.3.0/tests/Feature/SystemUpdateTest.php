<?php

use App\Enums\SystemUpdateStatusEnum;
use App\Enums\SystemUpdateStepEnum;
use App\Enums\UpdateTypeEnum;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Requests\SystemUpdate\StoreSystemUpdateRequest;
use App\Jobs\ApplySystemUpdateJob;
use App\Jobs\RunSystemUpdateJob;
use App\Models\SystemUpdate;
use App\Services\SettingService;
use App\Services\SystemUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * Feature tests for the system updater's robustness layer.
 *
 * These tests focus on the three behaviours the rewrite is meant to guarantee:
 * 1. The upload path creates a pending row and dispatches the queue job without running the work inline.
 * 2. The stale-heartbeat watchdog flips stuck pending rows to failed so the UI can unstick.
 * 3. The isStale()/isTerminal() helpers used by the poll endpoint behave correctly.
 */
beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

it('uses the release force flag when reporting an available client update', function () {
    expect(SettingService::getUpdateType('1.0.0', '2.0.0', false))->toBe(UpdateTypeEnum::SOFT_UPDATE())
        ->and(SettingService::getUpdateType('1.0.0', '2.0.0', true))->toBe(UpdateTypeEnum::FORCE_UPDATE())
        ->and(SettingService::getUpdateType('2.0.0', '2.0.0', true))->toBeNull();
});

it('prepareUpload creates a pending row and stores the ZIP', function () {
    $zip = UploadedFile::fake()->create('update.zip', 10, 'application/zip');

    $update = app(SystemUpdater::class)->prepareUpload($zip, userId: 0);

    expect($update->status)->toBe(SystemUpdateStatusEnum::PENDING);
    expect($update->step)->toBe(SystemUpdateStepEnum::QUEUED);
    expect($update->progress)->toBe(0);
    expect($update->heartbeat_at)->not->toBeNull();
    expect($update->package_name)->toEndWith('.zip');
    Storage::disk('local')->assertExists(SystemUpdater::UPDATES_DIR.'/'.$update->package_name);
});

it('rejects non-zip uploads', function () {
    $file = UploadedFile::fake()->create('update.tar', 1, 'application/x-tar');

    expect(fn () => app(SystemUpdater::class)->prepareUpload($file, 0))
        ->toThrow(InvalidArgumentException::class);
});

it('requires force_update to be a boolean in the update manifest', function (array $manifest) {
    $updater = new class extends SystemUpdater
    {
        public function validate(array $manifest): void
        {
            $this->validateManifest($manifest);
        }
    };

    expect(fn () => $updater->validate($manifest))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'missing force_update' => [[
        'version' => '99.0.0',
        'actions' => [['type' => 'add']],
    ]],
    'non-boolean force_update' => [[
        'version' => '99.0.0',
        'force_update' => 'false',
        'actions' => [['type' => 'add']],
    ]],
]);

it('refuses a second upload while another run is still active', function () {
    SystemUpdate::create([
        'version' => '1.0.0',
        'package_name' => 'existing.zip',
        'status' => SystemUpdateStatusEnum::PENDING->value,
        'step' => SystemUpdateStepEnum::APPLYING->value,
        'heartbeat_at' => now(),
    ]);

    $zip = UploadedFile::fake()->create('update.zip', 10, 'application/zip');

    expect(fn () => app(SystemUpdater::class)->prepareUpload($zip, 0))
        ->toThrow(RuntimeException::class);
});

it('allows a new upload when the pending row is stale (heartbeat > threshold)', function () {
    SystemUpdate::create([
        'version' => '1.0.0',
        'package_name' => 'stale.zip',
        'status' => SystemUpdateStatusEnum::PENDING->value,
        'step' => SystemUpdateStepEnum::APPLYING->value,
        'heartbeat_at' => now()->subMinutes(SystemUpdate::STUCK_THRESHOLD_MINUTES + 1),
    ]);

    $zip = UploadedFile::fake()->create('update.zip', 10, 'application/zip');

    $newRow = app(SystemUpdater::class)->prepareUpload($zip, 0);

    expect($newRow->status)->toBe(SystemUpdateStatusEnum::PENDING);
    // The stale row should have been flipped by the guard.
    expect(SystemUpdate::where('package_name', 'stale.zip')->first()->status)
        ->toBe(SystemUpdateStatusEnum::FAILED);
});

it('reconcileStuckRuns flips rows with no heartbeat past the threshold', function () {
    $fresh = SystemUpdate::create([
        'version' => '1.1.0',
        'package_name' => 'fresh.zip',
        'status' => SystemUpdateStatusEnum::PENDING->value,
        'heartbeat_at' => now(),
    ]);
    $stuck = SystemUpdate::create([
        'version' => '1.2.0',
        'package_name' => 'stuck.zip',
        'status' => SystemUpdateStatusEnum::PENDING->value,
        'heartbeat_at' => now()->subMinutes(SystemUpdate::STUCK_THRESHOLD_MINUTES + 5),
    ]);

    $healed = app(SystemUpdater::class)->reconcileStuckRuns();

    expect($healed)->toBe(1);
    expect($fresh->fresh()->status)->toBe(SystemUpdateStatusEnum::PENDING);
    expect($stuck->fresh()->status)->toBe(SystemUpdateStatusEnum::FAILED);
    expect($stuck->fresh()->log)->toContain('stuck — no heartbeat');
});

it('isStale returns false for terminal rows regardless of heartbeat', function () {
    $applied = SystemUpdate::create([
        'version' => '1.0.0',
        'package_name' => 'applied.zip',
        'status' => SystemUpdateStatusEnum::APPLIED->value,
        'heartbeat_at' => now()->subDay(),
    ]);

    expect($applied->isStale())->toBeFalse();
    expect($applied->isTerminal())->toBeTrue();
});

it('ApplySystemUpdateJob failed() hook finalizes a lingering pending row', function () {
    $row = SystemUpdate::create([
        'version' => '1.0.0',
        'package_name' => 'job-failed.zip',
        'status' => SystemUpdateStatusEnum::PENDING->value,
        'heartbeat_at' => now(),
    ]);

    (new ApplySystemUpdateJob($row->id))->failed(new RuntimeException('worker died'));

    $row->refresh();
    expect($row->status)->toBe(SystemUpdateStatusEnum::FAILED);
    expect($row->log)->toContain('worker died');
});

it('keeps active workers pending even when the database heartbeat is old', function () {
    $updater = app(SystemUpdater::class);
    $updater->prepareUpload(UploadedFile::fake()->create('update.zip', 1, 'application/zip'), 0);
    $row = SystemUpdate::first();
    $row->update(['heartbeat_at' => now()->subHour()]);
    $lock = fopen(Storage::disk('local')->path(SystemUpdater::UPDATES_DIR.'/run.lock'), 'c');
    flock($lock, LOCK_EX | LOCK_NB);
    try {
        expect($updater->reconcileStuckRuns())->toBe(0);
        expect($row->fresh()->status)->toBe(SystemUpdateStatusEnum::PENDING);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('requires explicit confirmation and a matching prepared upload token', function () {
    $request = Request::create('/admin/system-updates', 'POST', ['token' => str_repeat('a', 64), 'confirmed' => true]);
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
    $user = Mockery::mock();
    $user->shouldReceive('hasRole')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);
    $controller = new SystemUpdateController(app(SystemUpdater::class));
    expect($controller->store($request)->getStatusCode())->toBe(422);
    $request->session()->put('system_update_prepared', ['token' => str_repeat('b', 64), 'expires' => time() + 1800]);
    expect($controller->store($request)->getStatusCode())->toBe(422);
    Queue::assertNothingPushed();
});

it('forbids update preparation and confirmation for non-super-admins', function (string $method) {
    $request = $method === 'preview'
        ? StoreSystemUpdateRequest::create('/admin/system-updates/preview', 'POST')
        : Request::create('/admin/system-updates', 'POST');
    app()->instance('request', $request);
    $user = Mockery::mock();
    $user->shouldReceive('hasRole')->andReturn(false);
    Auth::shouldReceive('user')->andReturn($user);
    $controller = new SystemUpdateController(app(SystemUpdater::class));
    expect(fn () => $controller->$method($request))->toThrow(HttpException::class);
    Queue::assertNothingPushed();
})->with(['preview', 'store']);

it('prepares an upload without dispatching and dispatches only its confirmed package', function () {
    $path = sys_get_temp_dir().'/update-preview-'.bin2hex(random_bytes(8)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('update.json', json_encode(['version' => '99.0.0', 'force_update' => false, 'vendor_update' => false,
        'actions' => [['type' => 'add', 'source' => 'app/release-preview.txt', 'target' => 'app/release-preview.txt']],
    ]));
    $zip->addFromString('app/release-preview.txt', 'prepared only');
    $zip->close();
    $request = StoreSystemUpdateRequest::create('/admin/system-updates/preview', 'POST', [], [], [
        'package' => new UploadedFile($path, 'update.zip', 'application/zip', null, true),
    ]);
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
    $user = Mockery::mock();
    $user->shouldReceive('hasRole')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('id')->andReturn(0);
    $controller = new SystemUpdateController(app(SystemUpdater::class));
    $preview = $controller->store($request);
    expect($preview->getStatusCode())->toBe(200);
    $prepared = $preview->getData(true);
    expect($controller->index()->getData()['preparedUpdate'])->toBe($prepared);
    $session = $request->session()->get('system_update_prepared');
    $request->session()->put('system_update_prepared.expires', time() - 1);
    expect($controller->index()->getData()['preparedUpdate'])->toBeNull();
    $request->session()->put('system_update_prepared', $session);
    Queue::assertNothingPushed();
    expect(SystemUpdate::count())->toBe(0);
    $confirm = Request::create('/admin/system-updates', 'POST', ['token' => $prepared['token'], 'confirmed' => true]);
    $confirm->setLaravelSession($request->session());
    app()->instance('request', $confirm);
    $response = $controller->store($confirm);
    try {
        expect($response->getStatusCode())->toBe(200);
        Queue::assertPushed(RunSystemUpdateJob::class, 1);
        expect(SystemUpdate::count())->toBe(1);
        expect(is_file(base_path('app/release-preview.txt')))->toBeFalse();
        expect($controller->store($confirm)->getStatusCode())->toBe(422);
    } finally {
        unlink($path);
        if ($token = $response->getData(true)['recovery_token'] ?? null) {
            File::deleteDirectory(storage_path('app/private/updates/recovery/'.$token));
        }
        File::deleteDirectory(storage_path('app/private/updates/prepared/'.$prepared['token']));
    }
});
