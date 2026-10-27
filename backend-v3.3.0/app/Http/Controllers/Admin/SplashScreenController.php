<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SplashAppTypeEnum;
use App\Enums\SplashSlotEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\SplashScreen\StoreSplashScreenRequest;
use App\Http\Resources\SplashScreenResource;
use App\Models\SplashScreen;
use App\Services\SplashScreenService;
use App\Support\DemoMode;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SplashScreenController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    private bool $editPermission;
    private bool $deletePermission;

    public function __construct(private readonly SplashScreenService $splashScreens)
    {
        $this->editPermission = $this->hasPermission('splash_screen.edit');
        $this->deletePermission = $this->hasPermission('splash_screen.delete');
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SplashScreen::class);

        $selectedApp = (string) $request->query('app_type', SplashAppTypeEnum::CUSTOMER());
        if (! in_array($selectedApp, SplashAppTypeEnum::values(), true)) {
            $selectedApp = SplashAppTypeEnum::CUSTOMER();
        }

        $screens = SplashScreen::query()
            ->forApp($selectedApp)
            ->whereIn('slot', [SplashSlotEnum::CURRENT(), SplashSlotEnum::STAGED_NEXT()])
            ->with('media')
            ->get()
            ->keyBy('slot');

        return view('admin.splash-screens.index', [
            'appTypes' => SplashAppTypeEnum::cases(),
            'selectedApp' => $selectedApp,
            'currentSplash' => $screens->get(SplashSlotEnum::CURRENT()),
            'stagedSplash' => $screens->get(SplashSlotEnum::STAGED_NEXT()),
            'editPermission' => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ]);
    }

    public function store(StoreSplashScreenRequest $request): JsonResponse
    {
        $this->authorize('create', SplashScreen::class);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        $screen = $this->splashScreens->save($request->validated());

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.splash_screen_saved_successfully',
            data: $this->screenPayload($screen),
            status: 201,
        );
    }

    public function promote(SplashScreen $splashScreen): JsonResponse
    {
        $this->authorize('update', $splashScreen);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        abort_unless($splashScreen->slot === SplashSlotEnum::STAGED_NEXT(), 404);

        $current = $this->splashScreens->promote($splashScreen);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.splash_screen_promoted_successfully',
            data: $this->screenPayload($current),
        );
    }

    public function destroy(SplashScreen $splashScreen): JsonResponse
    {
        $this->authorize('delete', $splashScreen);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        $appType = $splashScreen->app_type;
        $splashScreen->delete();
        $this->splashScreens->clearCache($appType);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.splash_screen_deleted_successfully',
            data: [],
        );
    }

    public function appState(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SplashScreen::class);

        $validated = $request->validate([
            'app_type' => ['required', Rule::in(SplashAppTypeEnum::values())],
        ]);

        $screens = SplashScreen::query()
            ->forApp((string) $validated['app_type'])
            ->whereIn('slot', [SplashSlotEnum::CURRENT(), SplashSlotEnum::STAGED_NEXT()])
            ->with('media')
            ->get()
            ->keyBy('slot');

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.success',
            data: [
                'current' => $screens->has(SplashSlotEnum::CURRENT()) ? $this->screenPayload($screens->get(SplashSlotEnum::CURRENT())) : null,
                'staged_next' => $screens->has(SplashSlotEnum::STAGED_NEXT()) ? $this->screenPayload($screens->get(SplashSlotEnum::STAGED_NEXT())) : null,
            ],
        );
    }

    private function demoWriteDeniedResponse(): ?JsonResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) || $user?->hasRole('super-admin');

        if (DemoMode::isDemoUser($user) && ! $isSuperAdmin) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                data: [],
                status: 403,
            );
        }

        return null;
    }

    private function screenPayload(SplashScreen $screen): array
    {
        return [
            'id' => $screen->id,
            'app_type' => $screen->app_type,
            'slot' => $screen->slot,
            'version' => $screen->version,
            'bg_type' => $screen->bg_type,
            'bg_color' => $screen->bg_color,
            'background_image_url' => $screen->background_image_url,
            'background_video_url' => $screen->background_video_url,
            'overlay_enabled' => (bool) $screen->overlay_enabled,
            'overlay_type' => $screen->overlay_type,
            'overlay_url' => $screen->overlay_url,
            'overlay_x_percent' => (float) $screen->overlay_x_percent,
            'overlay_y_percent' => (float) $screen->overlay_y_percent,
            'overlay_width_percent' => (float) $screen->overlay_width_percent,
            'min_display_seconds' => $screen->min_display_seconds,
            'activate_at' => $screen->activate_at?->format('Y-m-d\TH:i'),
            'delete_url' => route('admin.splash-screens.destroy', $screen),
            'promote_url' => route('admin.splash-screens.promote', $screen),
            'api' => SplashScreenResource::make($screen)->resolve(),
        ];
    }
}
