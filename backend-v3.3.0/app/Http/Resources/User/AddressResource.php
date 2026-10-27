<?php

namespace App\Http\Resources\User;

use App\Models\DeliveryZone;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDeliverable = true;
        if (isset($this->is_deliverable)) {
            $isDeliverable = (bool) $this->is_deliverable;
        } elseif ($request->filled('zone_id')) {
            $zone = DeliveryZone::find($request->input('zone_id'));
            if ($zone) {
                $isDeliverable = DeliveryZoneService::isPointInPolygon(
                    $zone,
                    floatval($this->latitude),
                    floatval($this->longitude)
                );
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'landmark' => $this->landmark,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'mobile' => $this->mobile,
            'address_type' => $this->address_type,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_deliverable' => $isDeliverable,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
