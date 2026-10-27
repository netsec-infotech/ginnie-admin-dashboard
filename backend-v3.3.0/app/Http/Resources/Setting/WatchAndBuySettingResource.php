<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchAndBuySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'variable' => $this->variable,
            'value' => [
                'enabled' => (bool) ($this->value['enabled'] ?? true),
                'title' => (string) ($this->value['title'] ?? __('labels.watch_buy')),
                'maxDurationSeconds' => (int) ($this->value['maxDurationSeconds'] ?? 60),
                'maxUploadMb' => (int) ($this->value['maxUploadMb'] ?? 50),
                'maxProductsPerReel' => (int) ($this->value['maxProductsPerReel'] ?? 10),
                'statusesEnabled' => (bool) ($this->value['statusesEnabled'] ?? true),
                'maxStatusDurationSeconds' => (int) ($this->value['maxStatusDurationSeconds'] ?? 15),
                'maxProductsPerStatus' => (int) ($this->value['maxProductsPerStatus'] ?? 5),
            ],
        ];
    }
}
