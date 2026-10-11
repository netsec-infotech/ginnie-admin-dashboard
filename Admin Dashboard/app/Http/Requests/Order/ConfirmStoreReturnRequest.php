<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No body validation needed — the action is a confirmation click. Permission
 * + ownership + status guard live in OrderPolicy::confirmReturn. Keeping the
 * FormRequest stub so the project's "every write endpoint has a FormRequest"
 * convention holds.
 */
class ConfirmStoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
