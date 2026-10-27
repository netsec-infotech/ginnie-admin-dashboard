<?php

namespace App\Http\Requests\Store;

use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class VerifyStoreRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'verification_status' => ['required', new Enum(StoreVerificationStatusEnum::class)],
            'visibility_status' => ['required', new Enum(StoreVisibilityStatusEnum::class)],
            'is_recommended' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $verificationStatus = $this->input('verification_status');
            $visibilityStatus = $this->input('visibility_status');
            $isRecommended = filter_var($this->input('is_recommended'), FILTER_VALIDATE_BOOLEAN);

            if ($verificationStatus !== StoreVerificationStatusEnum::APPROVED()) {
                if ($visibilityStatus === StoreVisibilityStatusEnum::VISIBLE()) {
                    $validator->errors()->add('visibility_status', __('messages.store_must_be_approved_to_be_visible') ?: 'Store must be approved before it can be set to visible.');
                }
                if ($isRecommended) {
                    $validator->errors()->add('is_recommended', __('messages.store_must_be_approved_to_be_recommended') ?: 'Please approve the store before marking it as recommended.');
                }
            }
        });
    }
}
