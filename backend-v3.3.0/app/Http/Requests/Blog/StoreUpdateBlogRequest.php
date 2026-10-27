<?php

namespace App\Http\Requests\Blog;

use App\Enums\Blog\BlogStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreUpdateBlogRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('POST') && ! $this->route('blog');

        return [
            'title' => ['required', 'string', 'max:255'],
            'author_profile_id' => ['required', 'integer', 'exists:watch_buy_profiles,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:blog_categories,id'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:50'],
            'status' => ['required', new Enum(BlogStatusEnum::class)],
            'is_editors_pick' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'image' => [$isCreate ? 'nullable' : 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'metadata' => ['nullable', 'array'],
            'metadata.seo_title' => ['nullable', 'string', 'max:255'],
            'metadata.seo_keywords' => ['nullable', 'array'],
            'metadata.seo_keywords.*' => ['nullable', 'string', 'max:60'],
            'metadata.seo_description' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category_ids') && is_string($this->input('category_ids'))) {
            $decoded = json_decode($this->input('category_ids'), true);
            $this->merge([
                'category_ids' => is_array($decoded) ? $decoded : array_values(array_filter(array_map('intval', explode(',', $this->input('category_ids'))))),
            ]);
        } elseif ($this->has('category_id') && ! $this->has('category_ids')) {
            $catId = (int) $this->input('category_id');
            $this->merge([
                'category_ids' => $catId > 0 ? [$catId] : [],
            ]);
        }

        if ($this->has('is_editors_pick')) {
            $this->merge([
                'is_editors_pick' => $this->boolean('is_editors_pick'),
            ]);
        }

        if ($this->has('product_ids') && is_string($this->input('product_ids'))) {
            $decoded = json_decode($this->input('product_ids'), true);
            $this->merge([
                'product_ids' => is_array($decoded) ? $decoded : explode(',', $this->input('product_ids')),
            ]);
        }

        if ($this->has('tags') && is_string($this->input('tags'))) {
            $decoded = json_decode($this->input('tags'), true);
            $this->merge([
                'tags' => is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $this->input('tags'))))),
            ]);
        }
    }
}
