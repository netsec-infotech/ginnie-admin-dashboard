<?php

namespace App\Http\Requests\Badge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'badge_id'      => ['nullable', 'integer', Rule::exists('badges', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_ids'   => __('labels.products'),
            'product_ids.*' => __('labels.product'),
            'badge_id'      => __('labels.badge'),
        ];
    }
}
