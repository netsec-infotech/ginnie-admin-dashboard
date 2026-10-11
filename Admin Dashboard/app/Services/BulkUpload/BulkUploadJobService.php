<?php

namespace App\Services\BulkUpload;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Interfaces\BulkImportable;
use App\Models\BulkUploadJob;
use App\Models\User;
use App\Notifications\BulkUploadFinishedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Generic lifecycle around the bulk_upload_jobs table — module-agnostic.
 * Per-module row processing lives in BulkImportable implementations
 * (e.g. BrandBulkImportService).
 */
class BulkUploadJobService
{
    /**
     * Store a freshly-uploaded CSV on the configured disk under a per-upload UUID
     * directory. Returns the relative path so the controller can hand it back to
     * the mapping step.
     */
    public function storeUploadedFile(UploadedFile $file): array
    {
        $disk = config('bulk_upload.file_disk', 'local');
        $tempDir = trim((string) config('bulk_upload.temp_dir', 'bulk-uploads'), '/');
        $token = (string) Str::uuid();
        $relativeDir = $tempDir . '/' . $token;
        $original = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($original, PATHINFO_FILENAME)) . '.csv';
        $path = $file->storeAs($relativeDir, $safeName, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'token' => $token,
            'original_filename' => $original,
        ];
    }

    /**
     * Parse a stored CSV and return headers + the first N rows for the mapping UI.
     *
     * @return array{headers: array<int,string>, rows: array<int,array<int,string>>, total_rows: int}
     */
    public function previewCsv(string $disk, string $path, int $previewRows): array
    {
        $absolute = $this->resolveAbsolutePath($disk, $path);
        if ($absolute === null || ! is_readable($absolute)) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $handle = fopen($absolute, 'r');
        if ($handle === false) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        try {
            $headers = fgetcsv($handle) ?: [];
            $headers = array_map(fn ($h) => is_string($h) ? trim($h) : (string) $h, $headers);

            $rows = [];
            $totalRows = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;
                if (count($rows) < $previewRows) {
                    $rows[] = $row;
                }
            }

            return ['headers' => $headers, 'rows' => $rows, 'total_rows' => $totalRows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Count data rows in a stored CSV (excludes header).
     */
    public function countDataRows(string $disk, string $path): int
    {
        $absolute = $this->resolveAbsolutePath($disk, $path);
        if ($absolute === null || ! is_readable($absolute)) {
            return 0;
        }

        $handle = fopen($absolute, 'r');
        if ($handle === false) {
            return 0;
        }
        try {
            fgetcsv($handle); // skip header
            $count = 0;
            while (fgetcsv($handle) !== false) {
                $count++;
            }
            return $count;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Return the latest pending or processing job for the given uploadable type.
     * Used by the one-at-a-time lockout in the controller.
     */
    public function findActiveJob(string $uploadableType): ?BulkUploadJob
    {
        return BulkUploadJob::query()
            ->forType($uploadableType)
            ->runnable()
            ->orderByDesc('id')
            ->first();
    }

    public function createPendingJob(
        string $uploadableType,
        ?int $userId,
        string $disk,
        string $path,
        string $originalFilename,
        array $columnMapping,
        array $defaultValues,
        int $totalRows,
        string $operation = 'create',
        ?string $matchField = null,
        bool $notifyOnFinish = false,
    ): BulkUploadJob {
        return BulkUploadJob::create([
            'uploadable_type' => $uploadableType,
            'user_id' => $userId,
            'file_disk' => $disk,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'column_mapping' => $columnMapping,
            'default_values' => $defaultValues,
            'total_rows' => $totalRows,
            'status' => BulkUploadJobStatusEnum::PENDING(),
            'operation' => $operation,
            'match_field' => $matchField,
            'notify_on_finish' => $notifyOnFinish,
        ]);
    }

    public function markProcessing(BulkUploadJob $job): void
    {
        $job->update([
            'status' => BulkUploadJobStatusEnum::PROCESSING(),
            'started_at' => $job->started_at ?? Carbon::now(),
        ]);
    }

    public function appendLog(BulkUploadJob $job, string $line): void
    {
        $line = '[' . Carbon::now()->format('Y-m-d H:i:s') . '] ' . $line;
        $job->error_log = $job->error_log ? $job->error_log . PHP_EOL . $line : $line;
        $job->save();
    }

    public function bumpCursor(BulkUploadJob $job, int $processed, int $success, int $failed): void
    {
        $job->processed_rows += $processed;
        $job->success_count += $success;
        $job->failed_count += $failed;
        $job->save();
    }

    /**
     * Persist a single row's outcome: increments processed_rows, the matching
     * outcome counter (created/updated/skipped/failed), and appends a
     * timestamped log line — all in one save. Per-row persistence is what
     * makes the detail page progress bar advance live; aggregating until the
     * end of a tick freezes the UI at 0%.
     *
     * `success_count` is overloaded to mean "created" so existing dashboards
     * keep working unchanged in Create mode.
     */
    public function recordRowOutcome(BulkUploadJob $job, string $outcome, string $line): void
    {
        $job->processed_rows = $job->processed_rows + 1;
        switch ($outcome) {
            case BulkImportable::OUTCOME_CREATED:
                $job->success_count = $job->success_count + 1;
                break;
            case BulkImportable::OUTCOME_UPDATED:
                $job->updated_count = $job->updated_count + 1;
                break;
            case BulkImportable::OUTCOME_SKIPPED:
                $job->skipped_count = $job->skipped_count + 1;
                break;
            case BulkImportable::OUTCOME_FAILED:
            default:
                $job->failed_count = $job->failed_count + 1;
                break;
        }
        $stamped = '[' . Carbon::now()->format('Y-m-d H:i:s') . '] ' . $line;
        $job->error_log = $job->error_log ? $job->error_log . PHP_EOL . $stamped : $stamped;
        $job->save();
    }

    public function markCompleted(BulkUploadJob $job): void
    {
        $job->update([
            'status' => BulkUploadJobStatusEnum::COMPLETED(),
            'finished_at' => Carbon::now(),
        ]);
        $this->deleteArtifacts($job);
        $this->dispatchFinishedNotification($job);
    }

    public function markFailed(BulkUploadJob $job, string $reason): void
    {
        $this->appendLog($job, $reason);
        $job->update([
            'status' => BulkUploadJobStatusEnum::FAILED(),
            'finished_at' => Carbon::now(),
        ]);
        $this->deleteArtifacts($job);
        $this->dispatchFinishedNotification($job);
    }

    /**
     * Cancel skips the finished-notification dispatch — the admin clicked
     * cancel themselves and doesn't need a "your job finished" ping.
     */
    public function markCancelled(BulkUploadJob $job): void
    {
        $job->update([
            'status' => BulkUploadJobStatusEnum::CANCELLED(),
            'finished_at' => Carbon::now(),
        ]);
        $this->deleteArtifacts($job);
    }

    /**
     * Fire the BulkUploadFinishedNotification if the job opted in and we know
     * who launched it. Wrapped in try/catch so a notification crash never
     * sabotages the job lifecycle — markCompleted/markFailed must always
     * succeed even if FCM / mail / DB notification all fail.
     */
    protected function dispatchFinishedNotification(BulkUploadJob $job): void
    {
        if (! $job->notify_on_finish || ! $job->user_id) {
            return;
        }

        try {
            $user = User::query()->find($job->user_id);
            if ($user) {
                $user->notify(new BulkUploadFinishedNotification($job));
            }
        } catch (\Throwable $e) {
            Log::warning('BulkUploadJobService: notify_on_finish dispatch failed for job '.$job->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete the uploaded CSV on terminal state. Keeps the per-row errors CSV
     * (if any) untouched so the admin can still download it from the job
     * detail page — the errors file lives in the same token dir, so the dir
     * survives as long as the errors CSV is in it.
     *
     * Idempotent.
     */
    public function deleteArtifacts(BulkUploadJob $job): void
    {
        try {
            $disk = $job->file_disk ?: config('bulk_upload.file_disk', 'local');
            $storage = Storage::disk($disk);
            if ($job->file_path && $storage->exists($job->file_path)) {
                $storage->delete($job->file_path);
            }
            if ($job->file_path) {
                $dir = dirname($job->file_path);
                if ($dir && $dir !== '.' && $storage->exists($dir) && empty($storage->files($dir))) {
                    $storage->deleteDirectory($dir);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('BulkUploadJobService: failed to delete artifacts for job ' . $job->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Full token-dir nuke — used only by the prune command when force-deleting
     * a terminal job row. Clears whatever's left (input CSV remnants, errors
     * CSV) so prune leaves nothing behind. Idempotent.
     */
    public function deleteAllArtifacts(BulkUploadJob $job): void
    {
        try {
            $disk = $job->file_disk ?: config('bulk_upload.file_disk', 'local');
            $storage = Storage::disk($disk);
            $path = $job->file_path ?: $job->failed_rows_path;
            if (! $path) {
                return;
            }
            $dir = dirname($path);
            if ($dir && $dir !== '.' && $storage->exists($dir)) {
                $storage->deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            Log::warning('BulkUploadJobService: failed to nuke token dir for job ' . $job->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Append failed rows to the per-job errors CSV.
     *
     * Lives at the same token dir as the input CSV (e.g.
     * bulk-uploads/<token>/_errors.csv). On the first call for a job, writes
     * the header row [_row, ...input headers, _error] and persists the path
     * on the job so the download endpoint can find it. Subsequent calls just
     * append rows.
     *
     * @param  array<int, string>                                              $headers
     * @param  array<int, array{row_number:int, row:array<int,mixed>, error:string}>  $entries
     */
    public function appendFailedRows(BulkUploadJob $job, array $headers, array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        $disk = $job->file_disk ?: config('bulk_upload.file_disk', 'local');
        $storage = Storage::disk($disk);
        $dir = $job->file_path ? dirname($job->file_path) : null;
        if (! $dir) {
            return;
        }

        $errorsRelative = $dir . '/_errors.csv';
        try {
            $absolute = $storage->path($errorsRelative);
            $isNew = ! $storage->exists($errorsRelative);

            $handle = fopen($absolute, 'a');
            if ($handle === false) {
                throw new \RuntimeException('Could not open errors CSV for append: ' . $errorsRelative);
            }

            try {
                if ($isNew) {
                    fputcsv($handle, array_merge(['_row'], $headers, ['_error']));
                }
                foreach ($entries as $entry) {
                    $row = array_values((array) ($entry['row'] ?? []));
                    fputcsv($handle, array_merge([(string) ($entry['row_number'] ?? '')], $row, [(string) ($entry['error'] ?? '')]));
                }
            } finally {
                fclose($handle);
            }

            if ($job->failed_rows_path !== $errorsRelative) {
                $job->failed_rows_path = $errorsRelative;
                $job->save();
            }
        } catch (\Throwable $e) {
            Log::warning('BulkUploadJobService: failed to append error rows for job ' . $job->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Open a CSV positioned at the given data-row index (0 = first row after header).
     * Caller is responsible for fclose().
     *
     * @return resource|null
     */
    public function openAtOffset(string $disk, string $path, int $offset)
    {
        $absolute = $this->resolveAbsolutePath($disk, $path);
        if ($absolute === null || ! is_readable($absolute)) {
            return null;
        }
        $handle = fopen($absolute, 'r');
        if ($handle === false) {
            return null;
        }

        // skip header
        fgetcsv($handle);

        // fast-forward to the offset
        for ($i = 0; $i < $offset; $i++) {
            if (fgetcsv($handle) === false) {
                break;
            }
        }
        return $handle;
    }

    private function resolveAbsolutePath(string $disk, string $path): ?string
    {
        try {
            $storage = Storage::disk($disk);
            $absolute = method_exists($storage, 'path') ? $storage->path($path) : null;
            return $absolute && file_exists($absolute) ? $absolute : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
