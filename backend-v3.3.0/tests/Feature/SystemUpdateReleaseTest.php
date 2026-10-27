<?php

use App\Services\SystemUpdater;
use App\Support\SystemUpdateRuntime;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->releaseRoot = sys_get_temp_dir().'/system-release-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->releaseRoot);
    require_once base_path('app/Support/SystemUpdateRuntime.php');
});

afterEach(function () {
    File::deleteDirectory($this->releaseRoot);
});

function releaseArchive(string $path, array $files): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
}

function releaseInspector(): SystemUpdater
{
    return new class extends SystemUpdater
    {
        public function extract(string $zip, string $directory): void
        {
            $this->extractArchive($zip, $directory);
        }

        public function vendor(string $directory): array
        {
            return $this->validateVendorUpdate($directory, ['php' => PHP_VERSION, 'bits' => PHP_INT_SIZE, 'extensions' => []]);
        }
    };
}

it('rejects archive traversal before extracting any file', function (string $entry) {
    releaseArchive($this->releaseRoot.'/package.zip', ['safe.txt' => 'safe', $entry => 'bad']);
    expect(fn () => releaseInspector()->extract($this->releaseRoot.'/package.zip', $this->releaseRoot))
        ->toThrow(RuntimeException::class);
    expect(is_file($this->releaseRoot.'/safe.txt'))->toBeFalse();
})->with(['../escape.php', '/absolute.php', 'folder/../../escape.php', 'folder\\escape.php', 'C:/escape.php']);

it('rejects archive symbolic links', function () {
    $zip = new ZipArchive;
    $zip->open($this->releaseRoot.'/package.zip', ZipArchive::CREATE);
    $zip->addFromString('link', '/tmp');
    $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();
    expect(fn () => releaseInspector()->extract($this->releaseRoot.'/package.zip', $this->releaseRoot))
        ->toThrow(RuntimeException::class, 'links');
});

it('validates matching production dependencies and rejects missing extensions or mismatched locks', function (string $case) {
    $composer = ['require' => ['php' => '^8.0']];
    $package = ['name' => 'example/package', 'version' => '1.0.0', 'require' => $case === 'missing extension' ? ['ext-notinstalledhere' => '*'] : []];
    $lock = ['content-hash' => md5(json_encode($composer)), 'packages' => [$package]];
    if ($case === 'mismatched manifest') {
        $composer['require']['php'] = '^8.4';
    }
    File::put($this->releaseRoot.'/composer.json', json_encode($composer));
    File::put($this->releaseRoot.'/composer.lock', json_encode($lock));
    $installed = ['dev' => $case === 'development packages', 'packages' => [$package]];
    if ($case === 'mismatched vendor') {
        $installed['packages'][0]['version'] = '2.0.0';
    }
    releaseArchive($this->releaseRoot.'/vendor.zip', [
        'vendor/autoload.php' => '<?php', 'vendor/composer/autoload_real.php' => '<?php',
        'vendor/composer/installed.json' => json_encode($installed),
    ]);
    if (in_array($case, ['valid', 'missing extension'], true)) {
        $warnings = releaseInspector()->vendor($this->releaseRoot);
        expect(is_file($this->releaseRoot.'/dependencies/vendor/autoload.php'))->toBeTrue();
        expect($warnings)->toHaveCount($case === 'missing extension' ? 2 : 0);
    } else {
        expect(fn () => releaseInspector()->vendor($this->releaseRoot))->toThrow(RuntimeException::class);
    }
})->with(['valid', 'missing extension', 'mismatched manifest', 'mismatched vendor', 'development packages']);

