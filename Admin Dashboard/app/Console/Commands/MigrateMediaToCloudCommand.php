<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * [CREATED BY AGENT]: CLI Command to migrate existing Spatie Media Library items from local storage to cloud storage.
 */
class MigrateMediaToCloudCommand extends Command
{
    protected $signature = 'media:migrate-cloud
                            {--disk= : Target cloud disk name (s3, r2)}
                            {--dry-run : Simulate migration without modifying files or database}
                            {--delete-source : Delete local source files after successful verification on cloud}';

    protected $description = 'Migrate existing media library files from any disk to the active cloud storage provider (AWS S3 or Cloudflare R2)';

    public function handle(): int
    {
        $targetDisk = $this->option('disk') ?: config('media-library.disk_name', 's3');

        if (in_array($targetDisk, ['local', 'public'], true)) {
            $this->error("Target disk [{$targetDisk}] is local storage. Please specify a cloud disk (--disk=s3 or --disk=r2).");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');

        $this->info("Starting media migration from other disks to [{$targetDisk}]".($dryRun ? ' (DRY RUN)' : '').'...');

        $count = 0;
        $success = 0;
        $failed = 0;

        Media::query()
            ->where('disk', '!=', $targetDisk)
            ->chunkById(50, function ($mediaItems) use ($targetDisk, $dryRun, $deleteSource, &$count, &$success, &$failed) {
                foreach ($mediaItems as $media) {
                    $count++;
                    $sourceDisk = $media->disk;
                    $relativePath = $media->getPathRelativeToRoot();

                    if (! Storage::disk($sourceDisk)->exists($relativePath)) {
                        $this->warn("Skipping Media ID #{$media->id}: File not found on local disk [{$sourceDisk}] at [{$relativePath}]");
                        $failed++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->info("[DRY RUN] Would copy Media ID #{$media->id} ({$media->file_name}) to [{$targetDisk}]");
                        $success++;

                        continue;
                    }

                    try {
                        $stream = Storage::disk($sourceDisk)->readStream($relativePath);

                        if ($stream === false) {
                            throw new \RuntimeException('Unable to read source file.');
                        }

                        Storage::disk($targetDisk)->writeStream($relativePath, $stream);

                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        // Verify upload on target disk
                        if (! Storage::disk($targetDisk)->exists($relativePath)) {
                            throw new \RuntimeException("File verification failed on target disk [{$targetDisk}] after putStream");
                        }

                        $media->disk = $targetDisk;
                        $media->save();

                        if ($deleteSource) {
                            Storage::disk($sourceDisk)->delete($relativePath);
                        }

                        $this->info("Migrated Media ID #{$media->id} ({$media->file_name}) to [{$targetDisk}]");
                        $success++;
                    } catch (\Throwable $e) {
                        $this->error("Error migrating Media ID #{$media->id}: ".$e->getMessage());
                        Log::error("Media cloud migration failed for ID {$media->id}: ".$e->getMessage());
                        $failed++;
                    }
                }
            });

        $this->info("Migration completed. Total inspected: {$count}, Success: {$success}, Failed/Skipped: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
