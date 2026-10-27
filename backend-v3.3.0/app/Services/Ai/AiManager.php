<?php

namespace App\Services\Ai;

use App\Contracts\AiDriverInterface;
use App\Enums\AiProviderEnum;
use App\Enums\SettingTypeEnum;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\DeepSeekDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\GroqDriver;
use App\Services\Ai\Drivers\OpenAiCompatibleDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\SettingService;
use Exception;

class AiManager
{
    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Get the active settings merged from AI settings (with fallback to legacy system settings).
     */
    public function getAiSettings(): array
    {
        $aiSettings = $this->settingService->getSettingValues(SettingTypeEnum::AI());
        $systemSettings = $this->settingService->getSettingValues(SettingTypeEnum::SYSTEM());

        // Check if enabled
        $enableAi = ! empty($aiSettings['enableAiDescription']) || ! empty($systemSettings['enableAiDescription']);

        // Provider
        $provider = $aiSettings['aiProvider'] ?? 'gemini';

        // Keys
        $geminiKey = trim((string) ($aiSettings['geminiApiKey'] ?? $systemSettings['geminiApiKey'] ?? ''));
        $openaiKey = trim((string) ($aiSettings['openaiApiKey'] ?? ''));
        $claudeKey = trim((string) ($aiSettings['claudeApiKey'] ?? ''));
        $deepseekKey = trim((string) ($aiSettings['deepseekApiKey'] ?? ''));
        $groqKey = trim((string) ($aiSettings['groqApiKey'] ?? ''));
        $customKey = trim((string) ($aiSettings['customAiApiKey'] ?? ''));

        // Model
        $model = $aiSettings['aiModel'] ?? ($systemSettings['aiModel'] ?? 'gemini-3.8-flash');
        $customModel = trim((string) ($aiSettings['aiCustomModel'] ?? ''));
        $isCustomModel = ($provider === 'custom' || $model === 'custom' || empty($model));
        $effectiveModel = ($isCustomModel && ! empty($customModel)) ? $customModel : ($model ?: $customModel);

        // Custom base url
        $customBaseUrl = trim((string) ($aiSettings['aiCustomBaseUrl'] ?? ''));

        // Temperature
        $temperature = isset($aiSettings['aiTemperature']) ? (float) $aiSettings['aiTemperature'] : 0.7;

        return [
            'enableAiDescription' => $enableAi,
            'aiProvider' => $provider,
            'aiModel' => $effectiveModel,
            'aiSelectedModel' => $model,
            'aiCustomModel' => $customModel,
            'aiCustomBaseUrl' => $customBaseUrl,
            'aiTemperature' => $temperature,
            'geminiApiKey' => $geminiKey,
            'openaiApiKey' => $openaiKey,
            'claudeApiKey' => $claudeKey,
            'deepseekApiKey' => $deepseekKey,
            'groqApiKey' => $groqKey,
            'customAiApiKey' => $customKey,
        ];
    }

