<?php

namespace App\Console\Commands;

use App\Services\LanguagePackSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncLanguagePacksCommand extends Command
{
    protected $signature = 'languages:sync-packs {--surface=all : all, panel, web, customer_app, seller_app, or rider_app}';

    protected $description = 'Insert missing bundled interface translations for active and inactive installed languages.';

    public function handle(LanguagePackSyncService $sync): int
    {
        try {
            $report = $sync->sync((string) $this->option('surface'));
            $this->table(['Surface', 'Locale', 'Status', 'Inserted', 'Existing', 'Skipped', 'Failed', 'Message'],
                array_map(fn (array $row): array => [$row['surface'], $row['locale'], $row['status'], $row['inserted'], $row['existing'], $row['skipped'], $row['failed'], $row['message']], $report['results']));
            $this->line(json_encode($report['totals'], JSON_THROW_ON_ERROR));

            return $report['totals']['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
