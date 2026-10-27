<?php

use App\Installer\Helpers\DatabaseManager;
use App\Installer\Helpers\InstallationProgress;
use App\Installer\Helpers\InstallationState;
use App\Installer\Helpers\InstalledFileManager;
use App\Installer\Helpers\PermissionsChecker;
use App\Installer\Middleware\canInstall;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

function installerProgressForTest(): array
{
    $suffix = bin2hex(random_bytes(6));
    $path = storage_path("framework/testing/installer-progress-{$suffix}.json");
    $lockPath = storage_path("framework/testing/installer-progress-{$suffix}.lock");
    $progress = new InstallationProgress($path, $lockPath);
    app()->instance(InstallationProgress::class, $progress);

    return [$progress, $path, $lockPath];
}

function removeInstallerProgressFiles(string $path, string $lockPath): void
{
    @unlink($path);
    @unlink($lockPath);
}

it('ships with a storage-independent pre-database session driver', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain("SESSION_DRIVER=cookie\n");
});

it('checks requirements and permissions before license validation', function () {
    $this->withoutMiddleware()
        ->get('/install')
        ->assertOk()
        ->assertSee('HyperMart Installer - Hyperlocal Multivendor Commerce Setup')
        ->assertSee('Installation Guide')
        ->assertSee(config('installer.branding.guide_url'), false)
        ->assertSee(route('LaravelInstaller::requirements'), false);

    $checker = Mockery::mock(PermissionsChecker::class);
    $checker->shouldReceive('check')->once()->andReturn([
        'permissions' => [],
        'errors' => null,
    ]);
    app()->instance(PermissionsChecker::class, $checker);

    $this->withoutMiddleware()
        ->get('/install/permissions')
        ->assertOk()
        ->assertSee('Ubuntu / Debian')
        ->assertSee('sudo chmod -R 775 storage bootstrap/cache')
        ->assertSee('Windows IIS')
        ->assertSee(route('LaravelInstaller::license'), false);
});

it('keeps only the super admin password hash in the installer session', function () {
    File::partialMock();
    File::shouldReceive('exists')
        ->once()
        ->with(storage_path('app'))
        ->andReturnTrue();
    File::shouldReceive('put')
        ->once()
        ->withArgs(function (string $path, string $contents) {
            $payload = json_decode($contents, true);

            return $path === storage_path('app/install_super_admin.json')
                && ! isset($payload['password'])
                && Hash::check('secure-password', $payload['password_hash']);
        })
        ->andReturnTrue();

    $response = $this->withoutMiddleware()
        ->withSession([])
        ->post('/install/super-admin/save', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
            'mobile' => '',
        ]);

    $response->assertOk()
        ->assertJsonPath('url', route('LaravelInstaller::database'))
        ->assertSessionHas('install_super_admin', fn (array $details) => ! isset($details['password'])
            && Hash::check('secure-password', $details['password_hash']));

    expect(file_get_contents(resource_path('views/vendor/installer/super_admin.blade.php')))
        ->toContain("sessionStorage.setItem('installer_admin_password', password)")
        ->and(file_get_contents(resource_path('views/vendor/installer/finished.blade.php')))
        ->toContain("sessionStorage.getItem('installer_admin_password')")
        ->not->toContain('Not available');
});

it('does not complete installation when database setup fails', function () {
    [, $path, $lockPath] = installerProgressForTest();
    $manager = Mockery::mock(DatabaseManager::class);
    $manager->shouldReceive('migrateAndSeed')->once()->andReturn([
        'status' => 'danger',
        'message' => 'Seeder failed',
    ]);
    app()->instance(DatabaseManager::class, $manager);

    $response = $this->withoutMiddleware()
        ->withSession([
            'install_super_admin' => [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password_hash' => bcrypt('password'),
            ],
        ])
        ->post('/install/database/install');

    $response->assertRedirect(route('LaravelInstaller::database'))
        ->assertSessionHas('error', 'Seeder failed');

    removeInstallerProgressFiles($path, $lockPath);
});

it('rebuilds the database before running installation seeders', function () {
    [$progress, $path, $lockPath] = installerProgressForTest();
    $progress->start('test-token');
    config(['installer.seeders' => ['FirstSeeder', 'SecondSeeder']]);
    DB::shouldReceive('connection')->andReturn(new stdClass);
    Artisan::shouldReceive('call')
        ->once()
        ->with('db:wipe', ['--drop-views' => true, '--force' => true])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => 'FirstSeeder', '--force' => true])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => 'SecondSeeder', '--force' => true])
        ->andReturn(0);
    Artisan::shouldNotReceive('call')->with('migrate:fresh', Mockery::any());

    $result = app(DatabaseManager::class)->migrateAndSeed();

    expect($result['status'])->toBe('success')
        ->and($progress->snapshot('test-token')['percentage'])->toBe(85);

    removeInstallerProgressFiles($path, $lockPath);
});

it('stops with a support reference when a required seeder exits unsuccessfully', function () {
    [$progress, $path, $lockPath] = installerProgressForTest();
    $progress->start('test-token');
    config(['installer.seeders' => ['FailingSeeder']]);
    DB::shouldReceive('connection')->andReturn(new stdClass);
    Artisan::shouldReceive('call')
        ->once()
        ->with('db:wipe', ['--drop-views' => true, '--force' => true])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => 'FailingSeeder', '--force' => true])
        ->andReturn(1);

    $result = app(DatabaseManager::class)->migrateAndSeed();
    $snapshot = $progress->snapshot('test-token');

    expect($result['status'])->toBe('danger')
        ->and($result['message'])->toBe('Required database seeding failed.')
        ->and($result['reference'])->toStartWith('INST-')
        ->and($snapshot['status'])->toBe('failed')
        ->and($snapshot['phase'])->toBe('seeders')
        ->and($snapshot['percentage'])->toBeLessThan(100)
        ->and($snapshot['error_reference'])->toStartWith('INST-');

    removeInstallerProgressFiles($path, $lockPath);
});

