<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

class DecideReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via OrderReturnPolicy::decide.
        return true;
    }

    public function rules(): array
    {
        return self::baseRules();
    }

    /**
     * Shared validation reused by non-HTTP callers (admin controller, future
     * bulk-decide endpoint) per CLAUDE.md §3.2.
     */
    public static function baseRules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject'],
            'seller_comment' => ['nullable', 'string', 'max:1000'],
            'admin_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'action' => __('labels.action'),
            'seller_comment' => __('labels.seller_comment'),
            'admin_comment' => __('labels.admin_comment'),
        ];
    }
}
