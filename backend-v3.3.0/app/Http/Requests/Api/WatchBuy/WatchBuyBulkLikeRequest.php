<?php

namespace App\Http\Requests\Api\WatchBuy;

use Illuminate\Foundation\Http\FormRequest;

class WatchBuyBulkLikeRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.reel_id' => ['required', 'integer', 'distinct:strict', 'exists:watch_buy_reels,id'],
            'items.*.liked' => ['required', 'boolean'],
        ];
    }
}
