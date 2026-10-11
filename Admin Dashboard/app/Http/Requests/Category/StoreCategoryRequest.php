<?php

namespace App\Http\Requests\Category;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Enums\CategoryStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function (string $attribute, $value, \Closure $fail): void {
                    $selfId = $this->input('id');
                    if ($value !== null && $value !== '' && $selfId !== null && $selfId !== '' && (int) $value === (int) $selfId) {
                        $fail(__('labels.parent_cannot_be_self'));
                    }
                },
            ],
            'title' => 'required|string|max:255|unique:categories,title',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg',
            'active_icon' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg',
            'background_type' => ['nullable', new Enum(CategoryBackgroundTypeEnum::class)],
            'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color_web' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color_app' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_image_web' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_image_app' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'background_animation' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:10240',
            'background_animation_web' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:10240',
            'background_animation_app' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:10240',
            'font_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'status' => ['nullable', new Enum(CategoryStatusEnum::class)],
            'requires_approval' => 'boolean',
            'commission' => 'nullable|numeric|min:0|max:100',
            'meta_title' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'searchLabels' => 'nullable|array',
            'searchLabels.*' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'metadata.seo_title' => 'nullable|string|max:255',
            'metadata.seo_keywords' => 'nullable|array',
            'metadata.seo_keywords.*' => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'status' => $this->status ?? CategoryStatusEnum::INACTIVE->value,
            'requires_approval' => $this->requires_approval ?? false,
        ]);
    }
}
