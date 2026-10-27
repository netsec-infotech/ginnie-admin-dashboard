<?php

namespace App\Http\Controllers\Api;

use App\Enums\LanguageSurfaceEnum;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\ClientLanguageService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Languages')]
class LanguageApiController extends Controller
{
    /**
     * Get available languages for a client surface.
     */
    #[QueryParameter('surface', description: 'Client surface (web, customer_app, seller_app, rider_app).', type: 'string', required: true, example: 'web')]
    public function languages(Request $request, ClientLanguageService $languages): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(config('languages.enabled_client_surfaces', []))],
        ]);
        $surface = LanguageSurfaceEnum::from($data['surface']);
        $available = $languages->languages($surface);
        $default = $languages->defaultLanguage($surface);

        return ApiResponseType::sendJsonResponse(true, 'labels.languages_fetched_successfully', [
            'default' => $default?->code,
            'languages' => $available->map(fn (Language $language): array => [
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'direction' => $language->direction,
                'flag_url' => $language->flag_url,
            ])->values()->all(),
        ]);
    }

    /**
     * Get resolved translation labels for a language.
     */
    #[QueryParameter('surface', description: 'Client surface (web, customer_app, seller_app, rider_app).', type: 'string', required: true, example: 'web')]
    #[QueryParameter('locale', description: 'Language code (e.g. en, es, ar, hi).', type: 'string', required: false, example: 'en')]
    public function labels(Request $request, ClientLanguageService $languages): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(config('languages.enabled_client_surfaces', []))],
            'locale' => ['nullable', 'string', 'max:20'],
        ]);
        $surface = LanguageSurfaceEnum::from($data['surface']);
        $language = $languages->resolveLanguage($surface, $data['locale'] ?? null);

        if (! $language) {
            return ApiResponseType::sendJsonResponse(false, 'labels.language_not_available', null, 404);
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.language_labels_fetched_successfully', [
            'locale' => $language->code,
            'direction' => $language->direction,
            'labels' => $languages->resolvedLabels($surface, $language),
        ]);
    }
}
