<?php

namespace App\Http\Requests\Api\WatchBuy;

use Illuminate\Foundation\Http\FormRequest;

class WatchBuyStatusFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'cursor' => ['nullable', 'string'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'store_slug' => ['nullable', 'string', 'max:255', 'exists:stores,slug'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}
