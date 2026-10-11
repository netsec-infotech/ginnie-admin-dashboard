<?php

namespace App\Console\Commands;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Interfaces\BulkImportable;
use App\Models\BulkUploadJob;
use App\Services\BulkUpload\BulkUploadJobService;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Process pending bulk_upload_jobs rows in chunks.
 * Picks one runnable job per tick, advances its cursor by rows_per_tick,
 * persists per-row outcomes to the error_log, and finalises on the last row.
 */
class ProcessBulkUploadsCommand extends Command
{
    protected $signature = 'bulk-uploads:run';

    protected $description = 'Process one pending bulk_upload_jobs row, chunked by rows_per_tick.';

    public function handle(BulkUploadJobService $jobService, BulkUploadModuleRegistry $registry): int
    {
        $job = BulkUploadJob::query()
            ->runnable()
            ->orderBy('id')
            ->first();

        if (! $job) {
            return self::SUCCESS;
        }

        $moduleConfig = $registry->getByModel($job->uploadable_type);
        if (! $moduleConfig) {
            $jobService->markFailed($job, __('labels.bulk_upload_unknown_uploadable_type'));
            $this->warn("No importer registered for {$job->uploadable_type} — job #{$job->id} marked failed.");
            return self::SUCCESS;
        }

        /** @var BulkImportable $importer */
        $importer = app($moduleConfig->importerClass);

        if ($job->status === BulkUploadJobStatusEnum::PENDING()) {
            $jobService->markProcessing($job);
            $jobService->appendLog($job, __('labels.bulk_upload_started', ['total' => $job->total_rows]));
        }

        $chunk = (int) config('bulk_upload.rows_per_tick', 300);
        $disk = $job->file_disk ?: config('bulk_upload.file_disk', 'local');
        $handle = $jobService->openAtOffset($disk, $job->file_path, $job->processed_rows);
        if ($handle === null) {
            $jobService->markFailed($job, __('labels.bulk_upload_file_unreadable'));
            return self::SUCCESS;
        }

        // First pass: re-read the header from the start so we can key rows by header name.
        $headers = $this->readHeaderSeparately($disk, $job->file_path, $jobService);
        if ($headers === null) {
            fclose($handle);
            $jobService->markFailed($job, __('labels.bulk_upload_csv_no_headers'));
            return self::SUCCESS;
        }

        $processedInTick = 0;
        $rowNumber = $job->processed_rows + 1; // human-friendly (1-based, excludes header)
        $cancelled = false;
        $failedRowsBuffer = [];

        try {
            while ($processedInTick < $chunk && ($raw = fgetcsv($handle)) !== false) {
                $row = [];
                foreach ($headers as $idx => $header) {
                    $row[$header] = $raw[$idx] ?? null;
                }

                [$outcome, $message] = $importer->processRow($row, $job);
                $line = '#' . $rowNumber . ' ' . ($message ?: $outcome);
                // Per-row persistence — counters + log advance live for the poller.
                $jobService->recordRowOutcome($job, $outcome, $line);

                if ($outcome === BulkImportable::OUTCOME_FAILED) {
                    $failedRowsBuffer[] = [
                        'row_number' => $rowNumber,
                        'row' => $raw,
                        'error' => $message ?: $outcome,
                    ];
                }

                $processedInTick++;
                $rowNumber++;

                $currentStatus = BulkUploadJob::query()->whereKey($job->id)->toBase()->value('status');
                if ($currentStatus === BulkUploadJobStatusEnum::CANCELLED()) {
                    $cancelled = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            if (! empty($failedRowsBuffer)) {
                $jobService->appendFailedRows($job, $headers, $failedRowsBuffer);
                $failedRowsBuffer = [];
            }

            Log::error('bulk-uploads:run row processing crashed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
            $jobService->markFailed($job, __('labels.bulk_upload_crash', ['reason' => $e->getMessage()]));
            return self::SUCCESS;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (! empty($failedRowsBuffer)) {
                $jobService->appendFailedRows($job, $headers, $failedRowsBuffer);
            }
        }
        $persistedStatus = BulkUploadJob::query()->whereKey($job->id)->toBase()->value('status');
        if ($cancelled || $persistedStatus === BulkUploadJobStatusEnum::CANCELLED()) {
            $this->info("Job #{$job->id} cancelled at {$job->processed_rows}/{$job->total_rows}.");
            return self::SUCCESS;
        }

        if ($job->processed_rows >= $job->total_rows) {
            $jobService->appendLog($job, __('labels.bulk_upload_completed_log', [
                'success' => $job->success_count,
                'failed' => $job->failed_count,
            ]));
            $jobService->markCompleted($job);
            $this->info("Job #{$job->id} completed: {$job->success_count} ok, {$job->failed_count} failed.");
        } else {
            $this->info("Job #{$job->id} progressed: {$job->processed_rows}/{$job->total_rows}.");
        }

        return self::SUCCESS;
    }

    /**
     * Read just the header row of the stored CSV. openAtOffset already skipped
     * the header on the data handle, so this re-opens once to grab the names.
     *
     * @return array<int,string>|null
     */
    private function readHeaderSeparately(string $disk, string $path, BulkUploadJobService $jobService): ?array
    {
        $storage = \Illuminate\Support\Facades\Storage::disk($disk);
        $absolute = method_exists($storage, 'path') ? $storage->path($path) : null;
        if (! $absolute || ! is_readable($absolute)) {
            return null;
        }
        $h = fopen($absolute, 'r');
        if ($h === false) {
            return null;
        }
        try {
            $row = fgetcsv($h);
            if (! $row) {
                return null;
            }
            return array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
        } finally {
            fclose($h);
        }
    }
}
