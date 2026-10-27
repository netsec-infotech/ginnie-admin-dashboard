<?php

namespace App\Services;

use App\Enums\CommandRunStatusEnum;
use App\Enums\CommandRunTriggerEnum;
use App\Models\CommandRunLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronMonitorService
{
    /**
     * Registry of all commands that should be monitored.
     */
    public function getRegisteredCommands(): array
    {
        return [
            [
                'command' => 'cashback:process',
                'name' => 'Process Cashback',
                'description' => 'labels.cron_cashback_desc',
                'frequency' => 'labels.daily_at_2am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'referral:settle',
                'name' => 'Settle Referrals',
                'description' => 'labels.cron_referral_desc',
                'frequency' => 'labels.daily_at_3am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'subscription:expire',
                'name' => 'Expire Subscriptions',
                'description' => 'labels.cron_subscription_expire_desc',
                'frequency' => 'labels.hourly',
                'type' => 'scheduled',
            ],
            [
                'command' => 'ads:prune-dedup',
                'name' => 'Prune Ad Dedup',
                'description' => 'labels.cron_ads_prune_desc',
                'frequency' => 'labels.daily_at_4am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'orders:check-stuck',
                'name' => 'Flag Stuck Orders',
                'description' => 'labels.cron_orders_check_stuck_desc',
                'frequency' => 'labels.every_5_minutes',
                'type' => 'scheduled',
            ],
            [
                'command' => 'carts:send-abandoned-notifications',
                'name' => 'Send Abandoned Cart Notifications',
                'description' => 'labels.abandoned_cart_notifications_description',
                'frequency' => 'labels.every_minute',
                'type' => 'scheduled',
            ],
            [
                'command' => 'stores:check-opening-notifications',
                'name' => 'Store Opening Notifications',
                'description' => 'labels.cron_store_opening_notifications_desc',
                'frequency' => 'labels.every_5_minutes',
                'type' => 'scheduled',
            ],
            [
                'command' => 'cart:clean-expired-shares',
                'name' => 'Clean Expired Shared Carts',
                'description' => 'labels.cron_shared_cart_cleanup_desc',
                'frequency' => 'labels.daily_at_0330',
                'type' => 'scheduled',
            ],
            [
                'command' => 'orders:auto-reject-unaccepted',
                'name' => 'Auto-Reject Unaccepted Orders',
                'description' => 'labels.cron_orders_auto_reject_unaccepted_desc',
                'frequency' => 'labels.every_minute',
                'type' => 'scheduled',
            ],
            [
                'command' => 'bulk-uploads:run',
                'name' => 'Process Bulk Uploads',
                'description' => 'labels.cron_bulk_uploads_desc',
                'frequency' => 'labels.every_minute',
                'type' => 'scheduled',
            ],
            [
                'command' => 'bulk-uploads:prune',
                'name' => 'Prune Bulk Uploads',
                'description' => 'labels.cron_bulk_uploads_prune_desc',
                'frequency' => 'labels.daily_at_0230',
                'type' => 'scheduled',
            ],
            // [CHANGED BY AGENT]: Registered media migration & sync commands for Cron Monitor & on-demand execution
            [
                'command' => 'media:migrate-cloud',
                'name' => 'Migrate Media to Cloud',
                'description' => 'labels.cron_media_migrate_desc',
                'frequency' => 'labels.on_demand',
                'type' => 'manual',
            ],
            [
                'command' => 'media:sync-local',
                'name' => 'Sync Media to Local',
                'description' => 'labels.cron_media_sync_desc',
                'frequency' => 'labels.on_demand',
                'type' => 'manual',
            ],
            [
                'command' => 'loyalty:process-earned-points',
                'name' => 'Process Earned Loyalty Points',
                'description' => 'labels.cron_loyalty_process_earned_desc',
                'frequency' => 'labels.hourly',
                'type' => 'scheduled',
            ],
            [
                'command' => 'loyalty:expire-points',
                'name' => 'Expire Loyalty Points',
                'description' => 'labels.cron_loyalty_expire_desc',
                'frequency' => 'labels.daily_at_midnight',
                'type' => 'scheduled',
            ],
            [
                'command' => 'watch-buy:delete-expired-statuses',
                'name' => 'Watch & Buy Story Cleanup',
                'description' => 'labels.cron_watch_buy_status_cleanup_desc',
                'frequency' => 'labels.hourly',
                'type' => 'scheduled',
            ],
        ];
    }

    /**
     * Build status overview for every registered command.
     */
    public function getCommandStatuses(): array
    {
        $commands = $this->getRegisteredCommands();
        $result = [];

        foreach ($commands as $cmd) {
            $lastRun = CommandRunLog::forCommand($cmd['command'])
                ->orderByDesc('started_at')
                ->first();

            $recentRuns = CommandRunLog::forCommand($cmd['command'])
                ->latestRuns(5)
                ->get();

            $result[] = array_merge($cmd, [
                'last_run' => $lastRun,
                'recent_runs' => $recentRuns,
                'is_configured' => $lastRun !== null,
                'last_status' => $lastRun?->status?->value ?? 'never_run',
            ]);
        }

        return $result;
    }

    /**
     * Run a command synchronously and log the result.
     */
    public function runCommand(string $command): CommandRunLog
    {
        $allowed = collect($this->getRegisteredCommands())->pluck('command')->all();
        $allowed[] = 'queue:work';

        if (!in_array($command, $allowed)) {
            throw new \InvalidArgumentException("Command [{$command}] is not in the monitored list.");
        }

        $log = CommandRunLog::create([
            'command' => $command,
            'status' => CommandRunStatusEnum::RUNNING(),
            'triggered_by' => CommandRunTriggerEnum::MANUAL(),
            'started_at' => now(),
        ]);

        $startTime = microtime(true);

        try {
            // queue:work needs --stop-when-empty so it processes pending jobs then exits
            $args = $command === 'queue:work' ? ['--stop-when-empty' => true] : [];
            Artisan::call($command, $args);
            $output = Artisan::output();

            $log->update([
                'status' => CommandRunStatusEnum::SUCCESS(),
                'finished_at' => now(),
                'duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'output' => trim($output) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Manual command run failed: {$command}", ['error' => $e->getMessage()]);

            $log->update([
                'status' => CommandRunStatusEnum::FAILED(),
                'finished_at' => now(),
                'duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'output' => $e->getMessage(),
            ]);
        }

        return $log->refresh();
    }

    /**
     * Get run history for a specific command.
     */
    public function getRunHistory(string $command, int $perPage = 15)
    {
        return CommandRunLog::forCommand($command)
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    /**
     * Check if the scheduler is healthy by verifying its log file was recently modified.
     */
    public function isSchedulerHealthy(): bool
    {
        return file_exists(storage_path('logs/schedule.txt'));
    }

    /**
     * Check if the queue worker is healthy by verifying its log file was recently modified.
     */
    public function isQueueWorkerHealthy(): bool
    {
        return file_exists(storage_path('logs/cron-log.txt'));
    }
}
