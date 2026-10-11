<?php

namespace App\Http\Requests\Banner;

use App\Enums\Banner\BannerPositionEnum;
use App\Enums\Banner\BannerTypeEnum;
use App\Enums\Banner\BannerVisibilityStatusEnum;
use App\Enums\HomePageScopeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreUpdateBannerRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', new Enum(HomePageScopeEnum::class)],
            'scope_id' => [
                'required_if:scope_type,' . HomePageScopeEnum::CATEGORY(),
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->whereNull('parent_id');
                }),
            ],
            'type' => ['required', new Enum(BannerTypeEnum::class)],
            'title' => ['required', 'string', 'max:255', 'unique:banners,title,' . ($this->route()->id ?? '')],
            'custom_url' => ['required_if:type,==,' . BannerTypeEnum::CUSTOM(), 'nullable', 'string', 'max:255'],
            'product_id' => 'required_if:type,==,' . BannerTypeEnum::PRODUCT() . '|nullable|exists:products,id',
            'category_id' => 'required_if:type,==,' . BannerTypeEnum::CATEGORY() . '|nullable|exists:categories,id',
            'brand_id' => 'required_if:type,==,' . BannerTypeEnum::BRAND() . '|nullable|exists:brands,id',
            'position' => ['required', new Enum(BannerPositionEnum::class)],
            'visibility_status' => ['required', new Enum(BannerVisibilityStatusEnum::class)],
            'display_order' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
            'metadata.seo_title' => 'nullable|string|max:255',
            'metadata.seo_keywords' => 'nullable|array',
            'metadata.seo_keywords.*' => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',
            'banner_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'all_zones' => 'sometimes|boolean',
            'zone_ids' => 'required_if:all_zones,false|array',
            'zone_ids.*' => 'integer|exists:delivery_zones,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Unchecked checkboxes don't appear in the payload — coerce explicitly to false.
        $this->merge([
            'all_zones' => filter_var($this->input('all_zones', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function attributes(): array
    {
        return [
            'all_zones' => __('labels.available_in_all_zones'),
            'zone_ids' => __('labels.zones'),
            'zone_ids.*' => __('labels.zone'),
        ];
    }
}
