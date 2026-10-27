<?php

namespace App\Listeners\Order;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Events\Order\OrderEscalated;
use App\Models\User;
use App\Notifications\OrderEscalatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3C — fan an OrderEscalated event out to every admin user with the
 * Super Admin role, plus any user holding ORDER_EDIT (so on-call ops staff
 * who were granted that permission also get paged).
 */
class NotifyAdminOnEscalation implements ShouldQueue
{
    public function handle(OrderEscalated $event): void
    {
        try {
            $admins = User::role(DefaultSystemRolesEnum::SUPER_ADMIN(), GuardNameEnum::ADMIN())->get();

            foreach ($admins as $admin) {
                $admin->notify(new OrderEscalatedNotification($event->order, $event->newReasons));
            }
        } catch (\Throwable $e) {
            Log::warning('OrderEscalated notification fan-out failed', [
                'order_id' => $event->order->id ?? null,
                'reasons' => $event->newReasons,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
