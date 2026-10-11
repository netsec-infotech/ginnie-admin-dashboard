<?php

namespace App\Http\Resources\DeliveryBoy;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryBoyOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $phoneCode = Country::select('phonecode')->where('name', $this->shipping_country)->get()->first();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            // COD Doorstep Online Payment metadata
            'is_cod_paid_online' => (bool) $this->is_cod_paid_online,
            'cod_online_gateway' => $this->cod_online_gateway,
            'can_pay_online' => (string) $this->payment_method === (string) PaymentTypeEnum::COD() && (string) $this->payment_status !== (string) PaymentStatusEnum::COMPLETED(),
            'cod_online_payment_url' => $this->when(
                (string) $this->payment_method === (string) PaymentTypeEnum::COD() && (string) $this->payment_status !== (string) PaymentStatusEnum::COMPLETED(),
                fn () => route('cod.public.payment.show', ['token' => $this->uuid])
            ),
            'fulfillment_type' => $this->fulfillment_type,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'delivery_time_slot_id' => $this->delivery_time_slot_id,
            'delivery_boy_id' => $this->delivery_boy_id,
            'delivery_charge' => $this->delivery_charge,
            'rider_tip_amount' => (float) ($this->rider_tip_amount ?? 0),
            'order_note' => $this->order_note,
            'subtotal' => $this->subtotal,
            'total_payable' => $this->total_payable,
            'final_total' => $this->final_total,

            // Shipping info (important for delivery)
            'shipping_name' => $this->shipping_name,
            'shipping_address_1' => $this->shipping_address_1,
            'shipping_address_2' => $this->shipping_address_2,
            'shipping_landmark' => $this->shipping_landmark,
            'shipping_zip' => $this->shipping_zip,
            'shipping_phone' => $this->shipping_phone,
            'shipping_address_type' => $this->shipping_address_type,
            'shipping_latitude' => $this->shipping_latitude,
            'shipping_longitude' => $this->shipping_longitude,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_country' => $this->shipping_country,
            'shipping_phonecode' => $phoneCode['phonecode'] ?? '',

            // Delivery route information
            'delivery_route' => $this->when(isset($this->delivery_route), function () {
                return $this->delivery_route;
            }),
            // Earnings calculation
            'earnings' => is_array($this->delivery_boy_earnings) ? [
                'total' => (int) ceil($this->delivery_boy_earnings['total'] ?? 0),
                'breakdown' => array_map(fn ($val) => (int) ceil($val), $this->delivery_boy_earnings['breakdown'] ?? []),
            ] : $this->delivery_boy_earnings,
            // Relationships
            'items' => DeliveryBoyOrderItemResource::collection($this->whenLoaded('items')),
            'delivery_zone' => $this->when($this->relationLoaded('deliveryZone'), function () {
                return [
                    'id' => $this->deliveryZone->id,
                    'name' => $this->deliveryZone->name,
                ];
            }),
            'assignment' => $this->when($this->relationLoaded('deliveryBoyAssignments') && $this->deliveryBoyAssignments->isNotEmpty(), function () {
                $assignment = $this->deliveryBoyAssignments->first();

                return [
                    'id' => $assignment->id,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
