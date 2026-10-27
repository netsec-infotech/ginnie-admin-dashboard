<?php

namespace App\Types\Settings;

use App\Enums\AiProviderEnum;
use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;
use Illuminate\Validation\Rules\Enum;

class AiSettingType implements SettingInterface
{
    use SettingTrait;

    public bool $enableAiDescription = false;

    public string $aiProvider = 'gemini';

    public string $aiModel = 'gemini-3.8-flash';

    public string $aiCustomModel = '';

    public string $aiCustomBaseUrl = '';

    public string $geminiApiKey = '';

    public string $openaiApiKey = '';

    public string $claudeApiKey = '';

    public string $deepseekApiKey = '';

    public string $groqApiKey = '';

    public string $customAiApiKey = '';

    public float $aiTemperature = 0.7;

    protected static function getValidationRules(): array
    {
        return [
            'enableAiDescription' => ['nullable', 'boolean'],
            'aiProvider' => ['required', new Enum(AiProviderEnum::class)],
            'aiModel' => ['nullable', 'string', 'max:150'],
            'aiCustomModel' => ['nullable', 'string', 'max:150'],
            'aiCustomBaseUrl' => ['nullable', 'string', 'max:500'],
            'geminiApiKey' => ['nullable', 'string', 'max:500'],
            'openaiApiKey' => ['nullable', 'string', 'max:500'],
            'claudeApiKey' => ['nullable', 'string', 'max:500'],
            'deepseekApiKey' => ['nullable', 'string', 'max:500'],
            'groqApiKey' => ['nullable', 'string', 'max:500'],
            'customAiApiKey' => ['nullable', 'string', 'max:500'],
            'aiTemperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
