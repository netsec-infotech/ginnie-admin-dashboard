<?php

namespace App\Broadcasting;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\DeviceTypeEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseChannel
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notifiable, 'fcmTokens')) {
            return;
        }

        $message = $notification->toFirebase($notifiable);
        $roleType = $this->resolveRoleType($notification, $notifiable, $message);
        $tokens = $this->resolveTokens($notifiable, $roleType);
        $nativeOnly = filter_var($message['data']['native_only'] ?? false, FILTER_VALIDATE_BOOL);

        if (empty($tokens['native']) && ($nativeOnly || empty($tokens['web']))) {
            return;
        }

        if ($roleType !== null && ! isset($message['data']['role_type'])) {
            $message['data']['role_type'] = $roleType;
        }

        $result = ['success' => 0, 'failure' => 0];

        if (! empty($tokens['native'])) {
            $nativeResult = $this->firebase->sendBulkNotification(
                tokens: $tokens['native'],
                title: $message['title'],
                body: $message['body'],
                image: $message['image'] ?? null,
                data: $message['data'] ?? [],
                android: $message['android'] ?? null,
                apns: $message['apns'] ?? null
            );
            $result['success'] += (int) ($nativeResult['success'] ?? 0);
            $result['failure'] += (int) ($nativeResult['failure'] ?? 0);
            $result['error'] = $nativeResult['error'] ?? null;
        }

        if (! $nativeOnly && ! empty($tokens['web'])) {
            $webResult = $this->firebase->sendBulkNotification(
                tokens: $tokens['web'],
                title: $message['title'],
                body: $message['body'] ?? '',
                image: $message['image'] ?? null,
                data: $message['data'] ?? [],
                android: $message['android'] ?? null,
                apns: $message['apns'] ?? null
            );
            $result['success'] += (int) ($webResult['success'] ?? 0);
            $result['failure'] += (int) ($webResult['failure'] ?? 0);
            $result['error'] ??= $webResult['error'] ?? null;
        }

        return $result;
    }

    protected function resolveRoleType($notification, $notifiable, array $message): ?string
    {
        if (! empty($message['data']['role_type'])) {
            return (string) $message['data']['role_type'];
        }

        if (method_exists($notification, 'toDatabase')) {
            try {
                $databasePayload = $notification->toDatabase($notifiable);

                if (is_array($databasePayload)) {
                    $fromDb = $databasePayload['role_type']
                        ?? NotificationRoleTypeEnum::fromSentTo($databasePayload['sent_to'] ?? null)?->value;

                    if (! empty($fromDb)) {
                        return (string) $fromDb;
                    }
                }
            } catch (\Throwable) {
                // fall through to notifiable-based inference
            }
        }

        // Final fallback — derive from the notifiable's own role(s). Defence
        // against notifications that forget to declare role_type/sent_to.
        return $this->inferRoleTypeFromNotifiable($notifiable);
    }

    /**
     * Best-effort inference of the recipient's role when the notification
     * itself didn't carry one. Returns null if the notifiable doesn't expose
     * any of the standard hints (hasRole / DeliveryBoy relation).
     */
    protected function inferRoleTypeFromNotifiable($notifiable): ?string
    {
        try {
            if (method_exists($notifiable, 'hasRole')) {
                if ($notifiable->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                    return NotificationRoleTypeEnum::ADMIN();
                }

                if ($notifiable->hasRole(DefaultSystemRolesEnum::SELLER())) {
                    return NotificationRoleTypeEnum::SELLER();
                }
            }

            if (method_exists($notifiable, 'deliveryBoy') && $notifiable->deliveryBoy()->exists()) {
                return NotificationRoleTypeEnum::RIDER();
            }

            if (method_exists($notifiable, 'hasRole')
                && $notifiable->hasRole(DefaultSystemRolesEnum::CUSTOMER())) {
                return NotificationRoleTypeEnum::CUSTOMER();
            }
        } catch (\Throwable $e) {
            Log::debug('FirebaseChannel role inference failed: '.$e->getMessage(), [
                'notifiable_id' => $notifiable->id ?? null,
            ]);
        }

        return null;
    }

    /**
     * @return array{web: array<int, string>, native: array<int, string>}
     */
    protected function resolveTokens($notifiable, ?string $roleType): array
    {
        $query = $notifiable->fcmTokens();

        if ($roleType !== null) {
            // Tokens registered before role_type rollout have NULL — still
            // include them so we don't silently drop legitimate devices.
            $query->where(function ($q) use ($roleType) {
                $q->where('role_type', $roleType)
                    ->orWhereNull('role_type');
            });
        } else {
            Log::debug('FirebaseChannel: dispatching without role_type filter', [
                'notifiable_id' => $notifiable->id ?? null,
            ]);
        }

        $tokens = $query
            ->get(['fcm_token', 'device_type'])
            ->filter(fn ($token) => ! empty($token->fcm_token));

        return [
            'web' => $tokens
                ->filter(fn ($token) => $token->device_type === DeviceTypeEnum::WEB)
                ->pluck('fcm_token')
                ->values()
                ->all(),
            'native' => $tokens
                ->filter(fn ($token) => $token->device_type !== DeviceTypeEnum::WEB)
                ->pluck('fcm_token')
                ->values()
                ->all(),
        ];
    }
}
