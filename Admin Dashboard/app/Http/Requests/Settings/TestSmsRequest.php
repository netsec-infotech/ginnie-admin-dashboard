<?php

namespace App\Http\Requests\Settings;

use App\Enums\SmsMobileFormatEnum;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Inputs for the admin "Send test SMS" action. Mirrors the writable subset of
 * the authentication setting payload, plus the test mobile number — the
 * controller fires the SMS using these values directly so the customer can
 * verify their gateway config without saving.
 */
class TestSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSetting', [Setting::class, 'authentication']) === true;
    }

    public function rules(): array
    {
        return [
            'test_mobile'              => ['required', 'string', 'max:32'],
            'customSmsUrl'             => ['required', 'url'],
            'customSmsMethod'          => ['required', 'in:GET,POST,PUT,PATCH'],
            'customSmsMobileFormat'    => ['nullable', new Enum(SmsMobileFormatEnum::class)],
            'customSmsTextFormatData'  => ['nullable', 'string'],
            'customSmsHeaderKey'       => ['nullable', 'array'],
            'customSmsHeaderKey.*'     => ['nullable', 'string'],
            'customSmsHeaderValue'     => ['nullable', 'array'],
            'customSmsHeaderValue.*'   => ['nullable', 'string'],
            'customSmsParamsKey'       => ['nullable', 'array'],
            'customSmsParamsKey.*'     => ['nullable', 'string'],
            'customSmsParamsValue'     => ['nullable', 'array'],
            'customSmsParamsValue.*'   => ['nullable', 'string'],
            'customSmsBodyKey'         => ['nullable', 'array'],
            'customSmsBodyKey.*'       => ['nullable', 'string'],
            'customSmsBodyValue'       => ['nullable', 'array'],
            'customSmsBodyValue.*'     => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'test_mobile'             => __('labels.test_sms_mobile_label'),
            'customSmsUrl'            => __('labels.custom_sms_url'),
            'customSmsMethod'         => __('labels.custom_sms_method'),
            'customSmsMobileFormat'   => __('labels.custom_sms_mobile_format'),
            'customSmsTextFormatData' => __('labels.custom_sms_text_format_data'),
        ];
    }

    /**
     * Build the transient authentication-setting-like config the service
     * expects. Trims away empty array entries and forces `customSms` true so
     * the dispatcher path runs even if the saved setting is disabled.
     */
    public function toSmsConfig(): array
    {
        return [
            'customSms'               => true,
            'customSmsUrl'            => (string) $this->input('customSmsUrl'),
            'customSmsMethod'         => (string) $this->input('customSmsMethod'),
            'customSmsMobileFormat'   => (string) $this->input('customSmsMobileFormat', ''),
            'customSmsTextFormatData' => (string) $this->input('customSmsTextFormatData', ''),
            'customSmsHeaderKey'      => array_values($this->input('customSmsHeaderKey', [])),
            'customSmsHeaderValue'    => array_values($this->input('customSmsHeaderValue', [])),
            'customSmsParamsKey'      => array_values($this->input('customSmsParamsKey', [])),
            'customSmsParamsValue'    => array_values($this->input('customSmsParamsValue', [])),
            'customSmsBodyKey'        => array_values($this->input('customSmsBodyKey', [])),
            'customSmsBodyValue'      => array_values($this->input('customSmsBodyValue', [])),
        ];
    }
}
