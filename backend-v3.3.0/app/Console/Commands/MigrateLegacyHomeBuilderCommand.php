<?php

namespace App\Console\Commands;

use App\Services\HomeBuilderLegacyMigrationService;
use Illuminate\Console\Command;

class MigrateLegacyHomeBuilderCommand extends Command
{
    protected $signature = 'home-builder:migrate-legacy {--dry-run : Show what would be migrated without writing data}';

    protected $description = 'Migrate legacy featured sections and banners into the home builder manually';

    public function handle(HomeBuilderLegacyMigrationService $service): int
    {
        $stats = $service->migrate((bool) $this->option('dry-run'));

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No data was changed.');
        } else {
            $this->info('Legacy home builder migration complete.');
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($count, $metric) => [
            'Metric' => $metric,
            'Count' => $count,
        ])->values()->all());

        return self::SUCCESS;
    }
}
