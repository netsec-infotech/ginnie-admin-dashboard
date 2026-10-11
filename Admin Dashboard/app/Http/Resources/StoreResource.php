<?php

namespace App\Http\Resources;

use App\Models\Setting;
use App\Services\DeliveryScheduleService;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_count' => $this->product_count ?? 0,
            'description' => $this->description,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'seller_id' => $this->seller_id,
            'tax_name' => $this->tax_name,
            'tax_number' => $this->tax_number,
            'bank_name' => $this->bank_name,
            'bank_branch_code' => $this->bank_branch_code,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'routing_number' => $this->routing_number,
            'bank_account_type' => $this->bank_account_type,
            'currency_code' => $this->currency_code,
            'max_delivery_distance' => $this->max_delivery_distance,
            'order_preparation_time' => $this->order_preparation_time,
            'promotional_text' => $this->promotional_text,
            'about_us' => $this->about_us,
            'return_replacement_policy' => $this->return_replacement_policy,
            'refund_policy' => $this->refund_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'delivery_policy' => $this->delivery_policy,
            'domestic_shipping_charges' => $this->domestic_shipping_charges,
            'international_shipping_charges' => $this->international_shipping_charges,
            'zones' => $this->whenLoaded('zones', function () {
                return DeliveryZoneResource::collection($this->zones);
            }),
            'metadata' => $this->metadataWithSeo(),
            'fulfillment_type' => $this->fulfillment_type,
            'address' => $this->address,
            'city' => $this->city,
            'landmark' => $this->landmark,
            'state' => $this->state,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'zipcode' => $this->zipcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance' => $this->distance ?? 0,
            'timing' => $this->timing ?? null,
            'logo' => $this->store_logo,
            'banner' => $this->store_banner,
            // Conditionally include whether the user's location is within this store's delivery zones
            $this->mergeWhen(isset($this->user_latitude) && isset($this->user_longitude), [
                'same_location' => DeliveryZoneService::canStoreDeliverToLocation(
                    $this->resource,
                    (float) $this->user_latitude,
                    (float) $this->user_longitude
                ),
            ]),
            'address_proof' => $this->address_proof,
            'voided_check' => $this->voided_check,
            'avg_products_rating' => number_format($this->avg_products_rating ?? 0, 2),
            'avg_store_rating' => number_format($this->avg_store_rating ?? 0, 2),
            'total_store_feedback' => $this->total_store_feedback ?? '0',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'verification_status' => $this->verification_status,
            'visibility_status' => $this->visibility_status,
            'is_recommended' => (bool) $this->is_recommended,
            'status' => optional(
                $this
            )->checkStoreStatus() ?? [],
            'allows_pickup' => $this->allows_pickup ?? false,
            'is_pickup_cod_enabled' => $this->is_pickup_cod_enabled ?? false,
            'pickup_instructions' => $this->pickup_instructions ?? '',
            'is_scheduled_delivery_enabled' => (bool) ($this->is_scheduled_delivery_enabled ?? false) && Setting::isScheduledDeliveryEnabled(),
            'scheduled_delivery_buffer_minutes' => (int) ($this->scheduled_delivery_buffer_minutes ?? 60),
            'max_advance_booking_days' => (int) ($this->max_advance_booking_days ?? 7),
            'scheduled_delivery_slot_duration' => (int) ($this->scheduled_delivery_slot_duration ?? 60),
            'scheduled_delivery_slot_capacity' => (int) ($this->scheduled_delivery_slot_capacity ?? 10),
            'is_temporarily_closed' => (bool) ($this->is_temporarily_closed ?? false),
            'temporary_closure_reason' => $this->temporary_closure_reason,
            'prep_concurrency' => (int) ($this->prep_concurrency ?? 2),
            'reschedule_deadline_hours' => (int) ($this->reschedule_deadline_hours ?? 24),
            'scheduled_delivery_info' => app(DeliveryScheduleService::class)->getAvailableDatesForStore($this->resource),
        ];
    }
}