function releasePlan(string $root, string $failure, bool $migrations = false): array
{
    foreach (['app', 'bootstrap/cache', 'storage/framework', 'vendor', 'stage/app', 'recovery', 'backup'] as $directory) {
        File::ensureDirectoryExists($root.'/'.$directory);
    }
    File::put($root.'/app/existing.txt', 'old');
    File::put($root.'/app/remove.txt', 'restore me');
    File::put($root.'/stage/app/existing.txt', 'new');
    File::put($root.'/stage/app/new.txt', 'new file');
    File::put($root.'/composer.json', 'old composer');
    File::put($root.'/composer.lock', 'old lock');
    File::put($root.'/stage/composer.json', 'new composer');
    File::put($root.'/stage/composer.lock', 'new lock');
    File::put($root.'/failure', $failure);
    File::put($root.'/uploaded-update.zip', 'uploaded package');
    File::put($root.'/vendor.zip', 'legacy vendor package');
    File::put($root.'/vendor/version', 'old');
    File::put($root.'/vendor/autoload.php', <<<'AUTOLOAD'
<?php
namespace Illuminate\Support\Facades {
    class DB {
        public static function connection() { return new self; }
        public function getPdo() { return new \PDO('sqlite:' . dirname(__DIR__) . '/database.sqlite'); }
    }
}
namespace App\Models {
    class SystemUpdate {
        public static function findOrFail($id) { return new self; }
        public function update($attributes) { file_put_contents(dirname(__DIR__) . '/finalized.json', json_encode($attributes)); }
    }
}
namespace { function now() { return date('c'); } }
AUTOLOAD);
    File::put($root.'/bootstrap/app.php', '<?php return new class { public function make($name) { return $this; } public function bootstrap() {} };');
    File::put($root.'/artisan', <<<'ARTISAN'
<?php
$root = __DIR__;
file_put_contents($root . '/commands.log', $argv[1] . ':' . file_get_contents($root . '/vendor/version') . "\n", FILE_APPEND);
if (!is_file($root . '/storage/framework/system-update-maintenance.php')) exit(19);
if ($argv[1] === 'migrate') {
    $pdo = new PDO('sqlite:' . $root . '/database.sqlite');
    $pdo->exec('CREATE TABLE migrated (id INTEGER)');
}
if (trim(file_get_contents($root . '/failure')) === $argv[1]) { fwrite(STDERR, 'simulated command failure'); exit(17); }
ARTISAN);
    File::copyDirectory($root.'/vendor', $root.'/stage/dependencies/vendor');
    File::put($root.'/stage/dependencies/vendor/version', 'new');
    File::copy(base_path('app/Support/SystemUpdateRuntime.php'), $root.'/recovery/runner.php');
    $plan = ['id' => 1, 'root' => $root, 'temp' => $root.'/stage', 'package' => $root.'/uploaded-update.zip', 'backup' => $root.'/backup', 'state' => $root.'/recovery/state.json', 'php' => PHP_BINARY,
        'manifest' => ['version' => '99.0.0', 'vendor_update' => true, 'run_migrations' => $failure === 'migrate' || $migrations,
            'actions' => [
                ['type' => 'replace', 'source' => 'app/existing.txt', 'target' => 'app/existing.txt'],
                ['type' => 'add', 'source' => 'app/new.txt', 'target' => 'app/new.txt'],
                ['type' => 'delete', 'target' => 'app/remove.txt'],
            ], 'commands' => ['config:cache']],
    ];
    File::put($root.'/recovery/plan.json', json_encode($plan));

    return $plan;
}

it('installs prepared dependencies and removes successful update artifacts', function (bool $migrations) {
    $plan = releasePlan($this->releaseRoot, '', $migrations);
    SystemUpdateRuntime::apply($plan, $this->releaseRoot.'/recovery/runner.php');
    $state = json_decode(File::get($plan['state']), true);
    expect($state['status'])->toBe('applied')->and($state['finished'])->toBeTrue();
    expect(File::get($this->releaseRoot.'/commands.log'))->toContain('package:discover:new', 'config:cache:new');
    expect(File::get($this->releaseRoot.'/app/existing.txt'))->toBe('new');
    expect(is_dir($plan['backup']))->toBeFalse();
    expect(is_file($plan['package']))->toBeFalse();
    expect(is_file($this->releaseRoot.'/vendor.zip'))->toBeFalse();
    expect($state['backup'])->toBeNull();
    expect($state['cleanup'])->toBe('completed');
    expect($state['log'])->toContain('Update flow: prebuilt vendor package', 'Composer install/update is not executed', 'Command:', 'Exit code: 0', 'uploaded ZIP and backups removed');
    $history = json_decode(File::get($this->releaseRoot.'/finalized.json'), true);
    expect($history['log'])->toContain('uploaded ZIP and backups removed');
    if ($migrations) expect($state['log'])->toContain('Database backup completed.');
    expect(is_file($this->releaseRoot.'/storage/framework/down'))->toBeFalse();
    expect(is_file($this->releaseRoot.'/storage/framework/system-update-maintenance.php'))->toBeFalse();
    expect(is_dir($plan['temp']))->toBeFalse();
})->with([false, true]);

