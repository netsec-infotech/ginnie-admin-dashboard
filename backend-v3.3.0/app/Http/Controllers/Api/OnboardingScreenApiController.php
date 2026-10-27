<?php

namespace App\Http\Controllers\Api;

use App\Enums\OnboardingAppTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\OnboardingScreenResource;
use App\Models\OnboardingScreen;
use App\Models\OnboardingScreenSetting;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OnboardingScreenApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app' => ['required', Rule::in(OnboardingAppTypeEnum::values())],
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
        $setting = OnboardingScreenSetting::where('app_type', $appType)->first();
        $isEnabled = $setting?->is_enabled ?? true;
        $screens = $isEnabled
            ? OnboardingScreen::query()->forApp($appType)->ordered()->limit(OnboardingScreen::MAX_PER_APP)->get()
            : collect();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.onboarding_screens_fetched_successfully',
            data: [
                'app_type' => $appType,
                'is_enabled' => $isEnabled,
                'screens' => OnboardingScreenResource::collection($screens),
            ],
        );
    }
}
