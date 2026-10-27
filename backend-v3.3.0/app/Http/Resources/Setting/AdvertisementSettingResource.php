<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvertisementSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'variable' => $this->variable,
            'value' => [
                'featureEnabled' => (bool) ($this->value['featureEnabled'] ?? false),
                'disableBehavior' => $this->value['disableBehavior'] ?? 'keep_running',
                'cpcRate' => isset($this->value['cpcRate']) ? (float) $this->value['cpcRate'] : null,
                'walletMinTopup' => isset($this->value['walletMinTopup']) ? (float) $this->value['walletMinTopup'] : null,
                'impressionMultiplierMin' => (int) ($this->value['impressionMultiplierMin'] ?? 12),
                'impressionMultiplierMax' => (int) ($this->value['impressionMultiplierMax'] ?? 20),
                'adImpressionVisibilityPct' => 50,
                'adImpressionVisibilityMs' => 1000,
            ],
        ];
    }
}
