<?php

namespace App\Http\Controllers\Api;

use App\Enums\SplashAppTypeEnum;
use App\Enums\SplashSlotEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\SplashScreenResource;
use App\Models\SplashScreen;
use App\Services\SplashScreenService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SplashScreenApiController extends Controller
{
    public function __construct(private readonly SplashScreenService $splashScreens) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app' => ['required', Rule::in(SplashAppTypeEnum::values())],
        ]);

        if ($validator->fails()) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed',
                data: $validator->errors(),
                status: 422,
            );
        }

        $appType = (string) $request->query('app');
        $this->splashScreens->promoteDueForApp($appType);

        $data = Cache::remember(SplashScreenService::CACHE_PREFIX.$appType, now()->addMinutes(30), function () use ($appType): array {
            $screens = SplashScreen::query()
                ->forApp($appType)
                ->whereIn('slot', [SplashSlotEnum::CURRENT(), SplashSlotEnum::STAGED_NEXT()])
                ->with('media')
                ->get()
                ->keyBy('slot');

            return [
                'current' => $screens->has(SplashSlotEnum::CURRENT())
                    ? SplashScreenResource::make($screens->get(SplashSlotEnum::CURRENT()))->resolve()
                    : null,
                'staged_next' => $screens->has(SplashSlotEnum::STAGED_NEXT())
                    ? SplashScreenResource::make($screens->get(SplashSlotEnum::STAGED_NEXT()))->resolve()
                    : null,
            ];
        });

        $etag = $this->etag($appType, $data);
        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->json(null, 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=60');
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.splash_config_fetched_successfully',
            data: $data,
        )->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=60');
    }

    private function etag(string $appType, array $data): string
    {
        return '"'.sha1(json_encode([
            'app' => $appType,
            'current' => $data['current']['cache_key'] ?? ($data['current']['updated_at'] ?? null),
            'staged_next' => $data['staged_next']['cache_key'] ?? ($data['staged_next']['updated_at'] ?? null),
        ])).'"';
    }
}
