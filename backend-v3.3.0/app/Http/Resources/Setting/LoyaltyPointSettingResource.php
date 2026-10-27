<?php

namespace App\Http\Resources\Setting;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyPointSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = Setting::getLoyaltyPointSettings();

        return [
            'variable' => 'loyalty_point',
            'value' => [
                'is_enabled' => $settings['is_enabled'],
                'point_rate' => $settings['point_rate'],
                'redemption_value' => $settings['redemption_value'],
                'max_redemption_percentage' => $settings['max_redemption_percentage'],
                'min_order_value_to_redeem' => $settings['min_order_value_to_redeem'],
                'is_expiry_enabled' => $settings['is_expiry_enabled'],
                'expiry_days' => $settings['expiry_days'],
            ],
        ];
    }
}
