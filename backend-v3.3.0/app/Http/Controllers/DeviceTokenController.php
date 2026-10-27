<?php

namespace App\Http\Controllers;

use App\Enums\DeviceTypeEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Http\Requests\Device\ForgetDeviceRequest;
use App\Http\Requests\Device\SyncDeviceRequest;
use App\Services\DeviceTokenService;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Persists / removes the browser's FCM token for the currently authenticated
 * admin or seller. Customer + delivery boy tokens are still captured at the
 * API login layer via AuthTrait::storeFcmToken — this controller only powers
 * the web panels, where the login form's hidden fcm_token field was being
 * dropped on the server side.
 */
class DeviceTokenController extends Controller
{
    use PanelAware;

    public function __construct(protected DeviceTokenService $deviceTokenService) {}

    public function sync(SyncDeviceRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.unauthenticated'),
                    data: [],
                    status: 401,
                );
            }

            $validated = $request->validated();
            $roleType = ! empty($validated['role_type'])
                ? NotificationRoleTypeEnum::from($validated['role_type'])
                : NotificationRoleTypeEnum::fromPanel($this->getPanel());

            $this->deviceTokenService->sync(
                $user,
                $validated['fcm_token'],
                DeviceTypeEnum::from($validated['device_type']),
                $roleType,
                $validated['previous_token'] ?? null,
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.device_synced'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('Device token sync failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    public function forget(ForgetDeviceRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.unauthenticated'),
                    data: [],
                    status: 401,
                );
            }

            $this->deviceTokenService->forget($user, $request->validated('fcm_token'));

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.device_forgotten'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('Device token forget failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }
}
