<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class ScheduledDeliverySettingResource
 * Transforms global scheduled delivery settings database values for API/UI.
 */
class ScheduledDeliverySettingResource extends JsonResource
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
                'enableScheduledDelivery' => filter_var($this->value['enableScheduledDelivery'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'maxAdvanceBookingDaysLimit' => (int) ($this->value['maxAdvanceBookingDaysLimit'] ?? 7),
            ],
        ];
    }
}
