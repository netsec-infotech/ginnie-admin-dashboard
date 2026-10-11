<?php

namespace App\Console\Commands;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Models\BulkUploadJob;
use App\Services\BulkUpload\BulkUploadJobService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Daily cleanup pass for the bulk-upload pipeline. Two responsibilities:
 *
 *  1. Hard-delete old terminal job rows so the jobs list stays useful and
 *     bulk_upload_jobs doesn't grow forever. COMPLETED rows and FAILED /
 *     CANCELLED rows have independent retention windows — failures stick
 *     around longer because their error log has debugging value.
 *  2. Sweep orphan CSV files under the configured temp_dir. A CSV is an
 *     orphan if no non-terminal job references its path AND the directory
 *     is older than the configured threshold. Covers preview uploads that
 *     were never confirmed and stragglers a crashed cron tick left behind.
 */
class PruneBulkUploadJobsCommand extends Command
{
    protected $signature = 'bulk-uploads:prune';

    protected $description = 'Hard-delete old terminal bulk_upload_jobs rows and sweep orphan CSVs.';

    public function handle(BulkUploadJobService $jobService): int
    {
        $retentionDays = (int) config('bulk_upload.retention_days', 7);
        $retentionFailedDays = (int) config('bulk_upload.retention_failed_days', 30);
        $orphanHours = (int) config('bulk_upload.orphan_csv_hours', 6);

        $orphansRemoved = $this->sweepOrphanCsvs($orphanHours);
        $completedRemoved = $this->pruneTerminalRows(
            statuses: [BulkUploadJobStatusEnum::COMPLETED()],
            cutoff: Carbon::now()->subDays($retentionDays),
            jobService: $jobService,
        );
        $failedRemoved = $this->pruneTerminalRows(
            statuses: [BulkUploadJobStatusEnum::FAILED(), BulkUploadJobStatusEnum::CANCELLED()],
            cutoff: Carbon::now()->subDays($retentionFailedDays),
            jobService: $jobService,
        );

        $summary = sprintf(
            'bulk-uploads:prune — orphans removed: %d, completed rows removed: %d, failed/cancelled rows removed: %d',
            $orphansRemoved,
            $completedRemoved,
            $failedRemoved,
        );
        $this->info($summary);
        Log::info($summary);

        return self::SUCCESS;
    }

    /**
     * Delete CSV files / per-token directories under temp_dir that aren't
     * referenced by any non-terminal job and are older than $hours.
     *
     * The "non-terminal" filter is important — terminal jobs already have
     * their CSV cleaned by markCompleted/Failed/Cancelled, but if any
     * straggler survived we still want it gone here.
     */
    private function sweepOrphanCsvs(int $hours): int
    {
        $disk = (string) config('bulk_upload.file_disk', 'local');
        $tempDir = trim((string) config('bulk_upload.temp_dir', 'bulk-uploads'), '/');
        $cutoffTimestamp = Carbon::now()->subHours(max($hours, 1))->getTimestamp();
        $storage = Storage::disk($disk);

        if (! $storage->exists($tempDir)) {
            return 0;
        }

        // Dirs we won't touch: any job (terminal or not) that still has a row
        // in the DB owns its token dir. Terminal jobs may be keeping a
        // per-row errors CSV around for the admin to download — those dirs
        // get cleaned only when the row itself is force-deleted (see
        // pruneTerminalRows). The sweep is for truly orphan dirs (preview
        // uploads never confirmed, crashed cron with no row).
        $activePaths = BulkUploadJob::query()
            ->withTrashed()
            ->where(function ($q) {
                $q->whereNotNull('file_path')->orWhereNotNull('failed_rows_path');
            })
            ->get(['file_path', 'failed_rows_path'])
            ->flatMap(fn ($job) => array_filter([$job->file_path, $job->failed_rows_path]))
            ->all();
        $activeDirs = array_unique(array_map(fn ($p) => dirname((string) $p), $activePaths));

        $removed = 0;
        foreach ($storage->directories($tempDir) as $dir) {
            if (in_array($dir, $activeDirs, true)) {
                continue; // belongs to a job that's still queued or processing
            }

            $lastModified = $this->resolveDirectoryMtime($storage, $dir);
            if ($lastModified === null || $lastModified > $cutoffTimestamp) {
                continue;
            }

            try {
                if ($storage->deleteDirectory($dir)) {
                    $removed++;
                }
            } catch (\Throwable $e) {
                Log::warning("bulk-uploads:prune could not delete orphan dir {$dir}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $removed;
    }

    /**
     * Hard-delete (forceDelete) rows whose status is in $statuses and whose
     * finished_at is older than $cutoff. Includes soft-deleted rows so they
     * get permanently cleared once their window passes. Calls deleteArtifacts
     * defensively before each force-delete to make sure no CSV stays behind.
     *
     * @param  array<int, string>  $statuses
     */
    private function pruneTerminalRows(array $statuses, Carbon $cutoff, BulkUploadJobService $jobService): int
    {
        $removed = 0;

        BulkUploadJob::query()
            ->withTrashed()
            ->whereIn('status', $statuses)
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$removed, $jobService) {
                foreach ($rows as $job) {
                    try {
                        // Full token-dir nuke — input CSV remnants + the
                        // errors CSV both go. Use deleteAllArtifacts (not the
                        // terminal-state deleteArtifacts which deliberately
                        // keeps the errors CSV around for download).
                        $jobService->deleteAllArtifacts($job);
                        $job->forceDelete();
                        $removed++;
                    } catch (\Throwable $e) {
                        Log::warning('bulk-uploads:prune row delete failed', [
                            'job_id' => $job->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $removed;
    }

    /**
     * Resolve the "freshness" timestamp for an orphan-candidate directory.
     *
     * We use the youngest contained file's mtime, NOT the directory's own
     * mtime — POSIX directories don't update their mtime when a file inside
     * is touched/backdated, and the semantic we care about ("when did
     * anything in this dir last change") is per-file. An empty leftover
     * directory is treated as ancient so the sweep clears it.
     */
    private function resolveDirectoryMtime($storage, string $dir): ?int
    {
        try {
            $files = $storage->files($dir);
            if (empty($files)) {
                return 0; // empty leftover dir — clear it
            }
            $youngest = 0;
            foreach ($files as $f) {
                $m = (int) $storage->lastModified($f);
                if ($m > $youngest) {
                    $youngest = $m;
                }
            }
            return $youngest ?: null;
        } catch (\Throwable) {
            // last-resort fallback to whatever mtime the adapter exposes for
            // the directory itself.
        }

        try {
            $mtime = $storage->lastModified($dir);
            return is_int($mtime) && $mtime > 0 ? $mtime : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
