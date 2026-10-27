<?php

namespace App\Http\Requests\WatchBuy;

use App\Services\WatchBuyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WatchBuyReelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $config = app(WatchBuyService::class)->config();
        $maxUploadKb = (int) $config['effectiveMaxUploadMb'] * 1024;

        return [
            'caption' => ['nullable', 'string', 'max:2000'],
            'source' => [Rule::requiredIf(str_starts_with((string) $this->route()?->getName(), 'admin.')), 'nullable', 'in:seller,seller_behalf,admin_direct'],
            'profile_id' => ['nullable', 'integer', 'exists:watch_buy_profiles,id'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,qt', 'max:'.$maxUploadKb],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'attachment_ids' => ['required', 'array', 'min:1', 'max:'.(int) $config['maxProductsPerReel']],
            'attachment_ids.*' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'primary_attachment_id' => ['required', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'attachment_ids' => __('labels.watch_buy_product_attachments'),
            'primary_attachment_id' => __('labels.watch_buy_primary'),
            'profile_id' => __('labels.watch_buy_seller_profile'),
            'source' => __('labels.watch_buy_creation_mode'),
            'video' => __('labels.watch_buy_video'),
            'cover' => __('labels.watch_buy_cover_image'),
        ];
    }

    public function messages(): array
    {
        return [
            'attachment_ids.required' => __('labels.watch_buy_at_least_one_product_required'),
            'attachment_ids.min' => __('labels.watch_buy_at_least_one_product_required'),
            'primary_attachment_id.required' => __('labels.watch_buy_primary_product_required'),
        ];
    }
}
