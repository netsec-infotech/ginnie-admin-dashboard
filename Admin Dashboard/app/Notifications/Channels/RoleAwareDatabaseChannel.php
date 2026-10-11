<?php

namespace App\Notifications\Channels;

use App\Enums\Notification\NotificationRoleTypeEnum;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class RoleAwareDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        $payload = parent::buildPayload($notifiable, $notification);
        $roleType = $payload['data']['role_type']
            ?? NotificationRoleTypeEnum::fromSentTo($payload['data']['sent_to'] ?? null)?->value;

        if ($roleType !== null && ! isset($payload['data']['role_type'])) {
            $payload['data']['role_type'] = $roleType;
        }

        $payload['role_type'] = $roleType;

        return $payload;
    }
}
