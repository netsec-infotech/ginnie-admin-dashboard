<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Notifications\AbandonedCartNotification;
use App\Services\AbandonedCartSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAbandonedCartNotifications extends Command
{
    protected $signature = 'carts:send-abandoned-notifications';

    protected $description = 'Queue reminders for eligible abandoned customer carts';

    public function handle(AbandonedCartSettings $settings): int
    {
        if (! $settings->isEnabled()) {
            $this->components->info('Abandoned cart notifications are disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($settings->delayMinutes());
        $dispatched = 0;

        Cart::query()
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->whereNull('abandoned_cart_notified_at')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereHas('items', fn ($query) => $query->where('save_for_later', '0'))
            ->orderBy('id')
            ->chunkById(100, function ($carts) use ($cutoff, &$dispatched): void {
                foreach ($carts as $cart) {
                    $claimed = $this->claimCart((int) $cart->id, $cutoff);
                    if ($claimed === null) {
                        continue;
                    }

                    try {
                        $claimed['user']->notify(new AbandonedCartNotification(
                            cartId: $claimed['cart_id'],
                            cartUuid: $claimed['cart_uuid'],
                            activityVersion: $claimed['activity_version'],
                        ));
                        $dispatched++;
                    } catch (Throwable $exception) {
                        Cart::query()
                            ->whereKey($claimed['cart_id'])
                            ->where('activity_version', $claimed['activity_version'])
                            ->update(['abandoned_cart_notified_at' => null]);

                        Log::error('Unable to queue abandoned cart notification.', [
                            'cart_id' => $claimed['cart_id'],
                            'user_id' => $claimed['user']->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->components->info("Queued {$dispatched} abandoned cart notification(s).");

        return self::SUCCESS;
    }

    /**
     * @return array{cart_id: int, cart_uuid: string, activity_version: int, user: \App\Models\User}|null
     */
    private function claimCart(int $cartId, Carbon $cutoff): ?array
    {
        return DB::transaction(function () use ($cartId, $cutoff): ?array {
            $cart = Cart::query()
                ->with('user')
                ->lockForUpdate()
                ->find($cartId);

            if (! $cart
                || ! $cart->user
                || $cart->user->getRawOriginal('status') !== 'active'
                || $cart->last_activity_at === null
                || $cart->last_activity_at->isAfter($cutoff)
                || $cart->abandoned_cart_notified_at !== null
                || ! $cart->items()->where('save_for_later', '0')->exists()) {
                return null;
            }

            $cart->forceFill(['abandoned_cart_notified_at' => now()])->save();

            return [
                'cart_id' => (int) $cart->id,
                'cart_uuid' => (string) $cart->uuid,
                'activity_version' => (int) $cart->activity_version,
                'user' => $cart->user,
            ];
        }, 3);
    }
}
