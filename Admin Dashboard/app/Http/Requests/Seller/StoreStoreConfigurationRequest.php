<?php

namespace App\Http\Requests\Seller;

use App\Enums\SettingTypeEnum;
use App\Services\SettingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStoreConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('metadata.operating_hours') && is_array($this->input('metadata.operating_hours'))) {
            $metadata = $this->input('metadata', []);
            $operatingHours = $metadata['operating_hours'] ?? [];
            foreach ($operatingHours as $day => $config) {
                if (! empty($config['is_open']) && filter_var($config['is_open'], FILTER_VALIDATE_BOOLEAN)) {
                    $operatingHours[$day]['open_time'] = $config['open_time'] ?? '09:00';
                    $operatingHours[$day]['close_time'] = $config['close_time'] ?? '21:00';
                }
            }
            $metadata['operating_hours'] = $operatingHours;
            $this->merge(['metadata' => $metadata]);

            if (empty($this->input('timing'))) {
                $op = $this->input('metadata.operating_hours');
                $openDays = [];
                foreach ($op as $day => $cfg) {
                    if (! empty($cfg['is_open']) && filter_var($cfg['is_open'], FILTER_VALIDATE_BOOLEAN)) {
                        $openDays[] = ucfirst($day);
                    }
                }
                if (empty($openDays)) {
                    $timingStr = 'Closed';
                } elseif (count($openDays) === 7) {
                    $first = reset($op);
                    $timingStr = 'Mon-Sun '.($first['open_time'] ?? '09:00').' - '.($first['close_time'] ?? '21:00');
                } else {
                    $first = reset($op);
                    $timingStr = implode(', ', $openDays).' '.($first['open_time'] ?? '09:00').' - '.($first['close_time'] ?? '21:00');
                }
                $this->merge(['timing' => $timingStr]);
            }
        }

        if ($this->has('is_scheduled_delivery_enabled')) {
            $this->merge(['is_scheduled_delivery_enabled' => $this->boolean('is_scheduled_delivery_enabled')]);
        }

        if ($this->has('is_temporarily_closed')) {
            $this->merge(['is_temporarily_closed' => $this->boolean('is_temporarily_closed')]);
        }

        if ($this->has('allows_pickup')) {
            $allowsPickup = $this->boolean('allows_pickup');
            $this->merge(['allows_pickup' => $allowsPickup]);
            if (! $allowsPickup) {
                $this->merge(['is_pickup_cod_enabled' => false]);
            } else {
                if ($this->has('is_pickup_cod_enabled')) {
                    $this->merge(['is_pickup_cod_enabled' => $this->boolean('is_pickup_cod_enabled')]);
                }
            }
        } else {
            if ($this->has('is_pickup_cod_enabled')) {
                $this->merge(['is_pickup_cod_enabled' => $this->boolean('is_pickup_cod_enabled')]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $settingService = app(SettingService::class);
        $settingsResource = $settingService->getSettingByVariable(SettingTypeEnum::SCHEDULED_DELIVERY());
        $settings = $settingsResource?->toArray(request())['value'] ?? [];
        $maxLimit = (int) ($settings['maxAdvanceBookingDaysLimit'] ?? 7);

        return [
            'timing' => 'nullable|string',
            'status' => 'required|string|in:online,offline',
            'max_delivery_distance' => 'nullable|numeric',
            'domestic_shipping_charges' => 'nullable|numeric',
            'international_shipping_charges' => 'nullable|numeric',
            'description' => 'nullable|string',
            'about_us' => 'nullable|string',
            'promotional_text' => 'nullable|string',
            'return_replacement_policy' => 'required|string',
            'refund_policy' => 'required|string',
            'terms_and_conditions' => 'required|string',
            'delivery_policy' => 'required|string',
            'metadata' => 'nullable|array',
            'metadata.operating_hours' => 'nullable|array',
            'metadata.operating_hours.*.is_open' => 'nullable|boolean',
            'metadata.operating_hours.*.open_time' => 'nullable|string',
            'metadata.operating_hours.*.close_time' => 'nullable|string',
            'metadata.operating_hours.*.has_break' => 'nullable|boolean',
            'metadata.operating_hours.*.break_start_time' => 'nullable|string',
            'metadata.operating_hours.*.break_end_time' => 'nullable|string',
            'allows_pickup' => ['nullable', 'boolean'],
            'pickup_instructions' => ['nullable', 'string', 'max:1000'],
            'pos_upi_vpa' => ['nullable', 'string', 'max:100'],
            'pos_upi_payee_name' => ['nullable', 'string', 'max:100'],
            'receipt_template' => ['nullable', 'array'],
            'receipt_template.footer_note' => ['nullable', 'string', 'max:500'],
            'pos_payment_config' => ['nullable', 'array'],
            'pos_payment_config.cash' => ['nullable', 'boolean'],
            'pos_payment_config.upi' => ['nullable', 'boolean'],
            'pos_payment_config.online_qr' => ['nullable', 'boolean'],
            'pos_payment_config.split' => ['nullable', 'boolean'],
            'pos_payment_config.custom_methods' => ['nullable', 'array'],
            'pos_payment_config.custom_methods.*.name' => ['required_with:pos_payment_config.custom_methods', 'string', 'max:50'],
            'pos_payment_config.custom_methods.*.instructions' => ['nullable', 'string', 'max:250'],
            'pos_payment_config.custom_methods.*.icon' => ['nullable', 'string', 'max:10'],
            'pos_payment_config.custom_methods.*.enabled' => ['nullable', 'boolean'],
            'is_scheduled_delivery_enabled' => ['nullable', 'boolean'],
            'is_pickup_cod_enabled' => ['nullable', 'boolean'],
            'scheduled_delivery_buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'max_advance_booking_days' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
            'scheduled_delivery_slot_duration' => ['nullable', 'integer', Rule::in([30, 60])],
            'scheduled_delivery_slot_capacity' => ['nullable', 'integer', 'min:1'],
            'is_temporarily_closed' => ['nullable', 'boolean'],
            'temporary_closure_reason' => ['nullable', 'string', 'max:250'],
            'prep_concurrency' => ['nullable', 'integer', 'min:1'],
            'reschedule_deadline_hours' => ['nullable', 'integer', 'min:1'],
            'metadata.seo_title' => 'nullable|string|max:255',
            'metadata.seo_keywords' => 'nullable|array',
            'metadata.seo_keywords.*' => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timing.required' => 'Store timing is required',
            'timing.string' => 'Store timing must be a string',
            'status.required' => 'Store status is required',
            'status.string' => 'Store status must be a string',
            'status.in' => 'Store status must be either online or offline',
            'max_delivery_distance.required' => 'Maximum delivery distance is required',
            'max_delivery_distance.numeric' => 'Maximum delivery distance must be a number',
            'domestic_shipping_charges.required' => 'Domestic shipping charges are required',
            'domestic_shipping_charges.numeric' => 'Domestic shipping charges must be a number',
            'international_shipping_charges.required' => 'International shipping charges are required',
            'international_shipping_charges.numeric' => 'International shipping charges must be a number',
            'return_replacement_policy.required' => 'Return and replacement policy is required',
            'refund_policy.required' => 'Refund policy is required',
            'terms_and_conditions.required' => 'Terms and conditions are required',
            'delivery_policy.required' => 'Delivery policy is required',
            'max_advance_booking_days.max' => 'Maximum advance booking days cannot exceed the platform limit of :max days.',
        ];
    }

    /**
     * Custom validator after-hooks to validate break hours.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $operatingHours = $this->input('metadata.operating_hours', []);
            if (! is_array($operatingHours)) {
                return;
            }
            foreach ($operatingHours as $day => $config) {
                $hasBreak = ! empty($config['has_break']) && filter_var($config['has_break'], FILTER_VALIDATE_BOOLEAN);
                if ($hasBreak) {
                    $start = $config['break_start_time'] ?? null;
                    $end = $config['break_end_time'] ?? null;
                    if (empty($start) || empty($end)) {
                        $validator->errors()->add("metadata.operating_hours.{$day}.break_start_time", 'Break start and end times are required when break is enabled.');

                        continue;
                    }
                    if (strtotime($start) >= strtotime($end)) {
                        $validator->errors()->add("metadata.operating_hours.{$day}.break_end_time", 'Break end time must be after break start time.');
                    }
                }
            }
        });
    }
}
