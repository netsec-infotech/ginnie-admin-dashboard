<?php

namespace App\Http\Requests\Api\WatchBuy;

use Illuminate\Foundation\Http\FormRequest;

class WatchBuyBulkSeenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_ids' => ['required', 'array', 'min:1', 'max:100'],
            'status_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
