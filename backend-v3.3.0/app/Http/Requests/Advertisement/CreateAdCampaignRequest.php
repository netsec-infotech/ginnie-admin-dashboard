<?php

namespace App\Http\Requests\Advertisement;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Models\AdCampaign;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission checked in controller constructor
    }

    public function rules(): array
    {
        $sellerId = auth()->user()?->seller()?->id;

        return [
            'product_id' => [
                'required',
                'integer',
                "exists:products,id,seller_id,{$sellerId},deleted_at,NULL",
                function (string $attribute, mixed $value, \Closure $fail) use ($sellerId) {
                    $hasActive = AdCampaign::where('product_id', $value)
                        ->where('seller_id', $sellerId)
                        ->whereNotIn('status', AdCampaignStatusEnum::terminal())
                        ->exists();

                    if ($hasActive) {
                        $fail(__('labels.ad_campaign_already_active_for_product'));
                    }
                },
            ],
            'budget' => [
                'required',
                'numeric',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists' => __('labels.ad_campaign_product_not_found'),
            'budget.min'        => __('labels.ad_budget_must_be_positive'),
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => __('labels.product'),
            'budget'     => __('labels.ad_budget'),
        ];
    }
}
