<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::exists('users', 'referral_code')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Normalise empty-string `friends_code` to null so the downstream service
     * sees a single "empty" sentinel.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('friends_code') && trim((string) $this->input('friends_code')) === '') {
            $this->merge(['friends_code' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'friends_code' => 'referral code',
        ];
    }
}
