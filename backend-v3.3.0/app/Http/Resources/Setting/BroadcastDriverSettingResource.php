<?php

namespace App\Http\Resources\Setting;

use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastDriverSettingResource extends JsonResource
{
    use PanelAware;

    public function toArray(Request $request): array
    {
        $data = [
            'variable' => $this->variable,
            'value' => [
                'broadcastDriver' => $this->value['broadcastDriver'] ?? 'pusher',
            ],
        ];

        if ($this->getPanel() !== 'admin') {
            return $data;
        }

        $data['value'] = array_merge($data['value'], [
            'allowedOrigins' => $this->value['allowedOrigins'] ?? 'localhost',
            'reverbAppId' => $this->value['reverbAppId'] ?? '',
            'reverbKey' => $this->value['reverbKey'] ?? '',
            'reverbSecret' => $this->value['reverbSecret'] ?? '',
            'reverbHost' => $this->value['reverbHost'] ?? '',
            'reverbPort' => (int) ($this->value['reverbPort'] ?? 443),
            'reverbScheme' => $this->value['reverbScheme'] ?? 'https',
            'reverbServerHost' => $this->value['reverbServerHost'] ?? '0.0.0.0',
            'reverbServerPort' => (int) ($this->value['reverbServerPort'] ?? 8080),
            'pusherAppId' => $this->value['pusherAppId'] ?? '',
            'pusherKey' => $this->value['pusherKey'] ?? '',
            'pusherSecret' => $this->value['pusherSecret'] ?? '',
            'pusherCluster' => $this->value['pusherCluster'] ?? 'mt1',
        ]);

        return $data;
    }
}