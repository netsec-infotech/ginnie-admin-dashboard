<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\GeminiService;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Group;

#[Group('Seller Products')]
class AiProductDescriptionController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Generate product description via AI
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'category_id' => 'nullable',
                'tone' => 'nullable|string|max:50',
                'keywords' => 'nullable|string|max:500',
            ]);

            $categoryName = '';
            if (! empty($validated['category_id'])) {
                $category = Category::find($validated['category_id']);
                if ($category) {
                    $categoryName = $category->name ?? $category->title ?? '';
                }
            }

            $result = $this->geminiService->generateProductDescription([
                'title' => $validated['title'],
                'category_name' => $categoryName,
                'tone' => $validated['tone'] ?? 'professional',
                'keywords' => $validated['keywords'] ?? '',
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'AI description generated successfully',
                data: $result
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: [],
                status: 400
            );
        }
    }
}
