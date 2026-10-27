<?php

namespace App\Http\Requests\User\Cart;

use App\Models\Address;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewSharedCartRequest extends FormRequest
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('address_id')) {
                return;
            }

            $user = $this->user('sanctum');
            $ownsAddress = $user && Address::query()
                ->whereKey($this->integer('address_id'))
                ->where('user_id', $user->id)
                ->exists();

            if (! $ownsAddress) {
                $validator->errors()->add('address_id', __('validation.exists', ['attribute' => 'address id']));
            }
        }];
    }
}