it('persists real progress and protects it with the installation token', function () {
    [$progress, $path, $lockPath] = installerProgressForTest();
    $progress->start('progress-token');
    $progress->completePhase('preflight', 'Preflight complete.');
    $progress->advance('migrations', 5, 10, 'Migration 5 of 10 completed.');

    $snapshot = $progress->snapshot('progress-token');

    expect($snapshot['status'])->toBe('running')
        ->and($snapshot['phase'])->toBe('migrations')
        ->and($snapshot['percentage'])->toBe(35)
        ->and($snapshot['logs'])->toHaveCount(3)
        ->and($progress->snapshot('wrong-token')['status'])->toBe('idle');

    $lock = $progress->acquireLock();

    expect($lock)->not->toBeNull()
        ->and($progress->acquireLock())->toBeNull();

    $progress->releaseLock($lock);

    $progress->complete('Installation completed successfully.');

    expect($progress->snapshot('progress-token')['status'])->toBe('complete')
        ->and($progress->snapshot('progress-token')['percentage'])->toBe(100);

    removeInstallerProgressFiles($path, $lockPath);
});

it('shows and exposes live installation progress only to its session', function () {
    [$progress, $path, $lockPath] = installerProgressForTest();
    $progress->start('progress-token');

    $response = $this->withoutMiddleware(canInstall::class)
        ->withSession([
            'install_progress_token' => 'progress-token',
            'install_super_admin' => [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password_hash' => bcrypt('password'),
            ],
        ])
        ->get('/install/database')
        ->assertOk()
        ->assertSee('role="progressbar"', false)
        ->assertSee('const progressUrl', false);

    expect(substr_count($response->getContent(), 'window.location.assign('))->toBe(1);

    $this->withoutMiddleware(canInstall::class)
        ->withSession(['install_progress_token' => 'progress-token'])
        ->getJson('/install/database/progress')
        ->assertOk()
        ->assertJsonPath('status', 'running')
        ->assertJsonMissingPath('token');

    $this->withoutMiddleware(canInstall::class)
        ->withSession(['install_progress_token' => 'wrong-token'])
        ->getJson('/install/database/progress')
        ->assertNotFound();

    removeInstallerProgressFiles($path, $lockPath);
});

it('does not create the installed marker from the final URL without verified completion', function () {
    [, $path, $lockPath] = installerProgressForTest();
    $fileManager = Mockery::mock(InstalledFileManager::class);
    $fileManager->shouldNotReceive('update');
    app()->instance(InstalledFileManager::class, $fileManager);

    $this->withoutMiddleware(canInstall::class)
        ->withSession([])
        ->get('/install/final')
        ->assertRedirect(route('LaravelInstaller::database'));

    removeInstallerProgressFiles($path, $lockPath);
});

it('uses the installer progress token when the finalization session key is missing', function () {
    $progress = Mockery::mock(InstallationProgress::class);
    $progress->shouldReceive('isReady')
        ->once()
        ->with('progress-token')
        ->andReturnFalse();
    app()->instance(InstallationProgress::class, $progress);

    $fileManager = Mockery::mock(InstalledFileManager::class);
    $fileManager->shouldNotReceive('update');
    app()->instance(InstalledFileManager::class, $fileManager);

    $this->withoutMiddleware(canInstall::class)
        ->withSession(['install_progress_token' => 'progress-token'])
        ->get('/install/final')
        ->assertRedirect(route('LaravelInstaller::database'))
        ->assertSessionHas('error', 'Installation has not completed successfully.');
});

it('does not automatically repeat a rejected finalization redirect', function () {
    $progress = Mockery::mock(InstallationProgress::class);
    $progress->shouldReceive('snapshot')
        ->once()
        ->with('progress-token')
        ->andReturn([
            'status' => 'ready',
            'phase' => 'finalization',
            'percentage' => 95,
            'message' => 'Ready for final verification.',
            'error_reference' => null,
            'logs' => [],
        ]);
    app()->instance(InstallationProgress::class, $progress);

    $this->withoutMiddleware(canInstall::class)
        ->withSession([
            'install_progress_token' => 'progress-token',
            'install_super_admin' => [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password_hash' => bcrypt('password'),
            ],
            'error' => 'Installation has not completed successfully.',
        ])
        ->get('/install/database')
        ->assertOk()
        ->assertSee('const finalizationRetryRequired = true;', false)
        ->assertSee('Retry Final Verification');
});

it('does not expose database installation through GET', function () {
    $this->withoutMiddleware()
        ->get('/install/database/install')
        ->assertMethodNotAllowed();
});

it('does not expose license verification through GET', function () {
    $this->withoutMiddleware()
        ->get('/install/license/verify?purchase_code=leaked-code')
        ->assertMethodNotAllowed();
});

it('requires the installed marker even when the application database is populated', function () {
    $state = new InstallationState(storage_path('framework/testing/missing-installed-marker'));
    app()->instance(InstallationState::class, $state);

    expect($state->isInstalled())->toBeFalse();

    $this->get('/admin/login')
        ->assertRedirect(route('LaravelInstaller::welcome'));

});
