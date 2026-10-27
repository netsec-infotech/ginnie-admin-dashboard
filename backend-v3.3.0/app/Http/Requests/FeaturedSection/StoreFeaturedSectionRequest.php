<?php

namespace App\Http\Requests\FeaturedSection;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\FeaturedSection\FeaturedSectionStyleEnum;
use App\Enums\FeaturedSection\FeaturedSectionTypeEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
// use Illuminate\Validation\Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreFeaturedSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string|max:1000',
            'style' => ['required', new Enum(FeaturedSectionStyleEnum::class)],
            'section_type' => ['required', new Enum(FeaturedSectionTypeEnum::class)],
            'status' => ['nullable', new Enum(ActiveInactiveStatusEnum::class)],
            'scope_type' => ['required', new Enum(HomePageScopeEnum::class)],
            'scope_id' => [
                'required_if:scope_type,'.HomePageScopeEnum::CATEGORY(),
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->whereNull('parent_id');
                }),
            ],
            'background_type' => 'nullable|in:image,color',
            'background_color' => 'nullable|string|max:255',
            'text_color' => 'nullable|string|max:255',
            'desktop_4k_background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'desktop_fdh_background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'tablet_background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'mobile_background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'categories' => 'required_unless:section_type,'.FeaturedSectionTypeEnum::CUSTOM_PRODUCTS().'|array',
            'categories.*' => 'exists:categories,id',
            'products' => 'required_if:section_type,'.FeaturedSectionTypeEnum::CUSTOM_PRODUCTS().'|array|min:1',
            'products.*' => [
                'integer',
                Rule::exists('products', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (
                        $this->input('section_type') !== FeaturedSectionTypeEnum::CUSTOM_PRODUCTS()
                        || $this->input('scope_type') !== HomePageScopeEnum::CATEGORY()
                        || empty($this->input('scope_id'))
                    ) {
                        return;
                    }

                    $belongsToScopedCategory = Product::query()
                        ->whereKey($value)
                        ->whereIn('category_id', Category::descendantAndSelfIds((int) $this->input('scope_id')))
                        ->exists();

                    if (! $belongsToScopedCategory) {
                        $fail(__('validation.exists', ['attribute' => __('labels.product')]));
                    }
                },
            ],
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
            'title' => __('labels.title'),
            'slug' => __('labels.slug'),
            'short_description' => __('labels.short_description'),
            'style' => __('labels.style'),
            'section_type' => __('labels.section_type'),
            'sort_order' => __('labels.sort_order'),
            'is_active' => __('labels.status'),
            'scope_type' => __('labels.scope_type'),
            'scope_id' => __('labels.scope_category'),
            'background_type' => __('labels.background_type'),
            'background_color' => __('labels.background_color'),
            'categories' => __('labels.categories'),
            'categories.*' => __('labels.category'),
            'products' => __('labels.products'),
            'products.*' => __('labels.product'),
            'all_zones' => __('labels.available_in_all_zones'),
            'zone_ids' => __('labels.zones'),
            'zone_ids.*' => __('labels.zone'),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('validation.featured_section_title_required'),
            'slug.required' => __('validation.featured_section_slug_required'),
            'slug.unique' => __('validation.featured_section_slug_unique'),
            'section_type.required' => __('validation.featured_section_type_required'),
            'categories.required_unless' => __('The Categories field is required unless Section Type is Custom Products.'),
            'products.required_if' => __('The Products field is required when Section Type is Custom Products.'),
            'categories.*.exists' => __('validation.featured_section_categories_exists'),
            'products.required' => __('validation.required', ['attribute' => __('labels.products')]),
            'products.*.exists' => __('validation.exists', ['attribute' => __('labels.product')]),
        ];
    }
}