it('restores overwritten and deleted files and removes newly added files before database changes', function () {
    $plan = releasePlan($this->releaseRoot, 'config:cache');
    SystemUpdateRuntime::apply($plan, $this->releaseRoot.'/recovery/runner.php');
    $state = json_decode(File::get($plan['state']), true);
    expect($state['status'])->toBe('failed');
    expect($state['failed_step'])->toBe('caching');
    expect($state['log'])->toContain('STDERR: simulated command failure', 'Exit code: 17', 'FAILED at step caching', 'config:cache');
    expect(is_file($plan['package']))->toBeTrue();
    expect(is_dir($plan['backup']))->toBeTrue();
    expect(is_dir($plan['temp']))->toBeTrue();
    expect(File::get($this->releaseRoot.'/app/existing.txt'))->toBe('old');
    expect(File::get($this->releaseRoot.'/app/remove.txt'))->toBe('restore me');
    expect(is_file($this->releaseRoot.'/app/new.txt'))->toBeFalse();
    expect(File::get($this->releaseRoot.'/vendor/version'))->toBe('old');
    expect(File::get($this->releaseRoot.'/composer.lock'))->toBe('old lock');
    expect(is_file($this->releaseRoot.'/storage/framework/system-update-maintenance.php'))->toBeFalse();
});

it('backs up the database and keeps maintenance active when migration execution fails', function () {
    $plan = releasePlan($this->releaseRoot, 'migrate');
    SystemUpdateRuntime::apply($plan, $this->releaseRoot.'/recovery/runner.php');
    $state = json_decode(File::get($plan['state']), true);
    expect($state['status'])->toBe('failed');
    expect(is_file($this->releaseRoot.'/backup/database.sqlite'))->toBeTrue();
    expect(is_file($plan['package']))->toBeTrue();
    expect($state['failed_step'])->toBe('migrating');
    expect($state['log'])->toContain('FAILED at step migrating', 'Exit code: 17');
    $backup = new PDO('sqlite:'.$this->releaseRoot.'/backup/database.sqlite');
    expect($backup->query("SELECT count(*) FROM sqlite_master WHERE name='migrated'")->fetchColumn())->toBe(0);
    expect(File::get($this->releaseRoot.'/vendor/version'))->toBe('new');
    expect(is_file($this->releaseRoot.'/storage/framework/system-update-maintenance.php'))->toBeTrue();
    expect($state['log'])->toContain('Database changes may have occurred');
    File::ensureDirectoryExists($this->releaseRoot.'/public');
    File::copy(base_path('public/index.php'), $this->releaseRoot.'/public/index.php');
    File::deleteDirectory($this->releaseRoot.'/vendor');
    $maintenance = new Process([PHP_BINARY, $this->releaseRoot.'/public/index.php']);
    $maintenance->mustRun();
    expect($maintenance->getOutput())->toContain('We are updating our website');
});

