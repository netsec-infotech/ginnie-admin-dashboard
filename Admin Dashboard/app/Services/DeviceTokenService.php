<?php

namespace App\Services;

use App\Enums\DeviceTypeEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;

class DeviceTokenService
{
    /**
     * Persist (or replace) the FCM token for a user's device.
     *
     * If $previousToken is supplied and differs from $token, the previous row
     * is removed *only if* it belongs to the same user — this lets the client
     * cleanly rotate a token without leaving an orphan row, while preventing a
     * malicious payload from wiping someone else's device by guessing tokens.
     *
     * The unique constraint on `fcm_token` means the same browser used by a
     * different user gets re-pointed to the new user automatically.
     */
    public function sync(
        User $user,
        string $token,
        DeviceTypeEnum $deviceType,
        ?NotificationRoleTypeEnum $roleType = null,
        ?string $previousToken = null
    ): UserFcmToken {
        if (! empty($previousToken) && $previousToken !== $token) {
            UserFcmToken::where('user_id', $user->id)
                ->where('fcm_token', $previousToken)
                ->delete();
        }

        return UserFcmToken::updateOrCreate(
            ['fcm_token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType->value,
                'role_type' => $roleType?->value,
            ]
        );
    }

    /**
     * Forget a single device token belonging to the given user.
     *
     * Returns the number of rows deleted (0 if the token wasn't tracked).
     */
    public function forget(User $user, string $token): int
    {
        try {
            return UserFcmToken::where('user_id', $user->id)
                ->where('fcm_token', $token)
                ->delete();
        } catch (\Throwable $e) {
            Log::error('Failed to forget FCM token: '.$e->getMessage());

            return 0;
        }
    }
}
