<?php

namespace App\Http\Resources\Setting;

use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticationSettingResource extends JsonResource
{
    use PanelAware;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $smsGateway = '';

        if (!empty($this->value['customSms'])) {
            $smsGateway = 'custom';
        } elseif (!empty($this->value['firebase'])) {
            $smsGateway = 'firebase';
        }

        $data = [
            'variable' => $this->variable,
            'value' => [
                'customSms' => $this->value['customSms'] ?? null,
                'customSmsMethod' => $this->value['customSmsMethod'] ?? '',
                'customSmsUrl' => $this->value['customSmsUrl'] ?? '',
                'googleRecaptchaSiteKey' => $this->value['googleRecaptchaSiteKey'] ?? '',
                'firebase' => $this->value['firebase'] ?? null,
                'fireBaseApiKey' => $this->value['fireBaseApiKey'] ?? '',
                'fireBaseAuthDomain' => $this->value['fireBaseAuthDomain'] ?? '',
                'fireBaseDatabaseURL' => $this->value['fireBaseDatabaseURL'] ?? '',
                'fireBaseProjectId' => $this->value['fireBaseProjectId'] ?? '',
                'fireBaseStorageBucket' => $this->value['fireBaseStorageBucket'] ?? '',
                'fireBaseMessagingSenderId' => $this->value['fireBaseMessagingSenderId'] ?? '',
                'fireBaseAppId' => $this->value['fireBaseAppId'] ?? '',
                'fireBaseMeasurementId' => $this->value['fireBaseMeasurementId'] ?? '',
                'googleLogin' => $this->value['googleLogin'] ?? null,
                'smsGateway' => $smsGateway,
            ]
        ];

        // Only admin panel can access sensitive values
        if ($this->getPanel() === 'admin') {
            $data['value'] = array_merge($data['value'], [
                'customSmsMobileFormat' => $this->value['customSmsMobileFormat'] ?? '',
                'customSmsTextFormatData' => $this->value['customSmsTextFormatData'] ?? '',
                'customSmsHeaderKey' => $this->value['customSmsHeaderKey'] ?? [],
                'customSmsHeaderValue' => $this->value['customSmsHeaderValue'] ?? [],
                'customSmsParamsKey' => $this->value['customSmsParamsKey'] ?? [],
                'customSmsParamsValue' => $this->value['customSmsParamsValue'] ?? [],
                'customSmsBodyKey' => $this->value['customSmsBodyKey'] ?? [],
                'customSmsBodyValue' => $this->value['customSmsBodyValue'] ?? [],
            ]);
        }

        return $data;
    }
}
