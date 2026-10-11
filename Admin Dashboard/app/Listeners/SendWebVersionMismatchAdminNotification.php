<?php

namespace App\Listeners;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Events\WebVersionMismatchDetected;
use App\Models\User;
use App\Notifications\WebVersionMismatchNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Listener that handles WebVersionMismatchDetected events and notifies Super Admins.
 * Implements Option B throttling: notifies admins only once per unique version mismatch pair.
 */
class SendWebVersionMismatchAdminNotification implements ShouldQueue
{
    /**
     * Handle the web version mismatch event.
     *
     * @param WebVersionMismatchDetected $event
     * @return void
     */
    public function handle(WebVersionMismatchDetected $event): void
    {
        try {
            // Option B: Ensure admins are notified only once per unique client version & active version combination
            $cacheKey = "web_version_mismatch_notified_{$event->clientVersion}_{$event->latestVersion}";

            if (Cache::has($cacheKey)) {
                return;
            }

            // Lock cache key forever until system updates to a new version or cache is cleared
            Cache::forever($cacheKey, true);

            // Fetch all Super Admin users
            $admins = User::role(DefaultSystemRolesEnum::SUPER_ADMIN(), GuardNameEnum::ADMIN())->get();

            foreach ($admins as $admin) {
                $admin->notify(new WebVersionMismatchNotification(
                    clientVersion: $event->clientVersion,
                    latestVersion: $event->latestVersion,
                    ipAddress: $event->ipAddress,
                    userAgent: $event->userAgent
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('WebVersionMismatch notification failed', [
                'client_version' => $event->clientVersion,
                'latest_version' => $event->latestVersion,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
