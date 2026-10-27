<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalProductAttribute;
use App\Models\Product;
use App\Services\Ai\AiTranslationService;
use App\Services\LanguageService;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AiTranslationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected AiTranslationService $translationService) {}

    public function translateProduct(Request $request): JsonResponse
    {
        if (is_string($request->input('source_locale'))) {
            $request->merge([
                'source_locale' => app(LanguageService::class)->normalizeCode($request->input('source_locale')),
            ]);
        }
        $request->validate([
            'source_locale' => ['sometimes', 'required', 'string', 'in:en'],
            'module' => ['sometimes', 'required', 'string', 'in:product'],
        ]);
        $request->merge(['module' => 'product']);

        return $this->translate($request);
    }

    /**
     * Translate fields from source locale to target client languages using the active AI provider.
     */
    public function translate(Request $request): JsonResponse
    {
        $module = (string) $request->input('module', 'brand');
        $request->merge(['module' => $module]);
        if ($request->has('target_locales') && is_array($request->input('target_locales'))) {
            $request->merge([
                'target_locales' => collect($request->input('target_locales'))
                    ->map(fn (mixed $locale): mixed => is_string($locale)
                        ? app(LanguageService::class)->normalizeCode($locale)
                        : $locale)
                    ->values()
                    ->all(),
            ]);
        }

        $activeTargetLocales = $this->translationService->activeTargetLocales();
        $descriptionLimit = in_array($module, ['category', 'product'], true) ? 10000 : 255;
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:brand,category,product,attribute'],
            'brand_id' => ['nullable', 'prohibited_unless:module,brand', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'prohibited_unless:module,category', 'integer', 'exists:categories,id'],
            'product_id' => ['nullable', 'prohibited_unless:module,product', 'integer', 'exists:products,id'],
            'fields' => ['required', 'array:title,short_description,description', 'min:1'],
            'fields.title' => ['nullable', 'required_without_all:fields.short_description,fields.description', 'string', 'max:255'],
            'fields.short_description' => ['nullable', 'prohibited_unless:module,product', 'string', 'max:255'],
            'fields.description' => ['nullable', 'required_without_all:fields.title,fields.short_description', 'string', 'max:'.$descriptionLimit],
            'target_locales' => ['nullable', 'prohibited_unless:module,product', 'array', 'min:1'],
            'target_locales.*' => ['required', 'string', 'distinct', Rule::in($activeTargetLocales)],
        ]);

        try {
            if ($module === 'product') {
                if (! empty($validated['product_id'])) {
                    $product = Product::query()->findOrFail($validated['product_id']);
                    $this->authorize('update', $product);
                } else {
                    $this->authorize('create', Product::class);
                }
            } elseif ($module === 'category') {
                if (! empty($validated['category_id'])) {
                    $category = Category::query()->findOrFail($validated['category_id']);
                    $this->authorize('update', $category);
                } else {
                    $this->authorize('create', Category::class);
                }
            } elseif ($module === 'attribute') {
                $this->authorize('create', GlobalProductAttribute::class);
            } else {
                if (! empty($validated['brand_id'])) {
                    $brand = Brand::query()->findOrFail($validated['brand_id']);
                    $this->authorize('update', $brand);
                } else {
                    $this->authorize('create', Brand::class);
                }
            }

            $limits = match ($module) {
                'category' => ['title' => 255, 'description' => 10000],
                'product' => ['title' => 255, 'short_description' => 255, 'description' => 10000],
                default => [],
            };
            $translations = $this->translationService->translateEnglishFields(
                $validated['fields'],
                $limits,
                $validated['target_locales'] ?? null,
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.translated_successfully',
                data: [
                    'translations' => $translations,
                    'source_locale' => 'en',
                    'target_locales' => array_keys($translations),
                ]
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        } catch (\Throwable $e) {
            Log::error('AiTranslationController translate error: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: [],
                status: 422
            );
        }
    }
}
