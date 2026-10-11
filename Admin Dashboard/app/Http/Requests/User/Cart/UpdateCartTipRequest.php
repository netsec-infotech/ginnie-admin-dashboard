<?php

namespace App\Http\Requests\User\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartTipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cart_id' => ['nullable', 'integer', 'exists:carts,id'],
            'tip_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function getTipAmount(): float
    {
        return (float) $this->input('tip_amount', 0);
    }
}
