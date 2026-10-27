<?php

namespace App\Http\Requests\Badge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreUpdateBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $badgeId = $this->route('id');
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('badges', 'name')->ignore($badgeId)],
            'label' => ['required', 'string', 'max:50', Rule::unique('badges', 'label')->ignore($badgeId)],
            'bg_color' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'text_color' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'border_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('labels.badge_name'),
            'label' => __('labels.badge_label'),
            'bg_color' => __('labels.badge_bg_color'),
            'text_color' => __('labels.badge_text_color'),
            'border_color' => __('labels.badge_border_color'),
        ];
    }
}
