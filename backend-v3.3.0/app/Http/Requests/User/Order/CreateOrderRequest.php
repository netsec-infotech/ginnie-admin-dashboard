<?php

namespace App\Http\Requests\User\Order;

use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Product\ProductAttachmentModeEnum;
use App\Models\Cart;
use App\Models\Store;
use App\Services\CartService;
use App\Services\DeliveryScheduleService;
use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class CreateOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (
            $this->input('payment_type') === PaymentTypeEnum::PAYSTACK() &&
            ! $this->filled('transaction_id')
        ) {
            $reference = $this->input('reference')
                ?? $this->input('paystack_reference')
                ?? $this->input('payment_id');

            if (! empty($reference)) {
                $this->merge(['transaction_id' => $reference]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request. Orders
     * require a verified mobile number; email verification is optional for
     * checkout.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Auth middleware already blocks unauthenticated requests; if we
        // somehow got here without a user, defer to the controller's own
        // null-check so the error message stays user-facing.
        if (! $user) {
            return true;
        }

        return $user->isFullyVerified();
    }

    /**
     * Return the project's standard envelope on authorization failure so
     * clients get a clear, structured "which channel is missing" payload.
     */
    protected function failedAuthorization(): void
    {
        $user = $this->user();

        throw new HttpResponseException(
            ApiResponseType::sendJsonResponse(
                false,
                'labels.account_verification_required',
                [
                    'email_verified' => $user?->email_verified_at !== null,
                    'mobile_verified' => $user?->mobile_verified_at !== null,
                ]
            )
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'payment_type' => ['required', Rule::in(PaymentTypeEnum::values())],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'gift_card' => ['nullable', 'string', 'max:50'],
            'address_id' => ['required_unless:delivery_type,pickup', 'nullable', 'numeric', 'exists:addresses,id'],
            'delivery_type' => ['required', new Enum(OrderDeliveryTypeEnum::class)],
            'rush_delivery' => ['boolean', 'nullable'],
            'use_wallet' => ['boolean', 'nullable'],
            'use_loyalty_points' => ['boolean', 'nullable'],
            'order_note' => ['nullable', 'string', 'max:500'],
            'redirect_url' => ['nullable'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_time_slot_id' => ['nullable', 'numeric', 'exists:delivery_time_slots,id'],
            'scheduled_slot_start_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'scheduled_slot_end_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],

            // Attachments structure: attchment[productId][] or attachments[productId][]
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['array'],
            'attachments.*.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx'],
        ];

        if (in_array($this->input('payment_type'), [PaymentTypeEnum::STRIPE(), PaymentTypeEnum::RAZORPAY(), PaymentTypeEnum::PAYSTACK()])) {
            $rules['transaction_id'] = ['required', 'string'];
        }
        if (! empty($this->input('redirect_url'))) {
            $rules['redirect_url'] = ['required', 'url'];
        }
        if ($this->input('payment_type') === PaymentTypeEnum::RAZORPAY()) {
            $rules['razorpay_order_id'] = ['required', 'string'];
            $rules['razorpay_signature'] = ['required', 'string'];
        }

        return $rules;
    }

    /**
     * Configure the validator instance to enforce required attachments for products that require them.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            if (! $user) {
                return; // Authorization handled elsewhere
            }

            // Pickup orders cannot use COD unless the store explicitly allows it
            if ($this->input('delivery_type') === 'pickup' && $this->input('payment_type') === PaymentTypeEnum::COD()) {
                $cart = CartService::getUserCart($user);
                if ($cart && $cart->items->isNotEmpty()) {
                    $storeId = $cart->items->first()->store_id;
                    $store = Store::find($storeId);
                    if (! $store || ! $store->is_pickup_cod_enabled) {
                        $validator->errors()->add('payment_type', __('messages.pickup_no_cod') ?: 'Pickup orders cannot use Cash on Delivery (COD) payment method for this store.');
                    }
                } else {
                    $validator->errors()->add('payment_type', __('messages.pickup_no_cod') ?: 'Pickup orders cannot use Cash on Delivery (COD) payment method.');
                }
            }

            // Load user's cart with products to check requirement
            $cart = CartService::getUserCart($user);
            if (! $cart) {
                return;
            }

            $scheduledDate = $this->input('scheduled_date');
            $deliveryTimeSlotId = $this->input('delivery_time_slot_id');
            $scheduledSlotStartTime = $this->input('scheduled_slot_start_time');
            $scheduledSlotEndTime = $this->input('scheduled_slot_end_time');

            if (! empty($scheduledDate) || ! empty($deliveryTimeSlotId) || ! empty($scheduledSlotStartTime) || ! empty($scheduledSlotEndTime)) {
                if (filter_var($this->input('rush_delivery'), FILTER_VALIDATE_BOOLEAN)) {
                    $validator->errors()->add('rush_delivery', __('messages.scheduled_no_rush') ?: 'Scheduled delivery orders cannot be rush delivery.');
                }

                if (! empty($scheduledSlotStartTime) || ! empty($scheduledSlotEndTime)) {
                    if (empty($scheduledDate)) {
                        $validator->errors()->add('scheduled_date', __('messages.scheduled_date_required') ?: 'Scheduled date is required when selecting a delivery time slot.');
                    }
                    if (empty($scheduledSlotStartTime) || empty($scheduledSlotEndTime)) {
                        $validator->errors()->add('scheduled_slot_start_time', 'Both slot start time and end time are required.');
                    } else {
                        $slotValidation = app(DeliveryScheduleService::class)->validateDynamicSlotForCart(
                            $cart,
                            (string) $scheduledDate,
                            (string) $scheduledSlotStartTime,
                            (string) $scheduledSlotEndTime
                        );
                        if (! $slotValidation['is_valid']) {
                            $validator->errors()->add('scheduled_slot_start_time', $slotValidation['message']);
                        }
                    }
                } elseif (! empty($deliveryTimeSlotId)) {
                    if (empty($scheduledDate)) {
                        $validator->errors()->add('scheduled_date', __('messages.scheduled_date_required') ?: 'Scheduled date is required when selecting a delivery time slot.');
                    } else {
                        $slotValidation = app(DeliveryScheduleService::class)->validateTimeSlotForCart(
                            $cart,
                            (int) $deliveryTimeSlotId,
                            (string) $scheduledDate
                        );
                        if (! $slotValidation['is_valid']) {
                            $validator->errors()->add('delivery_time_slot_id', $slotValidation['message']);
                        }
                    }
                } elseif (! empty($scheduledDate)) {
                    $dateValidation = app(DeliveryScheduleService::class)->validateScheduledDateForCart($cart, (string) $scheduledDate);
                    if (! $dateValidation['is_valid']) {
                        $validator->errors()->add('scheduled_date', $dateValidation['message']);
                    }
                }
            }

            $attachments = $this->file('attachments', []);
            $attachmentsAlt = $this->file('attchment', []); // alternate key

            foreach ($cart->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }
                $requires = (string) $product->is_attachment_required === '1' || $product->is_attachment_required === 1 || $product->is_attachment_required === true;
                if ($requires) {
                    $productId = (string) $product->id;
                    $files = [];
                    if (isset($attachments[$productId])) {
                        $files = (array) $attachments[$productId];
                    } elseif (isset($attachmentsAlt[$productId])) {
                        $files = (array) $attachmentsAlt[$productId];
                    }

                    $attachmentMode = $product->attachment_mode ?? ProductAttachmentModeEnum::REQUIRED();

                    if (empty($files) && $attachmentMode === ProductAttachmentModeEnum::REQUIRED()) {
                        $validator->errors()->add('attachments.'.$productId, __('validation.required', ['attribute' => 'attachment for product '.$product->getTranslation('title', 'en', false)]));
                    }
                }
            }
        });
    }
}
