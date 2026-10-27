<?php

namespace App\Console\Commands;

use App\Services\CurrencyBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HyperlocalCurrencyBackfillCommand extends Command
{
    protected $signature = 'hyperlocal:currency-backfill
        {--dry-run : Inspect rows without writing changes}
        {--execute : Write safe Phase 2 snapshot values}
        {--batch=500 : Number of records to inspect per batch}
        {--only= : Comma-separated sections: orders,payments,pos-refunds,currency-metadata}
        {--resume= : Resume an existing execute run id}
        {--json : Print the backfill report as JSON}
        {--output= : Write the JSON report to a safe file under storage/app/currency-audits}';

    protected $description = 'Safely backfill Phase 2 currency snapshot fields without changing financial amounts.';

    public function handle(CurrencyBackfillService $service): int
    {
        if ($this->option('dry-run') && $this->option('execute')) {
            $this->error('Use either --dry-run or --execute, not both.');

            return self::FAILURE;
        }

        $batch = max(1, (int) $this->option('batch'));
        $sections = $this->sections();

        $report = $service->run([
            'execute' => (bool) $this->option('execute'),
            'batch' => $batch,
            'only' => $sections,
            'resume' => $this->option('resume') ? (int) $this->option('resume') : null,
        ]);

        if ($this->option('json') || $this->option('output')) {
            return $this->renderJson($report);
        }

        $this->renderHumanReport($report);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function sections(): array
    {
        $only = $this->option('only');
        if (! is_string($only) || trim($only) === '') {
            return [];
        }

        return collect(explode(',', $only))
            ->map(fn (string $section) => trim($section))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderJson(array $report): int
    {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            $this->error('Unable to encode currency backfill report.');

            return self::FAILURE;
        }

        $output = $this->option('output');
        if (is_string($output) && $output !== '') {
            $path = $this->safeOutputPath($output);

            if ($path === null) {
                $this->error('Unsafe output path. Use storage/app/currency-audits/*.json.');

                return self::FAILURE;
            }

            if (File::exists($path)) {
                $this->error("Refusing to overwrite existing report: {$path}");

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json . PHP_EOL);
            $this->info("Currency backfill JSON report written to: {$path}");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderHumanReport(array $report): void
    {
        $this->line('Hyperlocal Currency Backfill');
        $this->line('Mode: ' . strtoupper((string) $report['mode']));
        $this->line('Run ID: ' . ($report['run_id'] ?? 'dry-run'));
        $this->newLine();

        foreach (($report['summary']['totals'] ?? []) as $section => $stats) {
            if (! is_array($stats)) {
                continue;
            }

            $this->line(sprintf(
                '%s: inspected %d, eligible %d, updated %d, skipped %d, conflicts %d',
                Str::headline($section),
                $stats['total'] ?? 0,
                $stats['eligible'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['skipped'] ?? 0,
                $stats['conflicts'] ?? 0,
            ));
        }

        $this->newLine();
        foreach ($report['findings'] as $finding) {
            $sample = $finding['sample_ids'] ? ' Sample IDs: ' . implode(', ', $finding['sample_ids']) . '.' : '';
            $this->line(sprintf(
                '[%s] %s/%s: %s Records: %d.%s',
                strtoupper($finding['severity']),
                $finding['category'],
                $finding['code'],
                $finding['message'],
                $finding['record_count'],
                $sample,
            ));
        }

        $this->newLine();
        $this->line('Final status: ' . ($report['summary']['final_status'] ?? 'UNKNOWN'));
    }

    private function safeOutputPath(string $output): ?string
    {
        $base = storage_path('app/currency-audits');
        $path = $output;

        if (! Str::endsWith(strtolower($path), '.json')) {
            return null;
        }

        if (! preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) && ! Str::startsWith($path, ['/', '\\'])) {
            $path = base_path($path);
        }

        $normalizedBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base);
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return Str::startsWith($normalizedPath, $normalizedBase . DIRECTORY_SEPARATOR) ? $normalizedPath : null;
    }
}
