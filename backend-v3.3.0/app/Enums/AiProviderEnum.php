<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static GEMINI()
 * @method static OPENAI()
 * @method static CLAUDE()
 * @method static DEEPSEEK()
 * @method static GROQ()
 * @method static CUSTOM()
 */
enum AiProviderEnum: string
{
    use InvokableCases, Names, Values;

    case GEMINI = 'gemini';
    case OPENAI = 'openai';
    case CLAUDE = 'claude';
    case DEEPSEEK = 'deepseek';
    case GROQ = 'groq';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::GEMINI => 'Google Gemini',
            self::OPENAI => 'OpenAI (ChatGPT)',
            self::CLAUDE => 'Anthropic Claude',
            self::DEEPSEEK => 'DeepSeek',
            self::GROQ => 'Groq (Ultra-Fast)',
            self::CUSTOM => 'Custom / OpenAI-Compatible (Ollama, OpenRouter, etc.)',
        };
    }
}
