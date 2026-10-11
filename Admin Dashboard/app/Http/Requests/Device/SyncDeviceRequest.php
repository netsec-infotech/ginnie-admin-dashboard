<?php

namespace App\Http\Requests\Device;

use App\Enums\DeviceTypeEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SyncDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled at the route level (auth/validate.* middleware).
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:255'],
            'device_type' => ['required', new Enum(DeviceTypeEnum::class)],
            'previous_token' => ['nullable', 'string', 'max:255', 'different:fcm_token'],
            'role_type' => [
                Rule::requiredIf(fn (): bool => $this->is('api/*')),
                'nullable',
                new Enum(NotificationRoleTypeEnum::class),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fcm_token' => $this->input('fcm_token', $this->input('new_token')),
            'previous_token' => $this->input('previous_token', $this->input('old_token')),
        ]);
    }
}
