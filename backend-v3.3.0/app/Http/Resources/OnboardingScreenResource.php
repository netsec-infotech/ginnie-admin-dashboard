<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingScreenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ?? '',
            'subtitle' => $this->subtitle ?? '',
            'media' => '',
            'media_type' => '',
            'media_mime_type' => '',
            'design' => $this->designPayload(nullDisabledOverlay: true, emptyStrings: true),
        ];
    }
}
