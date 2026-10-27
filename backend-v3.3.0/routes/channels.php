<?php

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Support permission check: super admin or users with support.view permission
$canViewSupport = static fn (User $user): bool => $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())
    || $user->hasPermissionTo(AdminPermissionEnum::SUPPORT_VIEW());

// Private channel for conversation messages, typing state and session status
Broadcast::channel('support.thread.{uuid}', function (User $user, string $uuid) use ($canViewSupport): bool {
    $thread = SupportThread::where('uuid', $uuid)->first();

    return $thread !== null && ($thread->user_id === $user->id || $canViewSupport($user));
});

// Presence channel for real-time online/offline indicator in chat
Broadcast::channel('support.presence.thread.{uuid}', function (User $user, string $uuid) use ($canViewSupport): array|false {
    $thread = SupportThread::where('uuid', $uuid)->first();
    if ($thread === null || ($thread->user_id !== $user->id && ! $canViewSupport($user))) {
        return false;
    }

    return [
        'id' => (string) $user->id,
        'role' => $thread->user_id === $user->id ? 'customer' : 'admin',
    ];
});

// Shared admin channel for live inbox counters and unread indicators
Broadcast::channel('support.admin.inbox', fn (User $user): bool => $canViewSupport($user));

// User-specific private channel
Broadcast::channel('support.user.{userId}', fn (User $user, int $userId): bool => $user->id === $userId);
