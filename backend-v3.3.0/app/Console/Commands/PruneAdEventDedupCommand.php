<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneAdEventDedupCommand extends Command
{
    protected $signature = 'ads:prune-dedup {--days=7 : Days to retain dedup rows}';

    protected $description = 'Delete ad event dedup rows older than the specified retention period.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days)->toDateString();

        $deleted = DB::table('ad_event_dedup')
            ->where('event_date', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} dedup rows older than {$days} days.");
        Log::info("ads:prune-dedup removed {$deleted} rows older than {$cutoff}.");

        return self::SUCCESS;
    }
}
