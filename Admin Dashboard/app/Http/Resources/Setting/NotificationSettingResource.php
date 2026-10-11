<?php

namespace App\Http\Resources\Setting;

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
        $data = [
            'variable' => $this->variable,
            'value' => [
                'firebaseProjectId' => $this->resource->value['firebaseProjectId'] ?? '',
                'userRequest' => $this->resource->value['userRequest'] ?? '',
                'vapIdKey' => $this->resource->value['vapIdKey'] ?? '',
                'notification_unread_count' => $user?->id ? $notification->getUnreadCount($user->id) : 0
            ]
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
