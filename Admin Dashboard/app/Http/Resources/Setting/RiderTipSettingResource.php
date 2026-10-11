<?php

namespace App\Http\Resources\Setting;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderTipSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tipSettings = Setting::getRiderTipSettings();

        return [
            'variable' => 'rider_tip',
            'value' => [
                'enable_rider_tips' => $tipSettings['enable_rider_tips'],
                'min_tip_amount' => $tipSettings['min_tip_amount'],
                'preset_1' => $tipSettings['preset_1'],
                'preset_2' => $tipSettings['preset_2'],
                'preset_3' => $tipSettings['preset_3'],
                'max_tip_amount' => $tipSettings['max_tip_amount'],
            ],
        ];
    }
}
