<?php

namespace App\Http\Resources\Advertisement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'product_title'     => $this->product?->title,
            'product_image'     => $this->product?->getFirstMediaUrl('main_image') ?: null,
            'budget'            => (float) $this->budget,
            'spent'             => (float) $this->spent,
            'remaining_budget'  => (float) max(0, $this->budget - $this->spent),
            'spend_progress'    => $this->spend_progress,
            'cpc_rate_snapshot' => (float) $this->cpc_rate_snapshot,
            'placements'        => $this->placements,
            'status'            => $this->status?->value,
            'status_label'      => $this->status?->label(),
            'rejection_reason'  => $this->rejection_reason,
            'force_stop_reason' => $this->force_stop_reason,
            'force_stopped_at'  => $this->force_stopped_at?->toIso8601String(),
            'total_clicks'      => $this->whenLoaded('stats', fn () => (int) $this->stats->sum('clicks'), 0),
            'total_impressions' => $this->whenLoaded('stats', fn () => (int) $this->stats->sum('impressions'), 0),
            'total_spent_stats' => $this->whenLoaded('stats', fn () => (float) $this->stats->sum('spent'), 0),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
