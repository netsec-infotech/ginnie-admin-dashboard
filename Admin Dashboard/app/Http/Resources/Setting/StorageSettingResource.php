<?php

namespace App\Http\Resources\Setting;

use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorageSettingResource extends JsonResource
{
    use PanelAware;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'variable' => $this->variable,
            'value' => [
                'storageDisk' => $this->value['storageDisk'] ?? 'local',
                'awsRegion' => $this->value['awsRegion'] ?? '',
                'awsBucket' => $this->value['awsBucket'] ?? '',
                'awsAssetUrl' => $this->value['awsAssetUrl'] ?? '',
                'awsUsePathStyleEndpoint' => ! empty($this->value['awsUsePathStyleEndpoint']),
                'r2Bucket' => $this->value['r2Bucket'] ?? ($this->value['awsBucket'] ?? ''),
                'r2AssetUrl' => $this->value['r2AssetUrl'] ?? ($this->value['awsAssetUrl'] ?? ''),
                'r2Endpoint' => $this->value['r2Endpoint'] ?? ($this->value['awsEndpoint'] ?? ''),
                'azureAccountName' => $this->value['azureAccountName'] ?? '',
                'azureContainer' => $this->value['azureContainer'] ?? '',
                'azureEndpoint' => $this->value['azureEndpoint'] ?? '',
            ],
        ];

        // Only admin panel can access keys
        if ($this->getPanel() === 'admin') {
            $data['value'] = array_merge($data['value'], [
                'awsAccessKeyId' => $this->value['awsAccessKeyId'] ?? '',
                'awsSecretAccessKey' => $this->value['awsSecretAccessKey'] ?? '',
                'r2AccessKeyId' => $this->value['r2AccessKeyId'] ?? ($this->value['awsAccessKeyId'] ?? ''),
                'r2SecretAccessKey' => $this->value['r2SecretAccessKey'] ?? ($this->value['awsSecretAccessKey'] ?? ''),
                'azureAccountKey' => $this->value['azureAccountKey'] ?? '',
            ]);
        }

        return $data;
    }
}