    /**
     * Resolve the AI driver for the given provider or active configured provider.
     */
    public function driver(?string $provider = null): AiDriverInterface
    {
        $settings = $this->getAiSettings();
        $targetProvider = $provider ?: $settings['aiProvider'];
        $temperature = $settings['aiTemperature'] ?? 0.7;
        $model = $settings['aiModel'];

        return match ($targetProvider) {
            AiProviderEnum::GEMINI(), 'gemini' => new GeminiDriver(
                apiKey: $settings['geminiApiKey'],
                model: $model ?: 'gemini-3.8-flash',
                temperature: $temperature
            ),
            AiProviderEnum::OPENAI(), 'openai' => new OpenAiDriver(
                apiKey: $settings['openaiApiKey'],
                model: $model ?: 'gpt-4o-mini',
                temperature: $temperature
            ),
            AiProviderEnum::CLAUDE(), 'claude' => new AnthropicDriver(
                apiKey: $settings['claudeApiKey'],
                model: $model ?: 'claude-3-5-haiku-latest',
                temperature: $temperature
            ),
            AiProviderEnum::DEEPSEEK(), 'deepseek' => new DeepSeekDriver(
                apiKey: $settings['deepseekApiKey'],
                model: $model ?: 'deepseek-v4-pro',
                temperature: $temperature,
                baseUrl: $settings['aiCustomBaseUrl'] ?: 'https://api.deepseek.com/v1'
            ),
            AiProviderEnum::GROQ(), 'groq' => new GroqDriver(
                apiKey: $settings['groqApiKey'],
                model: $model ?: 'llama-3.3-70b-versatile',
                temperature: $temperature,
                baseUrl: $settings['aiCustomBaseUrl'] ?: 'https://api.groq.com/openai/v1'
            ),
            AiProviderEnum::CUSTOM(), 'custom' => new OpenAiCompatibleDriver(
                apiKey: $settings['customAiApiKey'],
                model: $model ?: 'custom-model',
                temperature: $temperature,
                baseUrl: $settings['aiCustomBaseUrl'] ?: 'https://openrouter.ai/api/v1'
            ),
            default => throw new Exception("Unsupported AI provider: {$targetProvider}"),
        };
    }

    /**
     * Main entry point for generating product content and SEO metadata.
     *
     * @param array{title: string, category_name?: string, tone?: string, length?: string, keywords?: string, instructions?: string, language?: string} $params
     * @return array{title: string, short_description: string, description: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    public function generateProductDescription(array $params): array
    {
        $settings = $this->getAiSettings();

        if (empty($settings['enableAiDescription'])) {
            throw new Exception(__('labels.ai_description_disabled') ?? 'AI Description Generator is currently disabled.');
        }

        return $this->driver()->generateProductDescription($params);
    }

    /**
     * Main entry point for generating full blog post and SEO metadata.
     *
     * @param array{topic: string, title?: string, category_name?: string, tone?: string, length?: string, keywords?: string, instructions?: string, language?: string} $params
     * @return array{title: string, short_description: string, content: string, tags: array<string>, seo_title: string, seo_keywords: array<string>, seo_description: string}
     */
    public function generateBlogContent(array $params): array
    {
        $settings = $this->getAiSettings();

        if (empty($settings['enableAiDescription'])) {
            throw new Exception(__('labels.ai_description_disabled') ?? 'AI Generator is currently disabled.');
        }

        return $this->driver()->generateBlogContent($params);
    }

    /**
     * Translate structured fields using AI.
     *
     * @param array<string, string> $fields
     * @param string $sourceLocale
     * @param array<int, string> $targetLocales
     * @param array<string, string> $languageNames
     * @param array<string, int> $fieldLimits
     * @return array<string, array<string, string>>
     */
    public function translateStructured(array $fields, string $sourceLocale, array $targetLocales, array $languageNames = [], array $fieldLimits = []): array
    {
        $settings = $this->getAiSettings();

        if (empty($settings['enableAiDescription'])) {
            throw new Exception(__('labels.ai_description_disabled') ?? 'AI Generator is currently disabled.');
        }

        return $this->driver()->translateStructured($fields, $sourceLocale, $targetLocales, $languageNames, $fieldLimits);
    }

