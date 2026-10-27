<?php

namespace App\Http\Resources\Setting;

use App\Services\CatalogLanguageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerSettingResource extends JsonResource
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
                'return_seller_inspection_sla_hours' => $this->value['return_seller_inspection_sla_hours'] ?? 48,
                'regularOrderAcceptanceTimeoutEnabled' => filter_var($this->value['regularOrderAcceptanceTimeoutEnabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'regularOrderAcceptanceTimeoutMinutes' => (int) ($this->value['regularOrderAcceptanceTimeoutMinutes'] ?? 10),
                'catalogLanguages' => app(CatalogLanguageService::class)->toArray(),
            ]
        ];
    }
}
