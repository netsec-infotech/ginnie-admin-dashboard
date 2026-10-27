<?php

namespace App\Http\Resources\Setting;

use App\Services\AbandonedCartSettings;
use App\Services\NotificationService;
use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NotificationSettingResource extends JsonResource
{
    use PanelAware;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();
        $notification = app(NotificationService::class);
        $abandonedCartSettings = app(AbandonedCartSettings::class);
        $data = [
            'variable' => $this->variable,
            'value' => [
                'firebaseProjectId' => $this->resource->value['firebaseProjectId'] ?? '',
                'userRequest' => $this->resource->value['userRequest'] ?? '',
                'vapIdKey' => $this->resource->value['vapIdKey'] ?? '',
                'whatsappEnabled' => filter_var($this->resource->value['whatsappEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'whatsappPhoneNumberId' => $this->resource->value['whatsappPhoneNumberId'] ?? '',
                'whatsappBusinessAccountId' => $this->resource->value['whatsappBusinessAccountId'] ?? '',
                'whatsappAccessToken' => $this->resource->value['whatsappAccessToken'] ?? '',
                'whatsappWebhookVerifyToken' => $this->resource->value['whatsappWebhookVerifyToken'] ?? '',
                'whatsappDefaultLanguage' => $this->resource->value['whatsappDefaultLanguage'] ?? 'en_US',
                'whatsappTestMobile' => $this->resource->value['whatsappTestMobile'] ?? '',
                'notification_unread_count' => $user?->id ? $notification->getUnreadCount($user->id) : 0,
                'enableAbandonedCartNotification' => filter_var(
                    $this->resource->value['enableAbandonedCartNotification'] ?? true,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'abandonedCartNotificationDelayMinutes' => (int) ($this->resource->value['abandonedCartNotificationDelayMinutes'] ?? 60),
                'abandonedCartNotificationChannels' => is_array($this->resource->value['abandonedCartNotificationChannels'] ?? null)
                    ? array_values($this->resource->value['abandonedCartNotificationChannels'])
                    : ['push'],
                'abandonedCartNotificationChannelAvailability' => $abandonedCartSettings->channelAvailability(),
            ],
        ];

        $serviceAccountFile = $this->resource->value['serviceAccountFile'] ?? null;
        $fileExists = false;
        $jsonContent = null;

        if (! empty($serviceAccountFile)) {
            if (Storage::disk('local')->exists($serviceAccountFile)) {
                $fileExists = true;
                $json = Storage::disk('local')->get($serviceAccountFile);
                $jsonContent = $json ? json_decode($json, true) : null;
            } elseif (file_exists(storage_path('app/private/' . $serviceAccountFile))) {
                $fileExists = true;
                $json = @file_get_contents(storage_path('app/private/' . $serviceAccountFile));
                $jsonContent = $json ? json_decode($json, true) : null;
            } elseif (file_exists(storage_path('app/' . $serviceAccountFile))) {
                $fileExists = true;
                $json = @file_get_contents(storage_path('app/' . $serviceAccountFile));
                $jsonContent = $json ? json_decode($json, true) : null;
            }
        }

        $fallbackPath = storage_path('app/private/settings/service-account-file.json');
        if (! $fileExists && file_exists($fallbackPath)) {
            $fileExists = true;
            $json = @file_get_contents($fallbackPath);
            $jsonContent = $json ? json_decode($json, true) : null;
        }

        // Only admin panel can access serviceAccountFile
        if ($this->getPanel() === 'admin') {
            $data['value']['serviceAccountFile'] = $serviceAccountFile ?? '';
            $data['value']['serviceAccountFileExist'] = $fileExists;
            $data['value']['serviceAccountFileData'] = $jsonContent;
        }

        return $data;
    }
}
