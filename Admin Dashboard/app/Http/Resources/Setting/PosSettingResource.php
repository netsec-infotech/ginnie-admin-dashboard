<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSettingResource extends JsonResource
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
                'walkin_user_id' => $this->value['walkin_user_id'] ?? null,
                'default_receipt_footer' => $this->value['default_receipt_footer'] ?? null,
            ]
        ];
    }
}
