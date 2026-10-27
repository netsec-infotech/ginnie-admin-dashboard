<?php

namespace App\Services;

use App\Services\Ai\AiManager;
use Exception;

class GeminiService
{
    protected AiManager $aiManager;

    public function __construct(AiManager $aiManager)
    {
        $this->aiManager = $aiManager;
    }

    /**
     * Generate product description using AI Engine (Gemini / configured driver).
     *
     * @param array $params
     * @return array{short_description: string, description: string}
     * @throws Exception
     */
    public function generateProductDescription(array $params): array
    {
        return $this->aiManager->generateProductDescription($params);
    }
}