it('serves maintenance and private progress without a vendor directory', function () {
    File::ensureDirectoryExists($this->releaseRoot.'/public');
    File::copy(base_path('public/system-update-status.php'), $this->releaseRoot.'/public/system-update-status.php');
    $token = str_repeat('a', 64);
    $directory = $this->releaseRoot.'/storage/app/private/updates/recovery/'.$token;
    File::ensureDirectoryExists($directory);
    File::put($directory.'/state.json', json_encode(['status' => 'failed', 'log' => 'private failure']));
    $script = $this->releaseRoot.'/public/system-update-status.php';
    $authorized = new Process([PHP_BINARY, '-r', '$_SERVER["HTTP_X_SYSTEM_UPDATE_TOKEN"]=$argv[1]; require $argv[2];', $token, $script]);
    $authorized->mustRun();
    expect(json_decode($authorized->getOutput(), true)['log'])->toBe('private failure');
    $unauthorized = new Process([PHP_BINARY, '-r', '$_SERVER["HTTP_X_SYSTEM_UPDATE_TOKEN"]=$argv[1]; require $argv[2];', str_repeat('b', 64), $script]);
    $unauthorized->mustRun();
    expect($unauthorized->getOutput())->not->toContain('private failure');
});

it('builds a foundation package or a vendor package without shipping Composer', function (bool $vendor) {
    $root = $this->releaseRoot;
    File::copy(base_path('build-update.sh'), $root.'/build-update.sh');
    foreach (['app/Http/Controllers/Admin', 'app/Http/Requests/SystemUpdate', 'app/Jobs', 'app/Models', 'app/Services', 'app/Support', 'database/migrations', 'public/assets/js', 'resources/views/admin/system-updates/partials'] as $dir) {
        File::ensureDirectoryExists($root.'/'.$dir);
    }
    File::put($root.'/app/example.php', '<?php');
    File::put($root.'/composer.json', '{}');
    File::put($root.'/composer.lock', '{}');
    foreach (['app/Http/Controllers/Admin/SystemUpdateController.php', 'app/Http/Requests/SystemUpdate/StoreSystemUpdateRequest.php',
        'app/Jobs/RunSystemUpdateJob.php', 'app/Models/SystemUpdate.php', 'app/Services/SystemUpdater.php',
        'app/Support/SystemUpdateProcess.php', 'app/Support/SystemUpdateRuntime.php',
        'database/migrations/2026_08_06_000004_add_failed_at_to_system_updates_table.php',
        'database/migrations/2026_08_22_000001_add_force_update_to_system_updates_table.php',
        'public/assets/js/system-updates.js', 'public/index.php', 'public/system-update-status.php',
        'resources/views/admin/system-updates/index.blade.php',
        'resources/views/admin/system-updates/partials/confirm-update-modal.blade.php'] as $foundation) {
        File::copy(base_path($foundation), $root.'/'.$foundation);
    }
    File::put($root.'/composer.phar', <<<'COMPOSER'
<?php
if (in_array('validate', $argv, true)) exit(0);
foreach (['--no-dev', '--no-scripts', '--no-plugins', '--optimize-autoloader'] as $flag) if (!in_array($flag, $argv, true)) exit(1);
foreach ($argv as $argument) if (str_starts_with($argument, '--working-dir=')) $directory = substr($argument, 14);
mkdir($directory . '/vendor', 0755, true);
file_put_contents($directory . '/vendor/autoload.php', '<?php');
mkdir($directory . '/vendor/example/package/.git/objects', 0755, true);
file_put_contents($directory . '/vendor/example/package/.git/config', 'metadata');
file_put_contents($directory . '/vendor/example/package/runtime.php', '<?php');
COMPOSER);
    File::put($root.'/update.json', json_encode(['version' => '99.0.0', 'min_app_version' => '1.0.0', 'force_update' => false,
        'vendor_update' => $vendor, 'actions' => [['type' => 'add', 'source' => 'app', 'target' => 'app']],
    ]));
    $process = new Process(['bash', 'build-update.sh', 'update.json'], $root, ['INTERACTIVE' => '0']);
    $process->mustRun();
    $manifest = json_decode(File::get($root.'/update-v99.0.0/update.json'), true);
    expect($manifest['vendor_update'])->toBe($vendor);
    expect(is_file($root.'/update-v99.0.0/composer.phar'))->toBeFalse();
    expect(is_file($root.'/update-v99.0.0/vendor.zip'))->toBe($vendor);
    if ($vendor) {
        $archive = new ZipArchive;
        $archive->open($root.'/update-v99.0.0/vendor.zip');
        expect($archive->locateName('vendor/example/package/.git/config'))->toBeFalse();
        expect($archive->locateName('vendor/example/package/.git/'))->toBeFalse();
        expect($archive->locateName('vendor/example/package/runtime.php'))->not->toBeFalse();
        $archive->close();
    }
    expect(array_column($manifest['actions'], 'target'))->toContain(
        'app/Http/Controllers/Admin/SystemUpdateController.php', 'app/Http/Requests/SystemUpdate/StoreSystemUpdateRequest.php',
        'app/Jobs/RunSystemUpdateJob.php', 'app/Models/SystemUpdate.php', 'app/Services/SystemUpdater.php',
        'app/Support/SystemUpdateProcess.php', 'app/Support/SystemUpdateRuntime.php',
        'database/migrations/2026_08_06_000004_add_failed_at_to_system_updates_table.php',
        'database/migrations/2026_08_22_000001_add_force_update_to_system_updates_table.php',
        'public/assets/js/system-updates.js', 'public/index.php', 'public/system-update-status.php',
        'resources/views/admin/system-updates/index.blade.php',
        'resources/views/admin/system-updates/partials/confirm-update-modal.blade.php'
    );
    foreach (['public/index.php', 'public/system-update-status.php'] as $entrypoint) {
        expect(File::get($root.'/update-v99.0.0/'.$entrypoint))->toBe(File::get(base_path($entrypoint)));
    }
})->with([false, true]);

