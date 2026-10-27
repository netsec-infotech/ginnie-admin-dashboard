<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Ai\AiManager;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Seller Products')]
class AiProductDescriptionController extends Controller
{
    protected AiManager $aiManager;

    public function __construct(AiManager $aiManager)
    {
        $this->aiManager = $aiManager;
    }

    /**
     * Generate product copy, rich HTML description, tags, and SEO metadata via AI.
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'category_id' => 'nullable',
                'tone' => 'nullable|string|max:50',
                'length' => 'nullable|string|max:50',
                'keywords' => 'nullable|string|max:1000',
                'instructions' => 'nullable|string|max:1000',
            ]);

            $categoryName = '';
            if (! empty($validated['category_id'])) {
                $category = Category::find($validated['category_id']);
                if ($category) {
                    $categoryName = $category->getTranslation('title', 'en', false);
                }
            }

            $result = $this->aiManager->generateProductDescription([
                'title' => $validated['title'],
                'category_name' => $categoryName,
                'tone' => $validated['tone'] ?? 'persuasive',
                'length' => $validated['length'] ?? 'standard',
                'keywords' => $validated['keywords'] ?? '',
                'instructions' => $validated['instructions'] ?? '',
                'language' => 'English',
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.ai_product_generated_successfully') ?? 'AI product copy generated successfully',
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

    /**
     * Generate full blog post and SEO metadata via AI.
     */
    public function generateBlog(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'topic' => 'required|string|max:500',
                'category_id' => 'nullable',
                'category_ids' => 'nullable|array',
                'category_ids.*' => 'integer|exists:blog_categories,id',
                'tone' => 'nullable|string|in:informative,engaging,professional,storytelling,seo_guide',
                'length' => 'nullable|string|in:short,standard,comprehensive',
                'keywords' => 'nullable|string|max:500',
                'instructions' => 'nullable|string|max:1000',
                'language' => 'nullable|string|max:50',
            ]);

            $categoryNames = [];
            if (! empty($validated['category_ids']) && is_array($validated['category_ids'])) {
                $categoryNames = \App\Models\BlogCategory::whereIn('id', $validated['category_ids'])->pluck('name')->toArray();
            } elseif (! empty($validated['category_id'])) {
                $category = \App\Models\BlogCategory::find($validated['category_id']);
                if ($category) {
                    $categoryNames[] = $category->name;
                }
            }
            $categoryName = implode(', ', $categoryNames);

            $result = $this->aiManager->generateBlogContent([
                'topic' => $validated['topic'],
                'category_name' => $categoryName,
                'tone' => $validated['tone'] ?? 'informative',
                'length' => $validated['length'] ?? 'standard',
                'keywords' => $validated['keywords'] ?? '',
                'instructions' => $validated['instructions'] ?? '',
                'language' => $validated['language'] ?? 'English',
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.ai_blog_generated_successfully') ?? 'AI blog generated successfully',
                data: $result
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: [],
                status: 400
            );
        }
    }

    /**
     * Test connection to a specific AI provider from Admin Settings.
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'provider' => 'required|string|in:gemini,openai,claude,deepseek,groq,custom',
                'api_key' => 'nullable|string|max:500',
                'model' => 'nullable|string|max:150',
                'custom_model' => 'nullable|string|max:150',
                'custom_base_url' => 'nullable|string|max:500',
                'temperature' => 'nullable|numeric|min:0|max:1',
            ]);

            $isCustom = ($validated['provider'] === 'custom' || ($validated['model'] ?? '') === 'custom');
            $model = ($isCustom && ! empty($validated['custom_model']))
                ? $validated['custom_model']
                : ($validated['model'] ?? $validated['custom_model'] ?? null);

            $result = $this->aiManager->testConnection(
                provider: $validated['provider'],
                apiKey: $validated['api_key'] ?? null,
                model: $model,
                baseUrl: $validated['custom_base_url'] ?? null,
                temperature: isset($validated['temperature']) ? (float) $validated['temperature'] : 0.1
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
