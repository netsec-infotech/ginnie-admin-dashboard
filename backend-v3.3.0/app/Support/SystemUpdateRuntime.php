<?php

namespace App\Support;

use App\Models\SystemUpdate;
use DirectoryIterator;
use FilesystemIterator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class SystemUpdateRuntime
{
    public static function write(string $path, string $content): void
    {
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true)) {
            throw new RuntimeException('Cannot create '.dirname($path));
        }
        $temporary = $path.'.tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Cannot write '.$path);
        }
    }

    public static function copy(string $source, string $target): void
    {
        if (is_link($source) || is_link($target)) {
            throw new RuntimeException('Symbolic links cannot be updated: '.$source);
        }
        if (is_dir($source)) {
            if (! is_dir($target) && ! mkdir($target, 0755, true)) {
                throw new RuntimeException('Cannot create '.$target);
            }
            foreach (new DirectoryIterator($source) as $entry) {
                if (! $entry->isDot()) {
                    self::copy($entry->getPathname(), $target.'/'.$entry->getFilename());
                }
            }
        } else {
            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true)) {
                throw new RuntimeException('Cannot create destination directory.');
            }
            if (! copy($source, $target)) {
                throw new RuntimeException('Cannot copy '.$source);
            }
        }
    }

    public static function remove(string $path): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Refusing to remove a symbolic link: '.$path);
        }
        if (is_dir($path)) {
            foreach (new DirectoryIterator($path) as $entry) {
                if (! $entry->isDot()) {
                    self::remove($entry->getPathname());
                }
            }
            if (! rmdir($path)) {
                throw new RuntimeException('Cannot remove directory '.$path);
            }
        } elseif (file_exists($path) && ! unlink($path)) {
            throw new RuntimeException('Cannot remove file '.$path);
        }
    }

    public static function command(array $arguments, string $root, callable $log, int $timeout = 600): void
    {
        $display = implode(' ', array_map('escapeshellarg', $arguments));
        $log('Command: '.$display);
        $log('Working directory: '.$root);
        $pipes = [];
        $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start command: '.$display);
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $started = time();
        $heartbeat = time();
        try {
            do {
                foreach ([1, 2] as $pipe) {
                    $text = stream_get_contents($pipes[$pipe]);
                    if ($text !== false && $text !== '') {
                        $log(($pipe === 2 ? 'STDERR: ' : 'STDOUT: ').trim($text));
                    }
                }
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }
                if (time() - $started > $timeout) {
                    proc_terminate($process, 9);
                    throw new RuntimeException('Command timed out after '.$timeout.' seconds: '.$display);
                }
                if (time() - $heartbeat > 10) {
                    $log('Command is still running…');
                    $heartbeat = time();
                }
                usleep(100000);
            } while (true);
            foreach ([1, 2] as $pipe) {
                $text = stream_get_contents($pipes[$pipe]);
                if ($text !== false && $text !== '') {
                    $log(($pipe === 2 ? 'STDERR: ' : 'STDOUT: ').trim($text));
                }
            }
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
        }
        $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $exit;
        $log('Exit code: '.$exit);
        if ($exit !== 0) {
            throw new RuntimeException('Command failed with exit code '.$exit.': '.$display);
        }
    }

    public static function extractArchive(string $archive, string $directory, ?string $prefix = null): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Cannot open update ZIP.');
        }
        try {
            $bytes = 0;
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                if (preg_match('~(^|/)\.git(/|$)~', $name)) {
                    throw new \RuntimeException('Git metadata cannot be included in update packages: '.$name.'. Rebuild the package without .git files and folders.');
                }
                if (preg_match('~(^|/)\.env(?:\.[^/]*)?(/|$)|^database/.*\.(sqlite|sqlite3|db)$~', $name)) {
                    throw new \RuntimeException('Secrets and database files cannot be included in update packages: '.$name);
                }
                self::safePath(rtrim($name, '/'));
                if ($prefix && ! str_starts_with($name, $prefix)) {
                    throw new \RuntimeException('vendor.zip must contain a vendor/ directory at its root.');
                }
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                $kind = ($attributes >> 16) & 0170000;
                if ($kind && ! in_array($kind, [0100000, 0040000], true)) {
                    throw new \RuntimeException('Archive links and special files are not allowed.');
                }
                if (isset($names[$name])) {
                    throw new \RuntimeException('Duplicate archive entry: '.$name);
                }
                $names[$name] = true;
                $bytes += $entry['size'];
                if ($bytes > 2147483648 || $zip->numFiles > 100000) {
                    throw new \RuntimeException('The extracted package exceeds the update limit.');
                }
            }
            if (disk_free_space($directory) < $bytes * 3 + 104857600) {
                throw new \RuntimeException('Not enough free disk space to prepare and back up this update.');
            }
            if (! $zip->extractTo($directory)) {
                throw new \RuntimeException('Failed to extract update package.');
            }
        } finally {
            $zip->close();
        }
    }

    public static function safePath(string $path): void
    {
        if ($path === '' || preg_match('~(^/|\\\\|:|[\x00-\x1f]|(^|/)\.\.?(/|$))~', $path)) {
            throw new \RuntimeException('Unsafe path in update package: '.$path);
        }
    }

    public static function validateVendorUpdate(string $directory, array $cli, ?array $website = null, bool $extracted = false): array
    {
        foreach (['composer.json', 'composer.lock', 'vendor.zip'] as $file) {
            if (! is_file($directory.'/'.$file)) {
                throw new \RuntimeException('Vendor update is missing '.$file);
            }
        }
        if (! $extracted) {
            if (! is_dir($directory.'/dependencies')) mkdir($directory.'/dependencies', 0700, true);
            self::extractArchive($directory.'/vendor.zip', $directory.'/dependencies', 'vendor/');
        }
        $vendor = $directory.'/dependencies/vendor';
        foreach (['autoload.php', 'composer/installed.json', 'composer/autoload_real.php'] as $file) {
            if (! is_file($vendor.'/'.$file)) {
                throw new \RuntimeException('Incomplete production vendor package: '.$file);
            }
        }
        $composer = json_decode(file_get_contents($directory.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $lock = json_decode(file_get_contents($directory.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        $installed = json_decode(file_get_contents($vendor.'/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! isset($lock['packages'], $installed['packages']) || ($installed['dev'] ?? true)) {
            throw new \RuntimeException('Build vendor with composer install --no-dev before packaging.');
        }
        $expected = array_column($lock['packages'], 'version', 'name');
        $actual = array_column($installed['packages'], 'version', 'name');
        ksort($expected);
        ksort($actual);
        if ($actual !== $expected) {
            throw new \RuntimeException('vendor.zip does not match composer.lock.');
        }
        $keys = ['name', 'version', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'minimum-stability', 'prefer-stable', 'repositories', 'extra'];
        $relevant = array_intersect_key($composer, array_flip($keys));
        if (isset($composer['config']['platform'])) {
            $relevant['config']['platform'] = $composer['config']['platform'];
        }
        ksort($relevant);
        if (! hash_equals($lock['content-hash'] ?? '', md5(json_encode($relevant, JSON_THROW_ON_ERROR)))) {
            throw new \RuntimeException('composer.json does not match composer.lock. Rebuild the release dependencies.');
        }
        $requirements = [$composer['require'] ?? [], $lock['platform'] ?? []];
        foreach ($lock['packages'] as $package) {
            $requirements[] = $package['require'] ?? [];
        }
        $web = $website ?? ['php' => PHP_VERSION, 'bits' => PHP_INT_SIZE, 'extensions' => array_combine(get_loaded_extensions(), array_map(fn ($name) => phpversion($name) ?: '0', get_loaded_extensions()))];
        $warnings = [];
        foreach (['website' => $web, 'command-line PHP' => $cli] as $label => $platform) {
            foreach ($requirements as $set) {
                foreach ($set as $name => $constraint) {
                    if ($name === 'php' || $name === 'php-64bit') {
                        $version = $platform['php'];
                        if ($name === 'php-64bit' && $platform['bits'] !== 8) {
                            throw new \RuntimeException($label.' requires 64-bit PHP.');
                        }
                    } elseif (str_starts_with($name, 'ext-')) {
                        $version = array_change_key_case($platform['extensions'])[substr($name, 4)] ?? null;
                        if ($version === null) {
                            $warnings[] = $label.' is missing '.$name.'. Features using this extension may not work until hosting support enables it.';
                            continue;
                        }
                    } elseif (str_starts_with($name, 'lib-') || str_starts_with($name, 'php-')) {
                        throw new \RuntimeException('This release has an unsupported platform check: '.$name);
                    } else {
                        continue;
                    }
                    if (! \Composer\Semver\Semver::satisfies($version, $constraint)) {
                        if (str_starts_with($name, 'ext-')) {
                            $warnings[] = $label.' has '.$name.' '.$version.' but this release requires '.$constraint.'. Features using this extension may not work until hosting support updates it.';
                            continue;
                        }
                        throw new \RuntimeException($label.' does not meet '.$name.' '.$constraint);
                    }
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    public static function apply(array $plan, string $runner): void
    {
        $root = $plan['root'];
        $manifest = $plan['manifest'];
        $guard = $root.'/storage/framework/system-update-maintenance.php';
        $down = $root.'/storage/framework/down';
        $state = ['id' => $plan['id'], 'version' => $manifest['version'], 'status' => 'pending', 'step' => 'verifying', 'progress' => 15, 'log' => '', 'backup' => $plan['backup'], 'finished' => false];
        $log = function (string $message) use (&$state, $plan): void {
            $state['heartbeat_at'] = date('c');
            $state['log'] .= '['.date('Y-m-d H:i:s').'] '.$message."\n";
            self::write($plan['state'], json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        };
        $step = function (string $name, int $progress) use (&$state, $log): void {
            $state['step'] = $name;
            $state['progress'] = $progress;
            $log('Step: '.$name);
        };
        $artisan = function (string $command) use ($plan, $log, $root): void {
            $log('Running artisan '.$command);
            self::command([$plan['php'], $root.'/artisan', ...explode(' ', $command)], $root, $log);
        };
        $finalize = function () use ($plan, $runner, $root, $log): void {
            self::command([$plan['php'], $runner, 'finalize', dirname($plan['state']).'/plan.json'], $root, $log);
        };
        $journal = [];
        $maintenance = false;
        $databaseChanged = false;
        $filesChanged = false;
        try {
            if (file_exists($guard) || file_exists($down)) {
                throw new RuntimeException('The website is already in maintenance mode.');
            }
            $log(! empty($manifest['vendor_update'])
                ? 'Update flow: prebuilt vendor package (vendor_update=true). Dependencies come from the validated vendor.zip, composer.json and composer.lock.'
                : 'Update flow: application files only (vendor_update=false). Existing dependencies are retained.');
            $log('Composer install/update is not executed on this server.');
            if (! empty($manifest['vendor_update'])) {
                foreach ($manifest['platform_warnings'] ?? [] as $warning) {
                    $log('Compatibility warning: '.$warning);
                }
                if (! empty($manifest['platform_warnings'])) {
                    $log('Fallback installation: installing the prepared vendor.zip directly without Composer platform checks.');
                }
            }
            $targets = array_column($manifest['actions'], 'target');
            $targets[] = 'bootstrap/cache';
            if (! empty($manifest['vendor_update'])) {
                array_push($targets, 'vendor', 'composer.json', 'composer.lock');
            }
            usort($targets, fn ($a, $b) => strlen($a) <=> strlen($b));
            foreach ($targets as $target) {
                foreach (array_keys($journal) as $saved) {
                    if ($target === $saved || str_starts_with($target, $saved.'/')) {
                        continue 2;
                    }
                }
                $journal[$target] = file_exists($root.'/'.$target);
            }
            $bytes = 0;
            foreach ($journal as $target => $exists) {
                $path = $root.'/'.$target;
                if (! $exists) {
                    continue;
                }
                if (is_link($path)) {
                    throw new RuntimeException('Cannot back up symbolic link: '.$target);
                }
                if (is_dir($path)) {
                    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $entry) {
                        if ($entry->isLink()) {
                            throw new RuntimeException('Cannot back up symbolic link: '.$entry->getPathname());
                        }
                        $bytes += $entry->getSize();
                    }
                } else {
                    $bytes += filesize($path);
                }
            }
            if (disk_free_space(dirname($plan['state'])) < $bytes * 2 + 104857600) {
                throw new RuntimeException('Not enough disk space for the release backup.');
            }
            self::write($guard, '<?php http_response_code(503); header("Retry-After: 60"); header("Cache-Control: no-store"); ?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance</title><body style="font-family:system-ui;max-width:36rem;margin:15vh auto;padding:2rem;text-align:center"><h1>We are updating our website</h1><p>Please check back shortly.</p></body></html><?php exit;');
            $maintenance = true;
            self::write($down, json_encode(['time' => time(), 'retry' => 60, 'status' => 503], JSON_THROW_ON_ERROR));
            $artisan('queue:restart');
            $step('applying', 25);
            $log('Maintenance enabled. Backing up the current release.');
            self::write($plan['backup'].'/journal.json', json_encode($journal, JSON_THROW_ON_ERROR));
            foreach ($journal as $target => $exists) {
                if ($exists) {
                    self::copy($root.'/'.$target, $plan['backup'].'/files/'.$target);
                }
            }
            if (! empty($manifest['run_migrations']) || ! empty($manifest['run_seeders'])) {
                $log('Backing up the database before changing files.');
                self::command([$plan['php'], $runner, 'backup', dirname($plan['state']).'/plan.json'], $root, $log);
            }
            $filesChanged = true;
            foreach ($manifest['actions'] as $action) {
                $target = $root.'/'.$action['target'];
                if ($action['type'] === 'delete') {
                    self::remove($target);
                } else {
                    self::copy($plan['temp'].'/'.$action['source'], $target);
                }
                $log($action['type'].': '.$action['target']);
            }
            if (! empty($manifest['vendor_update'])) {
                $step('vendor', 55);
                self::remove($root.'/vendor');
                self::copy($plan['temp'].'/dependencies/vendor', $root.'/vendor');
                foreach (['composer.json', 'composer.lock'] as $file) {
                    self::copy($plan['temp'].'/'.$file, $root.'/'.$file);
                }
                $log('Prepared dependencies installed.');
            }
            foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $file) {
                self::remove($file);
            }
            $artisan('package:discover');
            if (! empty($manifest['run_migrations'])) {
                $step('migrating', 65);
                $databaseChanged = true;
                $log('Database changes are starting. Automatic file rollback is now disabled.');
                $artisan('migrate --force');
            }
            if (! empty($manifest['run_seeders'])) {
                $step('seeding', 75);
                $databaseChanged = true;
                $artisan('db:seed --force');
            }
            $step('caching', 85);
            foreach ($manifest['commands'] ?? [] as $command) {
                $artisan($command);
            }
            $step('finalizing', 95);
            self::command([$plan['php'], $runner, 'health', dirname($plan['state']).'/plan.json'], $root, $log);
            $state['status'] = 'applied';
            $state['progress'] = 100;
            $log('Update verified successfully.');
            $finalize();
            $log('Update verified and recorded. Removing maintenance mode.');
            self::remove($down);
            self::remove($guard);
            $maintenance = false;
        } catch (Throwable $error) {
            $state['status'] = 'failed';
            $state['failed_step'] = $state['step'];
            $log('FAILED at step '.$state['failed_step'].': '.$error->getMessage());
            $restored = ! $filesChanged;
            if ($filesChanged && ! $databaseChanged) {
                $step('rolling_back', 95);
                try {
                    foreach ($journal as $target => $exists) {
                        self::remove($root.'/'.$target);
                        if ($exists) {
                            self::copy($plan['backup'].'/files/'.$target, $root.'/'.$target);
                        }
                    }
                    self::command([$plan['php'], $runner, 'health', dirname($plan['state']).'/plan.json'], $root, $log);
                    $restored = true;
                    $log('Previous release restored and verified.');
                } catch (Throwable $restoreError) {
                    $log('Recovery required: '.$restoreError->getMessage());
                }
            }
            if ($databaseChanged) {
                $log('Maintenance retained. Database changes may have occurred. Restore the database backup and matching files together with hosting support.');
            }
            try {
                $finalize();
            } catch (Throwable $finalizeError) {
                $log('Could not save update history: '.$finalizeError->getMessage());
            }
            if ($maintenance && $restored && ! $databaseChanged) {
                self::remove($down);
                self::remove($guard);
                $log('Website reopened on the previous release.');
            }
            $state['finished'] = true;
            $log('Update stopped. Backups created for this run, the uploaded ZIP and the recovery log are retained.');
        }
        if ($state['status'] === 'applied' && ! $maintenance) {
            $log("Website reopened. Removing this update's temporary files and backups.");
            $state['cleanup'] = 'completed';
            $cleanup = ['Temporary update files' => $plan['temp'], 'Uploaded update ZIP' => $plan['package'], 'Database and file backups' => $plan['backup']];
            if (! empty($manifest['vendor_update'])) {
                $cleanup['Legacy root vendor archive'] = $root.'/vendor.zip';
            }
            foreach ($cleanup as $label => $path) {
                try {
                    self::remove($path);
                    if ($path === $plan['backup']) {
                        $state['backup'] = null;
                    }
                    $log('Deleted '.$label.': '.$path);
                } catch (Throwable $cleanupError) {
                    $state['cleanup'] = 'incomplete';
                    $log('Cleanup warning: '.$label.' could not be fully deleted: '.$cleanupError->getMessage());
                }
            }
            $state['finished'] = true;
            $log($state['cleanup'] === 'completed'
                ? 'Update completed. Temporary files, uploaded ZIP and backups removed; update log retained.'
                : 'Update completed with cleanup warnings. The website remains online; remove the remaining paths listed above manually.');
            try {
                $finalize();
            } catch (Throwable $historyError) {
                $log('History log sync warning: '.$historyError->getMessage().'. The complete log remains available through the private update link.');
            }
        }
    }

    public static function backupDatabase(PDO $pdo, string $path): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('VACUUM INTO '.$pdo->quote($path.'.sqlite'));

            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Automatic database backup supports MySQL/MariaDB and SQLite. This update was stopped before changing files.');
        }
        if ($pdo->query('SELECT 1 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() LIMIT 1')->fetch() || $pdo->query('SELECT 1 FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE() LIMIT 1')->fetch() || $pdo->query('SHOW TRIGGERS')->fetch() || $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetch()) {
            throw new RuntimeException('This database has custom routines, events, triggers or views. A hosting-managed backup is required; update stopped before changing files.');
        }
        $file = fopen($path.'.sql', 'xb');
        if (! $file) {
            throw new RuntimeException('Cannot create database backup.');
        }
        $write = function (string $text) use ($file): void {
            if (fwrite($file, $text) !== strlen($text)) {
                throw new RuntimeException('Database backup could not be written completely.');
            }
        };
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
        try {
            $write("SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $name = '`'.str_replace('`', '``', $table).'`';
                $schema = $pdo->query('SHOW CREATE TABLE '.$name)->fetch(PDO::FETCH_NUM)[1];
                $write('DROP TABLE IF EXISTS '.$name.";\n".$schema.";\n");
                $fields = $pdo->query('SHOW COLUMNS FROM '.$name)->fetchAll(PDO::FETCH_ASSOC);
                $fields = array_filter($fields, fn ($field) => ! str_contains($field['Extra'], 'VIRTUAL GENERATED') && ! str_contains($field['Extra'], 'STORED GENERATED'));
                $select = implode(',', array_map(fn ($field) => '`'.str_replace('`', '``', $field['Field']).'`', $fields));
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $rows = $pdo->query('SELECT '.$select.' FROM '.$name);
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $columns = implode(',', array_map(fn ($column) => '`'.str_replace('`', '``', $column).'`', array_keys($row)));
                    $values = implode(',', array_map(fn ($value) => $value === null ? 'NULL' : "UNHEX('".bin2hex((string) $value)."')", $row));
                    $write('INSERT INTO '.$name.' ('.$columns.') VALUES ('.$values.");\n");
                }
                $rows->closeCursor();
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        } finally {
            fclose($file);
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $plan = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
        require $plan['root'].'/vendor/autoload.php';
        $app = require $plan['root'].'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if ($argv[1] === 'backup') {
            SystemUpdateRuntime::backupDatabase(DB::connection()->getPdo(), $plan['backup'].'/database');
            echo "Database backup completed.\n";
        } elseif ($argv[1] === 'health') {
            DB::connection()->getPdo()->query('SELECT 1');
            echo "Application boot and database connection verified.\n";
        } elseif ($argv[1] === 'finalize') {
            $state = json_decode(file_get_contents($plan['state']), true, 512, JSON_THROW_ON_ERROR);
            SystemUpdate::findOrFail($plan['id'])->update([
                'status' => $state['status'], 'step' => $state['step'], 'progress' => $state['progress'],
                'log' => $state['log'], 'heartbeat_at' => now(),
                'applied_at' => $state['status'] === 'applied' ? now() : null,
                'failed_at' => $state['status'] === 'failed' ? now() : null,
            ]);
        } else {
            throw new RuntimeException('Unknown update operation.');
        }
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage()."\n");
        exit(1);
    }
}
