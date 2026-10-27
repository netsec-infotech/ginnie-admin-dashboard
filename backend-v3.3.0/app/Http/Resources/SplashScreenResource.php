<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SplashScreenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'cache_key' => "splash:{$this->id}:{$this->version}",
            'min_display_seconds' => $this->min_display_seconds,
            'background' => [
                'type' => $this->bg_type,
                'color' => $this->bg_color,
                'image_url' => $this->background_image_url,
                'video_url' => $this->background_video_url,
            ],
            'overlay' => [
                'enabled' => (bool) $this->overlay_enabled,
                'type' => $this->overlay_enabled ? $this->overlay_type : null,
                'url' => $this->overlay_enabled ? $this->overlay_url : null,
                'position' => [
                    'x_percent' => (float) $this->overlay_x_percent,
                    'y_percent' => (float) $this->overlay_y_percent,
                    'width_percent' => (float) $this->overlay_width_percent,
                ],
            ],
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
