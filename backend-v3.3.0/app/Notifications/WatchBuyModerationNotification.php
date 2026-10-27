<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WatchBuyModerationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WatchBuyProfile $profile,
        public string $reason,
        public ?WatchBuyReel $reel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->message(),
            'image' => null,
            'data' => $this->metadata(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'type' => NotificationTypeEnum::WATCH_BUY(),
            'sent_to' => 'seller',
            'role_type' => NotificationRoleTypeEnum::SELLER(),
            'user_id' => $notifiable->id ?? null,
            'metadata' => $this->metadata(),
        ];
    }

    private function title(): string
    {
        return $this->reel
            ? __('labels.watch_buy_reel_blocked_notification_title')
            : __('labels.watch_buy_profile_suspended_notification_title');
    }

    private function message(): string
    {
        return $this->reel
            ? __('labels.watch_buy_reel_blocked_notification_message', ['id' => $this->reel->id, 'reason' => $this->reason])
            : __('labels.watch_buy_profile_suspended_notification_message', ['username' => $this->profile->username, 'reason' => $this->reason]);
    }

    private function metadata(): array
    {
        return [
            'type' => NotificationTypeEnum::WATCH_BUY(),
            'role_type' => NotificationRoleTypeEnum::SELLER(),
            'target_type' => $this->reel ? 'reel' : 'profile',
            'profile_id' => $this->profile->id,
            'reel_id' => $this->reel?->id,
            'reason' => $this->reason,
        ];
    }
}
