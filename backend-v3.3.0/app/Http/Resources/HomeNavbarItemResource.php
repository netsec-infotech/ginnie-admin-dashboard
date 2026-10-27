<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeNavbarItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $appearance = $this->resolved_home_appearance;
        $platform = $request->input('platform');
        $selectedAppearance = $platform
            ? ($appearance[$platform === 'web' ? 'desktop' : 'app'] ?? [])
            : null;
        $sortOrder = $platform === 'web'
            ? ($this->web_sort_order ?? $this->sort_order)
            : ($this->app_sort_order ?? $this->sort_order);

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sort_order' => (int) $sortOrder,
            'app_sort_order' => $this->when(
                $request->routeIs('admin.*'),
                (int) ($this->app_sort_order ?? $this->sort_order),
            ),
            'web_sort_order' => $this->when(
                $request->routeIs('admin.*'),
                (int) ($this->web_sort_order ?? $this->sort_order),
            ),
            'is_active' => (bool) $this->is_active,
            'show_on_app' => (bool) $this->show_on_app,
            'show_on_web' => (bool) $this->show_on_web,
            'platforms' => array_values(array_filter([
                $this->show_on_app ? 'app' : null,
                $this->show_on_web ? 'web' : null,
            ])),
            'layout_query' => ['navbar_slug' => $this->slug],
            'home_appearance' => $appearance,
            'appearance' => $this->when($selectedAppearance !== null, $selectedAppearance),
            'search_labels' => array_values($this->search_labels ?? []),
            'icon' => $this->app_icon,
            'active_icon' => $this->app_active_icon,
            'background_image' => $this->app_background_image,
            'app_tablet_background_image' => $this->app_tablet_background_image,
            'desktop_icon' => $this->desktop_icon,
            'desktop_active_icon' => $this->desktop_active_icon,
            'desktop_background_image' => $this->desktop_background_image,
            'desktop_tablet_background_image' => $this->desktop_tablet_background_image,
            'desktop_mobile_background_image' => $this->desktop_mobile_background_image,
        ];
    }
}
