<?php

namespace App\Services;

use App\Models\AvailabilityNotification;
use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StoreAvailabilityNotificationService
{
    public function setSubscription(Store $store, User $user, bool $enabled): bool
    {
        if ($enabled && $store->isAcceptingOrders()) {
            throw ValidationException::withMessages([
                'is_notify' => __('labels.store_is_already_online'),
            ]);
        }

        $identity = [
            'user_id' => $user->id,
            'subscribable_type' => 'store',
            'subscribable_id' => $store->id,
            'store_id' => $store->id,
        ];

        if ($enabled) {
            AvailabilityNotification::query()->upsert(
                [[
                    ...$identity,
                    'status' => 'pending',
                    'notified_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['user_id', 'subscribable_type', 'subscribable_id', 'store_id'],
                ['status', 'notified_at', 'updated_at'],
            );

            return true;
        }

        AvailabilityNotification::query()->where($identity)->delete();

        return false;
    }
}
