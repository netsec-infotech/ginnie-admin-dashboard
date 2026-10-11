<?php

namespace App\Http\Requests\Advertisement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminAdCampaignActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission checked in controller constructor
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                Rule::in(['approve', 'reject', 'force_stop']),
            ],
            'reason' => [
                'required_if:action,reject',
                'required_if:action,force_stop',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'action.in'          => __('labels.ad_campaign_invalid_action'),
            'reason.required_if' => __('labels.ad_campaign_reason_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'action' => __('labels.action'),
            'reason' => __('labels.reason'),
        ];
    }
}
