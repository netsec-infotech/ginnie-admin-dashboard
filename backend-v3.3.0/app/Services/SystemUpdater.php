<?php

namespace App\Services;

use App\Enums\SystemUpdateStatusEnum;
use App\Enums\SystemUpdateStepEnum;
use App\Models\Setting;
use App\Models\SystemUpdate;
use App\Support\SystemUpdateRuntime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class SystemUpdater
{
    public const UPDATES_DIR = 'updates';

    public const TEMP_DIR = 'updates/tmp';

    public const BACKUP_DIR = 'updates/backups';

    /** Commands that are safe to run as part of an update run. */
    protected array $allowedCommands = [
        'config:clear',
        'config:cache',
        'route:clear',
        'route:cache',
        'view:clear',
        'view:cache',
        'optimize',
        'optimize:clear',
        'storage:link',
        'package:discover',
    ];

    /* ===================================================================
     * Public API — called from the HTTP request (synchronous, fast).
     * =================================================================== */

    /**
     * Move the uploaded ZIP onto local storage and create a pending row.
     * Returns the row so the controller can dispatch the queue job.
     */
    public function prepareUpload(UploadedFile $zip, int $userId): SystemUpdate
    {
        if (strtolower($zip->getClientOriginalExtension()) !== 'zip') {
            throw new \InvalidArgumentException('Only ZIP files are allowed.');
        }

        $this->ensureDirs();
        $lock = fopen(Storage::disk('local')->path(self::UPDATES_DIR.'/run.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Another update is running.');
        }
        try {
            $this->guardConcurrentRun();

            $storedPath = $zip->storeAs(
                self::UPDATES_DIR,
                pathinfo($zip->getClientOriginalName(), PATHINFO_FILENAME)
                .'_'.bin2hex(random_bytes(6)).'.zip',
                'local'
            );

            $checksum = @hash_file('sha256', Storage::disk('local')->path($storedPath)) ?: null;

            return SystemUpdate::create([
                'version' => 'unknown',
                'package_name' => basename($storedPath),
                'checksum' => $checksum,
                'status' => SystemUpdateStatusEnum::PENDING->value,
                'step' => SystemUpdateStepEnum::QUEUED->value,
                'progress' => 0,
                'heartbeat_at' => now(),
                'applied_by' => $userId ?: null,
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /* ===================================================================
     * Queued side — called from ApplySystemUpdateJob.
     * =================================================================== */

    /**
     * Execute a prepared update row. Only called from the queue worker.
     */
    public function run(int $updateId, ?callable $beforeApply = null): void
    {
        $this->ensureDirs();
        $lock = fopen(Storage::disk('local')->path(self::UPDATES_DIR.'/run.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            throw new \RuntimeException('Another update is running.');
        }
        $update = SystemUpdate::findOrFail($updateId);
        if ($update->status !== SystemUpdateStatusEnum::PENDING) {
            flock($lock, LOCK_UN);
            fclose($lock);

            return;
        }
        $tempDir = Storage::disk('local')->path(self::TEMP_DIR.'/'.$updateId);
        try {
            $package = Storage::disk('local')->path(self::UPDATES_DIR.'/'.$update->package_name);
            if (! is_file($package) || ! hash_equals($update->checksum ?? '', hash_file('sha256', $package))) {
                throw new \RuntimeException('The uploaded package is missing or its checksum changed.');
            }
            $update->update(['step' => 'verifying', 'heartbeat_at' => now()]);
            $manifest = $this->inspectPackage($package, $tempDir);
            $token = File::get(Storage::disk('local')->path(self::UPDATES_DIR.'/'.$updateId.'.token'));
            $recovery = storage_path('app/private/updates/recovery/'.$token);
            File::ensureDirectoryExists($recovery, 0700);
            $plan = [
                'id' => $updateId, 'root' => base_path(), 'temp' => $tempDir, 'package' => $package,
                'backup' => Storage::disk('local')->path(self::BACKUP_DIR.'/'.$updateId),
                'state' => $recovery.'/state.json', 'manifest' => $manifest,
                'php' => $this->getPhpCliBinary(),
            ];
            File::copy(base_path('app/Support/SystemUpdateRuntime.php'), $recovery.'/runner.php');
            File::put($recovery.'/plan.json', json_encode($plan, JSON_THROW_ON_ERROR));
            $update->update([
                'version' => $manifest['version'], 'min_supported_version' => $manifest['min_app_version'] ?? null,
                'force_update' => $manifest['force_update'], 'notes' => $manifest['notes'] ?? '',
                'heartbeat_at' => now(),
            ]);
            if (! class_exists(SystemUpdateRuntime::class, false)) {
                require_once $recovery.'/runner.php';
            }
        } catch (\Throwable $error) {
            File::deleteDirectory($tempDir);
            $update->update(['status' => 'failed', 'failed_at' => now(), 'log' => $error->getMessage()]);
            $tokenFile = Storage::disk('local')->path(self::UPDATES_DIR.'/'.$updateId.'.token');
            if (is_file($tokenFile)) {
                $state = storage_path('app/private/updates/recovery/'.File::get($tokenFile).'/state.json');
                File::put($state, json_encode(['id' => $updateId, 'status' => 'failed', 'finished' => true, 'step' => 'verifying', 'log' => $error->getMessage(), 'heartbeat_at' => date('c')]));
            }
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $error;
        }
        if ($beforeApply) {
            $beforeApply();
        }
        SystemUpdateRuntime::apply($plan, $recovery.'/runner.php');
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    public function inspectPackage(string $package, string $directory): array
    {
        File::ensureDirectoryExists($directory, 0700);
        if (! is_writable(base_path()) || ! is_writable(storage_path('framework')) || ! is_file(public_path('system-update-status.php'))) {
            throw new \RuntimeException('The updater foundation and writable application directories are required.');
        }
        if (PHP_SAPI !== 'cli' && extension_loaded('Zend OPcache') && ini_get('opcache.enable') && ! ini_get('opcache.validate_timestamps')) {
            throw new \RuntimeException('Ask hosting support to enable OPcache timestamp validation before using automatic updates.');
        }
        $this->extractArchive($package, $directory);
        if (is_dir($directory.'/dependencies')) {
            throw new \RuntimeException('The dependencies directory is reserved for staged vendor files.');
        }
        $manifest = json_decode(File::get($directory.'/update.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->validateManifest($manifest);
        if (! empty($manifest['run_migrations']) || ! empty($manifest['run_seeders'])) {
            if (! in_array(config('database.connections.'.config('database.default').'.driver'), ['mysql', 'mariadb', 'sqlite'], true)) {
                throw new \RuntimeException('Automatic database backup requires MySQL/MariaDB or SQLite.');
            }
        }
        foreach ($manifest['actions'] as $action) {
            $this->validateAction($action, $directory);
        }
        foreach ($manifest['commands'] ?? [] as $command) {
            if (! is_string($command) || ! in_array($command, $this->allowedCommands, true)) {
                throw new \RuntimeException('Unsupported update command. Use a listed cache command without arguments.');
            }
        }
        if (! function_exists('proc_open')) {
            throw new \RuntimeException('Your host must enable proc_open for update checks and Artisan commands. Composer is not required.');
        }
        if (! str_contains(File::get(public_path('index.php')), 'system-update-maintenance.php')) {
            throw new \RuntimeException('Install the updater foundation release before applying this package.');
        }
        $process = new Process([$this->getPhpCliBinary(), '-r', 'echo json_encode(["php"=>PHP_VERSION,"bits"=>PHP_INT_SIZE,"extensions"=>array_combine(get_loaded_extensions(),array_map(fn($e)=>phpversion($e)?:"0",get_loaded_extensions()))]);']);
        $process->setTimeout(15)->mustRun();
        $platform = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        if (! empty($manifest['vendor_update'])) {
            $manifest['platform_warnings'] = $this->validateVendorUpdate($directory, $platform);
        }

        return $manifest;
    }

    protected function extractArchive(string $archive, string $directory, ?string $prefix = null): void
    {
        SystemUpdateRuntime::extractArchive($archive, $directory, $prefix);
    }

    protected function safePath(string $path): void
    {
        SystemUpdateRuntime::safePath($path);
    }

    protected function validateAction(array $action, string $directory): void
    {
        $type = $action['type'] ?? '';
        if (! in_array($type, ['add', 'replace', 'delete'], true)) {
            throw new \RuntimeException('Invalid update action.');
        }
        $target = $action['target'] ?? '';
        $this->safePath($target);
        $roots = ['app', 'routes', 'config', 'resources', 'public', 'database', 'packages', 'bootstrap', 'tests'];
        if (! in_array(explode('/', $target)[0], $roots, true)) {
            throw new \RuntimeException('Update target is outside allowed roots: '.$target);
        }
        if ($target === 'bootstrap' || str_starts_with($target, 'bootstrap/cache') || $target === 'public' || str_starts_with($target, 'public/storage')) {
            throw new \RuntimeException('Protected update target: '.$target);
        }
        $parent = base_path($target);
        while ($parent !== base_path()) {
            if (is_link($parent)) {
                throw new \RuntimeException('Update targets cannot follow symbolic links.');
            }
            if (file_exists($parent) && ! is_writable($parent)) {
                throw new \RuntimeException('Update target is not writable: '.$target);
            }
            $parent = dirname($parent);
        }
        if ($type !== 'delete') {
            $source = $action['source'] ?? '';
            $this->safePath($source);
            if (! file_exists($directory.'/'.$source)) {
                throw new \RuntimeException('Package source is missing: '.$source);
            }
            if ($target === 'public/index.php' && ! str_contains(File::get($directory.'/'.$source), 'system-update-maintenance.php')) {
                throw new \RuntimeException('The new entry point must preserve the update maintenance guard.');
            }
            if ($target === 'public/system-update-status.php' && ! str_contains(File::get($directory.'/'.$source), 'HTTP_X_SYSTEM_UPDATE_TOKEN')) {
                throw new \RuntimeException('The recovery entry point must preserve private log authentication.');
            }
        } elseif (in_array($target, ['public/index.php', 'public/system-update-status.php'], true)) {
            throw new \RuntimeException('The application entry point cannot be deleted.');
        }
    }

    protected function validateVendorUpdate(string $directory, array $cli): array
    {
        return SystemUpdateRuntime::validateVendorUpdate($directory, $cli);
    }

    public function reconcileStuckRuns(): int
    {
        $lock = @fopen(Storage::disk('local')->path(self::UPDATES_DIR.'/run.lock'), 'c');
        if ($lock && ! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return 0;
        }
        $healed = 0;
        foreach (SystemUpdate::where('status', 'pending')->get() as $row) {
            if (! $row->isStale()) {
                continue;
            }
            $row->update(['status' => 'failed', 'failed_at' => now(), 'heartbeat_at' => now(),
                'log' => trim(($row->log ?? '')."\nFAILED: stuck — no heartbeat; inspect the recovery log before retrying.")]);
            $healed++;
        }
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $healed;
    }

    protected function guardConcurrentRun(): void
    {
        if (is_file(storage_path('framework/system-update-maintenance.php'))) {
            throw new \RuntimeException('An update requires recovery. Resolve it before starting another update.');
        }
        SystemUpdate::where('status', 'pending')->get()->filter(fn ($row) => $row->isStale())->each(fn ($row) => $row->update(['status' => 'failed', 'failed_at' => now()]));
        if (SystemUpdate::where('status', 'pending')->exists()) {
            throw new \RuntimeException('Another update is already in progress.');
        }
    }

    protected function validateManifest(array $m): void
    {
        if (empty($m['version']) || empty($m['actions']) || ! array_key_exists('force_update', $m)) {
            throw new \InvalidArgumentException('Invalid update.json: version, force_update, and actions are required.');
        }

        foreach (['vendor_update', 'run_migrations', 'run_seeders'] as $flag) {
            if (isset($m[$flag]) && ! is_bool($m[$flag])) {
                throw new \InvalidArgumentException($flag.' must be a boolean.');
            }
        }
        if (! is_array($m['actions']) || ! is_bool($m['force_update'])) {
            throw new \InvalidArgumentException('Invalid update.json: force_update must be a boolean.');
        }

        $current = Setting::getCurrentVersion();
        $newVersion = (string) $m['version'];
        $minApp = isset($m['min_app_version']) ? (string) $m['min_app_version'] : null;

        if ($minApp !== null && version_compare($current, $minApp, '<')) {
            throw new \RuntimeException("This update requires app version {$minApp} or higher. Current version is {$current}.");
        }

        if (version_compare($newVersion, $current, '<=')) {
            throw new \RuntimeException("Update version {$newVersion} must be greater than current version {$current}.");
        }
    }

    protected function ensureDirs(): void
    {
        foreach ([self::UPDATES_DIR, self::TEMP_DIR, self::BACKUP_DIR] as $dir) {
            File::ensureDirectoryExists(Storage::disk('local')->path($dir));
        }
    }

    /**
     * Resolve a PHP CLI binary that can actually execute artisan.
     * Order: PHP_CLI_BINARY env override → Symfony finder → common paths → PHP_BINARY.
     */
    protected function getPhpCliBinary(): string
    {
        $override = env('PHP_CLI_BINARY');
        if ($override && is_executable($override)) {
            return $override;
        }

        $found = (new PhpExecutableFinder)->find(false);
        if ($found && is_executable($found) && ! str_contains($found, 'php-fpm')) {
            return $found;
        }

        $version = (string) PHP_MAJOR_VERSION.'.'.(string) PHP_MINOR_VERSION;
        $candidates = [
            "/usr/bin/php{$version}",
            "/usr/local/bin/php{$version}",
            '/usr/bin/php',
            '/usr/local/bin/php',
        ];
        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return PHP_BINARY;
    }
}
