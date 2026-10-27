<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\SupportTicketMessage;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SupportAdminMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public function __construct(
        public readonly SupportTicketMessage $message,
        public readonly ?int $assignedTo,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->filterChannels([RoleAwareDatabaseChannel::class, FirebaseChannel::class]);
    }

    public function toFirebase(object $notifiable): array
    {
        $message = $this->message->loadMissing(['user', 'ticket.thread']);
        $target = $this->targetUrl($message);

        return [
            'title' => $this->assignedTo
                ? 'New assigned support message'
                : 'New unassigned support message',
            'body' => $this->preview($message),
            'image' => null,
            'data' => [
                'notification_id' => $this->id,
                'type' => NotificationTypeEnum::SUPPORT_MESSAGE(),
                'role_type' => NotificationRoleTypeEnum::ADMIN(),
                'thread_uuid' => $message->ticket->thread->uuid,
                'ticket_slug' => $message->ticket->slug,
                'message_id' => (string) $message->id,
                'assigned_to' => $this->assignedTo ? (string) $this->assignedTo : '',
                'click_action' => $target,
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $payload = $this->toFirebase($notifiable);

        return [
            'title' => $payload['title'],
            'message' => $payload['body'],
            'type' => NotificationTypeEnum::SUPPORT_MESSAGE(),
            'sent_to' => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN(),
            'user_id' => $notifiable->id,
            'metadata' => $payload['data'],
        ];
    }

    private function preview(SupportTicketMessage $message): string
    {
        $sender = $message->user?->name ?: 'Customer';
        $text = str($message->message)->squish()->limit(120)->toString();

        return $text !== '' ? "{$sender}: {$text}" : "{$sender} sent an attachment.";
    }

    private function targetUrl(SupportTicketMessage $message): string
    {
        return route('admin.support.index', [
            'thread' => $message->ticket->thread->uuid,
            'scope' => 'all',
        ]);
    }
}
