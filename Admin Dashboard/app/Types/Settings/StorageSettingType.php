<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class StorageSettingType implements SettingInterface
{
    use SettingTrait;

    public string $storageDisk = 'local';

    public string $awsAccessKeyId = '';

    public string $awsSecretAccessKey = '';

    public string $awsRegion = '';

    public string $awsBucket = '';

    public string $awsAssetUrl = '';

    public bool $awsUsePathStyleEndpoint = false;

    public string $r2AccessKeyId = '';

    public string $r2SecretAccessKey = '';

    public string $r2Bucket = '';

    public string $r2AssetUrl = '';

    public string $r2Endpoint = '';

    public string $azureAccountName = '';

    public string $azureAccountKey = '';

    public string $azureContainer = '';

    public string $azureEndpoint = '';

    protected static function getValidationRules(): array
    {
        return [
            'storageDisk' => 'nullable|string|in:local,s3,r2',
            'awsAccessKeyId' => 'required_if:storageDisk,s3|nullable|string',
            'awsSecretAccessKey' => 'required_if:storageDisk,s3|nullable|string',
            'awsRegion' => 'required_if:storageDisk,s3|nullable|string',
            'awsBucket' => 'required_if:storageDisk,s3|nullable|string',
            'awsAssetUrl' => 'nullable|url',
            'awsUsePathStyleEndpoint' => 'nullable|boolean',
            'r2AccessKeyId' => 'required_if:storageDisk,r2|nullable|string',
            'r2SecretAccessKey' => 'required_if:storageDisk,r2|nullable|string',
            'r2Bucket' => 'required_if:storageDisk,r2|nullable|string',
            'r2AssetUrl' => 'nullable|url',
            'r2Endpoint' => 'required_if:storageDisk,r2|nullable|url',
            'azureAccountName' => 'nullable|string',
            'azureAccountKey' => 'nullable|string',
            'azureContainer' => 'nullable|string',
            'azureEndpoint' => 'nullable|string',
        ];
    }
}
