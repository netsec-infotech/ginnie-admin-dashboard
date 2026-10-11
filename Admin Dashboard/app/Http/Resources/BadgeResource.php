<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BadgeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'label'        => $this->label,
            'bg_color'     => $this->bg_color,
            'text_color'   => $this->text_color,
            'border_color' => $this->border_color,
        ];
    }
}
