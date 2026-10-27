<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to Super Admins when a web version mismatch is detected.
 */
class WebVersionMismatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param string $clientVersion The outdated version reported by the web client.
     * @param string $latestVersion The active server version.
     * @param string|null $ipAddress IP address of the request.
     * @param string|null $userAgent User-Agent string of the requesting web browser.
     */
    public function __construct(
        public string $clientVersion,
        public string $latestVersion,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {}

    /**
     * Get the notification delivery channels.
     *
     * @param object $notifiable
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, 'mail', FirebaseChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param object $notifiable
     * @return MailMessage|null
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        $subject = 'Web App Version Mismatch Detected';
        $greeting = 'Hello ' . ($notifiable->name ?? 'Admin');
        $line = 'An outdated web application version is currently in use by a client.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($line)
            ->line('Reported Web Version: v' . $this->clientVersion)
            ->line('Active System Version: v' . $this->latestVersion)
            ->line('Client IP: ' . ($this->ipAddress ?? 'N/A'))
            ->line('User Agent: ' . ($this->userAgent ?? 'N/A'));
    }

    /**
     * Get the Firebase push notification representation.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => 'Web App Version Mismatch',
            'body' => 'Outdated web version (v' . $this->clientVersion . ') detected. Active version: v' . $this->latestVersion . '.',
            'data' => [
                'type' => NotificationTypeEnum::SYSTEM(),
                'role_type' => NotificationRoleTypeEnum::ADMIN->value,
                'client_version' => $this->clientVersion,
                'latest_version' => $this->latestVersion,
            ],
        ];
    }

    /**
     * Get the database representation of the notification (stored in notifications table).
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Web App Version Mismatch',
            'message' => 'Outdated web version (v' . $this->clientVersion . ') detected. Active system version is v' . $this->latestVersion . '.',
            'type' => NotificationTypeEnum::SYSTEM(),
            'sent_to' => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN->value,
            'user_id' => $notifiable->id ?? null,
            'metadata' => [
                'client_version' => $this->clientVersion,
                'latest_version' => $this->latestVersion,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
            ],
        ];
    }
}
