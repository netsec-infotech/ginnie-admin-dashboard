<?php

use App\Enums\CommandRunStatusEnum;
use App\Enums\CommandRunTriggerEnum;
use App\Models\CommandRunLog;
use App\Models\SellerSubscription;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// Cron monitor: helper to wrap each scheduled command with logging
// ---------------------------------------------------------------------------
$loggedCommand = function (string $command) {
    return Schedule::command($command)
        ->before(function () use ($command) {
            try {
                CommandRunLog::create([
                    'command' => $command,
                    'status' => CommandRunStatusEnum::RUNNING(),
                    'triggered_by' => CommandRunTriggerEnum::SCHEDULE(),
                    'started_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("Cron monitor: could not log start for {$command}", ['error' => $e->getMessage()]);
            }
        })
        ->after(function () use ($command) {
            try {
                $log = CommandRunLog::where('command', $command)
                    ->where('status', CommandRunStatusEnum::RUNNING())
                    ->where('triggered_by', CommandRunTriggerEnum::SCHEDULE())
                    ->latest('started_at')
                    ->first();

                if ($log) {
                    $log->update([
                        'status' => CommandRunStatusEnum::SUCCESS(),
                        'finished_at' => now(),
                        'duration_ms' => $log->started_at
                            ? (int) now()->diffInMilliseconds($log->started_at)
                            : null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Cron monitor: could not log finish for {$command}", ['error' => $e->getMessage()]);
            }
        })
        ->onFailure(function () use ($command) {
            try {
                $log = CommandRunLog::where('command', $command)
                    ->where('status', CommandRunStatusEnum::RUNNING())
                    ->where('triggered_by', CommandRunTriggerEnum::SCHEDULE())
                    ->latest('started_at')
                    ->first();

                if ($log) {
                    $log->update([
                        'status' => CommandRunStatusEnum::FAILED(),
                        'finished_at' => now(),
                        'duration_ms' => $log->started_at
                            ? (int) now()->diffInMilliseconds($log->started_at)
                            : null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Cron monitor: could not log failure for {$command}", ['error' => $e->getMessage()]);
            }
        });
};

// ---------------------------------------------------------------------------
// Scheduled commands (with logging)
// ---------------------------------------------------------------------------

// Process cashback — daily at 2 AM
$loggedCommand('cashback:process')->dailyAt('02:00');

// Settle referral earnings — daily at 3 AM
$loggedCommand('referral:settle')->dailyAt('03:00');

// Expire seller subscriptions — hourly
$loggedCommand('subscription:expire')->hourly();

// Prune ad event dedup rows older than 7 days — daily at 4 AM
$loggedCommand('ads:prune-dedup')->dailyAt('04:00');

// Phase 3C — flag stuck orders so admins can intervene. Every 5 min.
$loggedCommand('orders:check-stuck')->everyFiveMinutes();

// Bulk uploads — chunked import processor. Picks up one runnable job per tick.
$loggedCommand('bulk-uploads:run')->everyMinute()->withoutOverlapping();

// Bulk uploads — daily prune of old terminal job rows + orphan CSV sweep.
$loggedCommand('bulk-uploads:prune')->dailyAt('02:30')->withoutOverlapping();

// Phase 5 — revalidate scheduled orders. Every 10 min.
$loggedCommand('orders:revalidate-scheduled')->everyTenMinutes()->withoutOverlapping();

// Expire customer loyalty points & send push notification reminders — daily at 00:00
$loggedCommand('loyalty:expire-points')->dailyAt('00:00');

// Credit earned customer loyalty points after return window expires — hourly
$loggedCommand('loyalty:process-earned-points')->hourly();
// Return Dispute System — Phase 5 — process return inspection SLA timeouts. Every 5 minutes.
$loggedCommand('returns:process-inspection-sla')->everyFiveMinutes();
// ---------------------------------------------------------------------------
// Closure command: subscription:expire
// ---------------------------------------------------------------------------
Artisan::command('subscription:expire', function () {
    $now = now();
    $expiredCount = 0;
    $affectedSellerIds = [];

    SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE())
        ->whereNotNull('end_date')
        ->where('end_date', '<=', $now)
        ->orderBy('id')
        ->chunkById(500, function ($subs) use (&$expiredCount, &$affectedSellerIds) {
            $ids = $subs->pluck('id')->all();
            $sellerIds = $subs->pluck('seller_id')->unique()->values()->all();

            if (!empty($ids)) {
                SellerSubscription::whereIn('id', $ids)
                    ->update(['status' => SellerSubscriptionStatusEnum::EXPIRED()]);
                $expiredCount += count($ids);
                $affectedSellerIds = array_values(array_unique(array_merge($affectedSellerIds, $sellerIds)));
            }
        });

    foreach ($affectedSellerIds as $sellerId) {
        Cache::forget(SellerSubscription::cacheKeyForCurrent((int) $sellerId));
    }
    Log::info("Expired {$expiredCount} subscription(s) as of {$now->toDateTimeString()}.");
    $this->info("Expired {$expiredCount} subscription(s) as of {$now->toDateTimeString()}.");
})->purpose('Expire subscriptions when their timeline is over (end_date passed)');
