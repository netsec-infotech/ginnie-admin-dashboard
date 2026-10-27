<?php

namespace App\Installer\Helpers;

use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DatabaseManager
{
    public function __construct(private readonly InstallationProgress $progress) {}

    /**
     * Migrate and seed the database.
     *
     * @return array
     */
    public function migrateAndSeed()
    {
        try {
            $this->sqlite();
            $this->progress->completePhase('preflight', 'Database preflight completed.');
        } catch (Throwable $e) {
            return $this->failure('preflight', 'Database preflight failed.', $e);
        }

        return $this->migrate();
    }

    /**
     * Run the migration and call the seeder.
     *
     * @return array
     */
    private function migrate()
    {
        $migrations = $this->migrationNames();
        $total = count($migrations);
        $completed = 0;

        $this->progress->advance(
            'migrations',
            0,
            $total,
            $total > 0
                ? "Preparing a clean database with {$total} migrations."
                : 'Preparing a clean database.'
        );

        Event::listen(MigrationEnded::class, function (MigrationEnded $event) use (&$completed, $total) {
            if ($event->method !== 'up') {
                return;
            }

            $completed++;

            $name = class_basename($event->migration);

            // Anonymous migrations all have a generic class name,
            // so use reflection to get the actual migration filename.
            try {
                $reflection = new \ReflectionClass($event->migration);

                if ($reflection->isAnonymous() && $reflection->getFileName()) {
                    $name = pathinfo(
                        $reflection->getFileName(),
                        PATHINFO_FILENAME
                    );
                }
            } catch (\Throwable $e) {
                // Keep the class basename as fallback.
            }

            $this->progress->advance(
                'migrations',
                $completed,
                max(1, $total),
                "Migration {$completed} of " . max(1, $total) . " completed: {$name}."
            );
        });

        try {
            $exitCode = Artisan::call('db:wipe', [
                '--drop-views' => true,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    "Database cleanup command exited with status {$exitCode}."
                );
            }

            $this->progress->advance(
                'migrations',
                0,
                $total,
                'Existing database tables were removed. Running migrations.'
            );

            $exitCode = Artisan::call('migrate', [
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    "Migration command exited with status {$exitCode}."
                );
            }
        } catch (Throwable $e) {
            return $this->failure(
                'migrations',
                'Database migration failed.',
                $e
            );
        }

        $this->progress->completePhase(
            'migrations',
            'All database migrations completed.'
        );

        return $this->seed();
    }

    /**
     * Seed the database.
     *
     * @return array
     */
    private function seed()
    {
        $seeders = array_values(array_filter((array) config('installer.seeders', []), 'is_string'));
        $total = count($seeders);
        $this->progress->advance('seeders', 0, $total, $total > 0
            ? "Preparing {$total} required seeders."
            : 'No required seeders configured.');

        foreach ($seeders as $index => $seeder) {
            try {
                $exitCode = Artisan::call('db:seed', [
                    '--class' => $seeder,
                    '--force' => true,
                ]);

                if ($exitCode !== 0) {
                    throw new \RuntimeException("Seeder command exited with status {$exitCode}.");
                }
            } catch (Throwable $e) {
                return $this->failure('seeders', 'Required database seeding failed.', $e);
            }

            $completed = $index + 1;
            $this->progress->advance(
                'seeders',
                $completed,
                max(1, $total),
                "Seeder {$completed} of " . max(1, $total) . ' completed: ' . class_basename($seeder) . '.'
            );
        }

        $this->progress->completePhase('seeders', 'All required seeders completed.');

        return $this->response(trans('installer_messages.final.finished'), 'success');
    }

    private function migrationNames(): array
    {
        try {
            $migrator = app(Migrator::class);
            $files = $migrator->getMigrationFiles(database_path('migrations'));

            return array_keys($files);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function failure(string $phase, string $message, Throwable $exception): array
    {
        $reference = 'INST-' . strtoupper(Str::random(10));
        Log::error("{$message} Reference: {$reference}", ['exception' => $exception]);
        $this->progress->fail($phase, $message, $reference);

        return [
            'status' => 'danger',
            'message' => $message,
            'reference' => $reference,
        ];
    }

    /**
     * Return a formatted error messages.
     *
     * @param  string  $status
     * @return array
     */
    private function response($message, $status = 'danger')
    {
        return [
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * check database type. If SQLite, then create the database file.
     */
    private function sqlite()
    {
        if (DB::connection() instanceof SQLiteConnection) {
            $database = DB::connection()->getDatabaseName();
            if (! file_exists($database)) {
                touch($database);
                DB::reconnect(Config::get('database.default'));
            }
        }
    }
}
