<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * [CREATED BY AGENT]: CLI Command to sync cloud storage media files back to local server disk.
 */
class SyncCloudToLocalCommand extends Command
{
    protected $signature = 'media:sync-local
                            {--delete-source : Delete cloud bucket files after successful verification locally}
                            {--dry-run : Simulate sync without modifying local storage or database}';

    protected $description = 'Sync cloud media library files back to local server storage (public disk)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');

        $this->info("Starting cloud media synchronization back to local server storage" . ($dryRun ? ' (DRY RUN)' : '') . "...");

        $count = 0;
        $success = 0;
        $failed = 0;

        Media::query()
            ->whereNotIn('disk', ['public', 'local'])
            ->chunkById(50, function ($mediaItems) use ($dryRun, $deleteSource, &$count, &$success, &$failed) {
                foreach ($mediaItems as $media) {
                    $count++;
                    $sourceDisk = $media->disk;
                    $relativePath = $media->getPathRelativeToRoot();

                    if (!Storage::disk($sourceDisk)->exists($relativePath)) {
                        $this->warn("Skipping Media ID #{$media->id}: File not found on remote cloud disk [{$sourceDisk}] at [{$relativePath}]");
                        $failed++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->info("[DRY RUN] Would download Media ID #{$media->id} ({$media->file_name}) from [{$sourceDisk}] to [public]");
                        $success++;
                        continue;
                    }

                    try {
                        $stream = Storage::disk($sourceDisk)->readStream($relativePath);

                        if ($stream === false) {
                            throw new \RuntimeException("Unable to read remote file from disk [{$sourceDisk}]");
                        }

                        Storage::disk('public')->put($relativePath, $stream);

                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        if (!Storage::disk('public')->exists($relativePath)) {
                            throw new \RuntimeException("File verification failed on local public disk after download");
                        }

                        $media->disk = 'public';
                        $media->save();

                        if ($deleteSource) {
                            Storage::disk($sourceDisk)->delete($relativePath);
                        }

                        $this->info("Synced Media ID #{$media->id} ({$media->file_name}) back to local public storage");
                        $success++;
                    } catch (\Throwable $e) {
                        $this->error("Error syncing Media ID #{$media->id}: " . $e->getMessage());
                        Log::error("Media local sync failed for ID {$media->id}: " . $e->getMessage());
                        $failed++;
                    }
                }
            });

        $this->info("Sync completed. Total inspected: {$count}, Success: {$success}, Failed/Skipped: {$failed}");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