it('identifies the rejected archive entry without disclosing its contents', function (string $entry, string $reason) {
    releaseArchive($this->releaseRoot.'/package.zip', [$entry => 'private contents']);
    expect(fn () => releaseInspector()->extract($this->releaseRoot.'/package.zip', $this->releaseRoot))
        ->toThrow(RuntimeException::class, $reason.': '.$entry);
})->with([
    ['vendor/example/package/.git/config', 'Git metadata cannot be included in update packages'],
    ['.env', 'Secrets and database files cannot be included in update packages'],
    ['database/database.sqlite', 'Secrets and database files cannot be included in update packages'],
]);

it('logs cleanup failures without rolling back the successfully installed release', function () {
    $plan = releasePlan($this->releaseRoot, '');
    unlink($plan['package']);
    File::put($this->releaseRoot.'/unrelated.txt', 'keep');
    symlink($this->releaseRoot.'/unrelated.txt', $plan['package']);
    SystemUpdateRuntime::apply($plan, $this->releaseRoot.'/recovery/runner.php');
    $state = json_decode(File::get($plan['state']), true);
    expect($state['status'])->toBe('applied');
    expect($state['cleanup'])->toBe('incomplete');
    expect($state['log'])->toContain('Cleanup warning: Uploaded update ZIP', 'Refusing to remove a symbolic link');
    expect(File::get($this->releaseRoot.'/vendor/version'))->toBe('new');
    expect(File::get($this->releaseRoot.'/unrelated.txt'))->toBe('keep');
    expect(is_file($this->releaseRoot.'/storage/framework/system-update-maintenance.php'))->toBeFalse();
    expect(json_decode(File::get($this->releaseRoot.'/finalized.json'), true)['log'])->toContain('cleanup warnings');
});

it('identifies updates that retain existing dependencies', function () {
    $plan = releasePlan($this->releaseRoot, '');
    $plan['manifest']['vendor_update'] = false;
    File::put($this->releaseRoot.'/recovery/plan.json', json_encode($plan));
    SystemUpdateRuntime::apply($plan, $this->releaseRoot.'/recovery/runner.php');
    $state = json_decode(File::get($plan['state']), true);
    expect($state['status'])->toBe('applied');
    expect($state['log'])->toContain('Update flow: application files only (vendor_update=false)', 'Existing dependencies are retained');
    expect(File::get($this->releaseRoot.'/vendor/version'))->toBe('old');
});
