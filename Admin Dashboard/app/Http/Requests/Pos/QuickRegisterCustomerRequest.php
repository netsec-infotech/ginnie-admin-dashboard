<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class QuickRegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'max:10'],
            'mobile'       => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'email'        => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'         => 'customer name',
            'country_code' => 'country code',
            'mobile'       => 'mobile number',
            'email'        => 'email address',
        ];
    }
}