    /**
     * Test connection to a specific provider with custom or saved credentials.
     */
    public function testConnection(
        string $provider,
        ?string $apiKey = null,
        ?string $model = null,
        ?string $baseUrl = null,
        ?float $temperature = 0.1
    ): array {
        $settings = $this->getAiSettings();

        $key = ! empty($apiKey) ? trim($apiKey) : match ($provider) {
            'gemini' => $settings['geminiApiKey'],
            'openai' => $settings['openaiApiKey'],
            'claude' => $settings['claudeApiKey'],
            'deepseek' => $settings['deepseekApiKey'],
            'groq' => $settings['groqApiKey'],
            'custom' => $settings['customAiApiKey'],
            default => '',
        };

        $selectedModel = ! empty($model) ? trim($model) : match ($provider) {
            'gemini' => 'gemini-3.8-flash',
            'openai' => 'gpt-4o-mini',
            'claude' => 'claude-3-5-haiku-latest',
            'deepseek' => 'deepseek-v4-pro',
            'groq' => 'llama-3.3-70b-versatile',
            'custom' => !empty($settings['aiCustomModel']) ? $settings['aiCustomModel'] : 'meta-llama/llama-3.3-70b-instruct',
            default => '',
        };

        $selectedBaseUrl = ! empty($baseUrl) ? trim($baseUrl) : ($settings['aiCustomBaseUrl'] ?? null);

        $testDriver = match ($provider) {
            'gemini' => new GeminiDriver($key, $selectedModel, $temperature ?: 0.1),
            'openai' => new OpenAiDriver($key, $selectedModel, $temperature ?: 0.1, $selectedBaseUrl),
            'claude' => new AnthropicDriver($key, $selectedModel, $temperature ?: 0.1),
            'deepseek' => new DeepSeekDriver($key, $selectedModel, $temperature ?: 0.1, $selectedBaseUrl),
            'groq' => new GroqDriver($key, $selectedModel, $temperature ?: 0.1, $selectedBaseUrl),
            'custom' => new OpenAiCompatibleDriver($key, $selectedModel, $temperature ?: 0.1, $selectedBaseUrl),
            default => throw new Exception("Unknown AI provider: {$provider}"),
        };

        return $testDriver->testConnection();
    }

    /**
     * Get a catalog of all supported providers with their metadata and curated models for UI selectors.
     *
     * @return array<string, array{label: string, models: array<string, string>, default_model: string, placeholder: string, requires_base_url: bool, base_url_default: string}>
     */
    public function getProviderCatalog(): array
    {
        return [
            'gemini' => [
                'label' => 'Google Gemini',
                'models' => (new GeminiDriver(''))->getCuratedModels(),
                'default_model' => 'gemini-3.8-flash',
                'placeholder' => 'Enter Google Gemini API Key (AI Studio)',
                'requires_base_url' => false,
                'base_url_default' => '',
            ],
            'openai' => [
                'label' => 'OpenAI (ChatGPT)',
                'models' => (new OpenAiDriver(''))->getCuratedModels(),
                'default_model' => 'gpt-4o-mini',
                'placeholder' => 'Enter OpenAI API Key (sk-...)',
                'requires_base_url' => false,
                'base_url_default' => '',
            ],
            'claude' => [
                'label' => 'Anthropic Claude',
                'models' => (new AnthropicDriver(''))->getCuratedModels(),
                'default_model' => 'claude-3-5-haiku-latest',
                'placeholder' => 'Enter Anthropic API Key (sk-ant-...)',
                'requires_base_url' => false,
                'base_url_default' => '',
            ],
            'deepseek' => [
                'label' => 'DeepSeek',
                'models' => (new DeepSeekDriver(''))->getCuratedModels(),
                'default_model' => 'deepseek-v4-pro',
                'placeholder' => 'Enter DeepSeek API Key (sk-...)',
                'requires_base_url' => false,
                'base_url_default' => 'https://api.deepseek.com/v1',
            ],
            'groq' => [
                'label' => 'Groq (Ultra-Fast Inference)',
                'models' => (new GroqDriver(''))->getCuratedModels(),
                'default_model' => 'llama-3.3-70b-versatile',
                'placeholder' => 'Enter Groq API Key (gsk_...)',
                'requires_base_url' => false,
                'base_url_default' => 'https://api.groq.com/openai/v1',
            ],
            'custom' => [
                'label' => 'Custom / OpenAI-Compatible (Ollama, OpenRouter, etc.)',
                'models' => (new OpenAiCompatibleDriver(''))->getCuratedModels(),
                'default_model' => '',
                'placeholder' => 'Enter API Key (Optional for Local Ollama)',
                'requires_base_url' => true,
                'base_url_default' => 'https://openrouter.ai/api/v1',
            ],
        ];
    }
}
