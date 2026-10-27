<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvailabilityNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_notify' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                Rule::in([true, false, 1, 0, '1', '0', 'true', 'false']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_notify') && $this->has('is_notifiy')) {
            $this->merge(['is_notify' => $this->input('is_notifiy')]);
        }
    }

    public function notificationEnabled(): bool
    {
        return filter_var($this->validated('is_notify'), FILTER_VALIDATE_BOOLEAN);
    }
}
