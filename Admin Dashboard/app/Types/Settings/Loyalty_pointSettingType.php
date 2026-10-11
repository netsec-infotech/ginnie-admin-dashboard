<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class Loyalty_pointSettingType implements SettingInterface
{
    use SettingTrait;

    public bool $is_enabled = false;

    public float $point_rate = 100.0;

    public float $redemption_value = 1.0;

    public float $max_redemption_percentage = 10.0;

    public float $min_order_value_to_redeem = 0.0;

    public bool $is_expiry_enabled = false;

    public int $expiry_days = 30;

    public static function fromArray(array $data): self
    {
        if (isset($data['is_enabled'])) {
            $data['is_enabled'] = filter_var($data['is_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($data['is_expiry_enabled'])) {
            $data['is_expiry_enabled'] = filter_var($data['is_expiry_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($data, static::getValidationRules(), static::getValidationMessages());

        $validator->after(function ($validator) use ($data) {
            $redemptionVal = (float) ($data['redemption_value'] ?? 0);
            $maxCap = (float) ($data['max_redemption_percentage'] ?? 0);
            $minOrder = (float) ($data['min_order_value_to_redeem'] ?? 0);

            if ($redemptionVal > 0 && $maxCap > 0) {
                $requiredMinOrder = $redemptionVal / ($maxCap / 100);
                if ($minOrder < ($requiredMinOrder - 0.0001)) {
                    $formattedRequired = (fmod($requiredMinOrder, 1) === 0.0) ? (int) $requiredMinOrder : round($requiredMinOrder, 2);
                    $formattedRedemption = (fmod($redemptionVal, 1) === 0.0) ? (int) $redemptionVal : $redemptionVal;
                    $formattedCap = (fmod($maxCap, 1) === 0.0) ? (int) $maxCap : $maxCap;

                    $validator->errors()->add(
                        'min_order_value_to_redeem',
                        "Minimum Order Value of {$formattedRequired} is required if you want to keep {$formattedRedemption} Points value for {$formattedCap}%"
                    );
                }
            }
        });

        $validatedData = $validator->validate();

        $instance = new static();
        $instance->is_enabled = (bool) ($validatedData['is_enabled'] ?? false);
        $instance->point_rate = (float) ($validatedData['point_rate'] ?? 100.0);
        $instance->redemption_value = (float) ($validatedData['redemption_value'] ?? 1.0);
        $instance->max_redemption_percentage = (float) ($validatedData['max_redemption_percentage'] ?? 10.0);
        $instance->min_order_value_to_redeem = (float) ($validatedData['min_order_value_to_redeem'] ?? 0.0);
        $instance->expiry_days = (int) ($validatedData['expiry_days'] ?? 0);
        $instance->is_expiry_enabled = $instance->expiry_days > 0;

        return $instance;
    }

    /**
     * Get Laravel validation rules for the properties
     *
     * @return array<string, array<mixed>>
     */
    protected static function getValidationRules(): array
    {
        return [
            'is_enabled' => ['nullable', 'boolean'],
            'point_rate' => ['required', 'numeric', 'min:0.01'],
            'redemption_value' => ['required', 'numeric', 'min:0.01'],
            'max_redemption_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_order_value_to_redeem' => ['required', 'numeric', 'min:0'],
            'expiry_days' => ['required', 'integer', 'min:0'],
        ];
    }
}
