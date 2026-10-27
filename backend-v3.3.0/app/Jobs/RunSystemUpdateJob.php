<?php

namespace App\Jobs;

use App\Enums\SystemUpdateStatusEnum;
use App\Models\SystemUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunSystemUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $updateId) {}

    public function handle(): void
    {
        $process = new Process([PHP_BINARY, base_path('app/Support/SystemUpdateProcess.php'), (string) $this->updateId], base_path());
        $process->setTimeout(1750)->mustRun();
    }

    public function failed(?\Throwable $error = null): void
    {
        $update = SystemUpdate::find($this->updateId);
        if (! $update || $update->status !== SystemUpdateStatusEnum::PENDING) {
            return;
        }

        $message = $error?->getMessage() ?? 'Fresh update process failed';
        Log::error('[SystemUpdater] Fresh process failed', ['id' => $this->updateId, 'error' => $message]);
        $update->update([
            'status' => SystemUpdateStatusEnum::FAILED->value,
            'failed_at' => now(),
            'heartbeat_at' => now(),
            'log' => trim(($update->log ?? '')."\n[".now()->toDateTimeString().'] FAILED: '.$message),
        ]);
    }
}
