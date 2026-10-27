<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeGeneralSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolveUrl = function (?string $path): string {
            if (empty($path)) {
                return '';
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . ltrim(str_replace('storage/', '', $path), '/'));
        };

        $bgImage = $this->value['backgroundImage'] ?? null;
        $bgImageWeb = $this->value['backgroundImageWeb'] ?? $bgImage;
        $bgImageWebMobile = $this->value['backgroundImageWebMobile'] ?? null;
        $bgImageApp = $this->value['backgroundImageApp'] ?? $bgImage;

        $bgAnim = $this->value['backgroundAnimation'] ?? null;
        $bgAnimWeb = $this->value['backgroundAnimationWeb'] ?? $bgAnim;
        $bgAnimApp = $this->value['backgroundAnimationApp'] ?? $bgAnim;

        $bgColor = $this->value['backgroundColor'] ?? '#ffffff';
        $bgColorWeb = $this->value['backgroundColorWeb'] ?? $bgColor;
        $bgColorApp = $this->value['backgroundColorApp'] ?? $bgColor;

        return [
            'variable' => $this->variable,
            'value' => [
                'title' => $this->value['title'] ?? '',
                'searchLabels' => $this->value['searchLabels'] ?? [],
                'backgroundType' => $this->value['backgroundType'] ?? '',
                'backgroundColor' => $bgColor,
                'backgroundColorWeb' => $bgColorWeb,
                'backgroundColorApp' => $bgColorApp,
                'backgroundImage' => $resolveUrl($bgImage),
                'backgroundImageWeb' => $resolveUrl($bgImageWeb),
                'backgroundImageWebMobile' => $resolveUrl($bgImageWebMobile),
                'backgroundImageApp' => $resolveUrl($bgImageApp),
                'backgroundAnimation' => $resolveUrl($bgAnim),
                'backgroundAnimationWeb' => $resolveUrl($bgAnimWeb),
                'backgroundAnimationApp' => $resolveUrl($bgAnimApp),
                'icon' => $resolveUrl($this->value['icon'] ?? null),
                'activeIcon' => $resolveUrl($this->value['activeIcon'] ?? null),
                'fontColor' => $this->value['fontColor'] ?? '#000000',
            ]
        ];
    }
}
