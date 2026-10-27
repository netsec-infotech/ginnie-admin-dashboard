<?php

namespace App\Http\Requests\Api\WatchBuy;

use Illuminate\Foundation\Http\FormRequest;

class WatchBuyReelFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'cursor' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'store_slug' => ['nullable', 'string', 'max:255', 'exists:stores,slug'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}
