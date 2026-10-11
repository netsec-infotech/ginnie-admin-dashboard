<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryBoySettingResource extends JsonResource
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
                'termsCondition' => $this->value['termsCondition'] ?? '',
                'privacyPolicy' => $this->value['privacyPolicy'] ?? '',
                'deliveryBoyReferEarnStatus' => $this->value['deliveryBoyReferEarnStatus'] ?? false,
                'deliveryBoyReferEarnBonusReferral' => $this->value['deliveryBoyReferEarnBonusReferral'] ?? 0,
                'deliveryBoyReferEarnBonusReferee' => $this->value['deliveryBoyReferEarnBonusReferee'] ?? 0,
                'cashCollectionLimitStatus' => $this->value['cashCollectionLimitStatus'] ?? false,
                'cashCollectionLimitAmount' => $this->value['cashCollectionLimitAmount'] ?? '0',
                'enableRiderTips' => filter_var($this->value['enableRiderTips'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'minTipAmount' => (float) ($this->value['minTipAmount'] ?? 5.00),
                'preset1' => (float) ($this->value['preset1'] ?? 10.00),
                'preset2' => (float) ($this->value['preset2'] ?? 20.00),
                'preset3' => (float) ($this->value['preset3'] ?? 50.00),
                'maxTipAmount' => (float) ($this->value['maxTipAmount'] ?? 500.00),
            ],
        ];
    }
}
