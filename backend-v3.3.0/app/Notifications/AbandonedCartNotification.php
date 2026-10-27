<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Cart;
use App\Notifications\Channels\CustomSmsChannel;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Services\AbandonedCartSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $cartId,
        public readonly string $cartUuid,
        public readonly int $activityVersion,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $settings = app(AbandonedCartSettings::class);
        $channels = [RoleAwareDatabaseChannel::class];

        if ($settings->hasChannel(AbandonedCartSettings::CHANNEL_PUSH)
            && $settings->channelIsAvailable(AbandonedCartSettings::CHANNEL_PUSH)) {
            $channels[] = FirebaseChannel::class;
        }

        if ($settings->hasChannel(AbandonedCartSettings::CHANNEL_EMAIL)
            && $settings->channelIsAvailable(AbandonedCartSettings::CHANNEL_EMAIL)
            && ! empty($notifiable->email)
            && $notifiable->email_verified_at !== null) {
            $channels[] = 'mail';
        }

        if ($settings->hasChannel(AbandonedCartSettings::CHANNEL_SMS)
            && $settings->channelIsAvailable(AbandonedCartSettings::CHANNEL_SMS)
            && ! empty($notifiable->mobile)
            && $notifiable->mobile_verified_at !== null) {
            $channels[] = CustomSmsChannel::class;
        }

        return $channels;
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $settings = app(AbandonedCartSettings::class);
        if (! $settings->isEnabled()
            || ! $this->customerIsActive($notifiable)
            || ! $this->cartCycleIsCurrent((int) $notifiable->id)) {
            return false;
        }

        return match ($channel) {
            RoleAwareDatabaseChannel::class => true,
            FirebaseChannel::class => $settings->hasChannel(AbandonedCartSettings::CHANNEL_PUSH)
                && $settings->channelIsAvailable(AbandonedCartSettings::CHANNEL_PUSH),
            'mail' => $settings->hasChannel(AbandonedCartSettings::CHANNEL_EMAIL)
                && $settings->channelIsAvailable(AbandonedCartSettings::CHANNEL_EMAIL)
                && ! empty($notifiable->email)
                && $notifiable->email_verified_at !== null,
            CustomSmsChannel::class => $settings->hasChannel(AbandonedCartSettings::CHANNEL_SMS)
                && $settings->channelIsAvailable(AbandonedCartSettings::CHANNEL_SMS)
                && ! empty($notifiable->mobile)
                && $notifiable->mobile_verified_at !== null,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toFirebase(object $notifiable): array
    {
        $payload = $this->payload();

        return [
            'title' => $payload['title'],
            'body' => $payload['message'],
            'image' => null,
            'data' => $payload['metadata'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $payload = $this->payload();

        return [
            'title' => $payload['title'],
            'message' => $payload['message'],
            'type' => NotificationTypeEnum::ABANDONED_CART(),
            'sent_to' => 'customer',
            'role_type' => NotificationRoleTypeEnum::CUSTOMER(),
            'user_id' => $notifiable->id,
            'metadata' => $payload['metadata'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(AbandonedCartSettings::class);
        $mail = (new MailMessage)
            ->subject(__('notifications.abandoned_cart_title'))
            ->greeting(__('notifications.abandoned_cart_email_greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.abandoned_cart_message'));

        if ($url = $settings->customerWebCartUrl()) {
            $mail->action(__('notifications.abandoned_cart_email_action'), $url);
        }

        return $mail;
    }

    public function toSms(object $notifiable): string
    {
        $message = __('notifications.abandoned_cart_sms');
        $url = app(AbandonedCartSettings::class)->customerWebCartUrl();

        return $url ? $message.' '.$url : $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload()['metadata'];
    }

    public function afterSending(object $notifiable, string $channel, mixed $response): void
    {
        if ($channel !== FirebaseChannel::class
            || ! is_array($response)
            || (int) ($response['failure'] ?? 0) === 0
            || (int) ($response['success'] ?? 0) > 0) {
            return;
        }

        throw new RuntimeException((string) ($response['error'] ?? 'Push notification delivery failed.'));
    }

    private function cartCycleIsCurrent(int $userId): bool
    {
        return Cart::query()
            ->whereKey($this->cartId)
            ->where('user_id', $userId)
            ->where('activity_version', $this->activityVersion)
            ->whereNotNull('abandoned_cart_notified_at')
            ->whereHas('items', fn ($query) => $query->where('save_for_later', '0'))
            ->exists();
    }

    private function customerIsActive(object $notifiable): bool
    {
        if (method_exists($notifiable, 'getRawOriginal')) {
            return $notifiable->getRawOriginal('status') === 'active';
        }

        return ($notifiable->status ?? false) === true;
    }

    /**
     * @return array{title: string, message: string, metadata: array<string, mixed>}
     */
    private function payload(): array
    {
        $settings = app(AbandonedCartSettings::class);
        $metadata = [
            'type' => NotificationTypeEnum::ABANDONED_CART(),
            'role_type' => NotificationRoleTypeEnum::CUSTOMER(),
            'cart_id' => (string) $this->cartId,
            'cart_uuid' => $this->cartUuid,
            'activity_version' => (string) $this->activityVersion,
            'click_action' => 'cart',
        ];

        if ($deepLink = $settings->customerAppCartDeepLink()) {
            $metadata['deep_link'] = $deepLink;
        }

        if ($webUrl = $settings->customerWebCartUrl()) {
            $metadata['web_url'] = $webUrl;
        }

        return [
            'title' => __('notifications.abandoned_cart_title'),
            'message' => __('notifications.abandoned_cart_message'),
            'metadata' => $metadata,
        ];
    }
}
