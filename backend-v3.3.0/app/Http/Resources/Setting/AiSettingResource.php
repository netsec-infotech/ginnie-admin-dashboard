<?php

namespace App\Http\Resources\Setting;

use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiSettingResource extends JsonResource
{
    use PanelAware;

    public function toArray(Request $request): array
    {
        $data = [
            'variable' => $this->variable,
            'value' => [
                'enableAiDescription' => (bool) ($this->value['enableAiDescription'] ?? false),
                'aiProvider' => (string) ($this->value['aiProvider'] ?? 'gemini'),
                'aiModel' => (string) ($this->value['aiModel'] ?? 'gemini-3.8-flash'),
                'aiCustomModel' => (string) ($this->value['aiCustomModel'] ?? ''),
                'aiCustomBaseUrl' => (string) ($this->value['aiCustomBaseUrl'] ?? ''),
                'aiTemperature' => (float) ($this->value['aiTemperature'] ?? 0.7),
            ],
        ];

        // Only admin panel can access API keys
        if ($this->getPanel() === 'admin') {
            $data['value']['geminiApiKey'] = (string) ($this->value['geminiApiKey'] ?? '');
            $data['value']['openaiApiKey'] = (string) ($this->value['openaiApiKey'] ?? '');
            $data['value']['claudeApiKey'] = (string) ($this->value['claudeApiKey'] ?? '');
            $data['value']['deepseekApiKey'] = (string) ($this->value['deepseekApiKey'] ?? '');
            $data['value']['groqApiKey'] = (string) ($this->value['groqApiKey'] ?? '');
            $data['value']['customAiApiKey'] = (string) ($this->value['customAiApiKey'] ?? '');
        }

        return $data;
    }
}
