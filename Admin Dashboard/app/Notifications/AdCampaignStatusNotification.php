<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\AdCampaign;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdCampaignStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AdCampaign $campaign,
        public string $action,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [RoleAwareDatabaseChannel::class, FirebaseChannel::class];
    }

    public function toFirebase($notifiable): array
    {
        $productTitle = $this->campaign->product->title ?? '-';

        return [
            'title' => $this->getTitle(),
            'body' => $this->getBody($productTitle),
            'image' => null,
            'data' => [
                'campaign_id' => $this->campaign->id,
                'product_id' => $this->campaign->product_id,
                'action' => $this->action,
                'type' => NotificationTypeEnum::AD_CAMPAIGN(),
                'role_type' => NotificationRoleTypeEnum::SELLER(),
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $productTitle = $this->campaign->product->title ?? '-';

        return [
            'title' => $this->getTitle(),
            'message' => $this->getBody($productTitle),
            'type' => NotificationTypeEnum::AD_CAMPAIGN(),
            'sent_to' => 'seller',
            'role_type' => NotificationRoleTypeEnum::SELLER(),
            'user_id' => $notifiable->id ?? null,
            'metadata' => [
                'campaign_id' => $this->campaign->id,
                'product_id' => $this->campaign->product_id,
                'action' => $this->action,
                'reason' => $this->reason,
            ],
        ];
    }

    private function getTitle(): string
    {
        return match ($this->action) {
            'approve' => __('labels.ad_campaign_approved_title'),
            'reject' => __('labels.ad_campaign_rejected_title'),
            'force_stop' => __('labels.ad_campaign_force_stopped_title'),
            default => __('labels.ad_campaign_status_updated_title'),
        };
    }

    private function getBody(string $productTitle): string
    {
        return match ($this->action) {
            'approve' => __('labels.ad_campaign_approved_body', ['product' => $productTitle]),
            'reject' => __('labels.ad_campaign_rejected_body', ['product' => $productTitle, 'reason' => $this->reason ?? '-']),
            'force_stop' => __('labels.ad_campaign_force_stopped_body', ['product' => $productTitle, 'reason' => $this->reason ?? '-']),
            default => __('labels.ad_campaign_status_updated_body', ['product' => $productTitle]),
        };
    }
}
