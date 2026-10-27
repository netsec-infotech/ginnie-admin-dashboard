<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\OnboardingAppTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingScreen\StoreUpdateOnboardingScreenRequest;
use App\Models\OnboardingScreen;
use App\Models\OnboardingScreenSetting;
use App\Services\SpatieMediaService;
use App\Support\DemoMode;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class OnboardingScreenController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    private bool $createPermission;
    private bool $editPermission;
    private bool $deletePermission;

    public function __construct()
    {
        $this->createPermission = $this->hasPermission('onboarding_screen.create');
        $this->editPermission = $this->hasPermission('onboarding_screen.edit');
        $this->deletePermission = $this->hasPermission('onboarding_screen.delete');
    }

    public function index(): View
    {
        $this->authorize('viewAny', OnboardingScreen::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
            ['data' => 'details', 'name' => 'details', 'title' => __('labels.details'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $settings = collect(OnboardingAppTypeEnum::cases())
            ->mapWithKeys(fn (OnboardingAppTypeEnum $appType): array => [
                $appType->value => OnboardingScreenSetting::forApp($appType)->is_enabled,
            ])
            ->all();

        return view('admin.onboarding-screens.index', [
            'appTypes' => OnboardingAppTypeEnum::cases(),
            'columns' => $columns,
            'createPermission' => $this->createPermission,
            'editPermission' => $this->editPermission,
            'settings' => $settings,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', OnboardingScreen::class);

        return view('admin.onboarding-screens.form', [
            'appTypes' => OnboardingAppTypeEnum::cases(),
        ]);
    }

    public function store(StoreUpdateOnboardingScreenRequest $request): JsonResponse
    {
        $this->authorize('create', OnboardingScreen::class);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        $validated = $request->validated();

        $screen = DB::transaction(function () use ($request, $validated): OnboardingScreen {
            $screen = OnboardingScreen::create(Arr::except($validated, ['image', 'background_image', 'overlay_image']));
            if ($request->hasFile('image')) {
                $screen->addMediaFromRequest('image')->toMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_IMAGE());
            }
            if ($request->hasFile('background_image')) {
                $screen->addMediaFromRequest('background_image')->toMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_BACKGROUND());
            }
            if ($request->hasFile('overlay_image')) {
                $screen->addMediaFromRequest('overlay_image')->toMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_OVERLAY());
            }

            return $screen;
        });

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.onboarding_screen_created_successfully',
            data: $request->boolean('stay_on_page')
                ? $this->screenPayload($screen->fresh())
                : ['redirect_url' => route('admin.onboarding-screens.index', ['app_type' => $screen->app_type])],
            status: 201,
        );
    }

    public function edit(OnboardingScreen $onboardingScreen): View
    {
        $this->authorize('update', $onboardingScreen);

        return view('admin.onboarding-screens.form', [
            'appTypes' => OnboardingAppTypeEnum::cases(),
            'onboardingScreen' => $onboardingScreen,
        ]);
    }

    public function update(StoreUpdateOnboardingScreenRequest $request, OnboardingScreen $onboardingScreen): JsonResponse
    {
        $this->authorize('update', $onboardingScreen);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        $validated = $request->validated();

        $onboardingScreen->update(Arr::except($validated, ['image', 'background_image', 'overlay_image']));
        SpatieMediaService::replaceFromRequest($request, $onboardingScreen, 'image', SpatieMediaCollectionName::ONBOARDING_SCREEN_IMAGE());
        SpatieMediaService::replaceFromRequest($request, $onboardingScreen, 'background_image', SpatieMediaCollectionName::ONBOARDING_SCREEN_BACKGROUND());
        SpatieMediaService::replaceFromRequest($request, $onboardingScreen, 'overlay_image', SpatieMediaCollectionName::ONBOARDING_SCREEN_OVERLAY());

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.onboarding_screen_updated_successfully',
            data: $request->boolean('stay_on_page')
                ? $this->screenPayload($onboardingScreen->fresh())
                : ['redirect_url' => route('admin.onboarding-screens.index', ['app_type' => $onboardingScreen->app_type])],
        );
    }

    public function destroy(OnboardingScreen $onboardingScreen): JsonResponse
    {
        $this->authorize('delete', $onboardingScreen);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        $onboardingScreen->delete();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.onboarding_screen_deleted_successfully',
            data: [],
        );
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OnboardingScreen::class);

        $request->validate([
            'app_type' => ['nullable', Rule::in(OnboardingAppTypeEnum::values())],
        ]);

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $appType = (string) $request->get('app_type', OnboardingAppTypeEnum::CUSTOMER());
        $orderColumnIndex = (int) ($request->get('order')[0]['column'] ?? 0);
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';
        $columns = ['id', 'image', 'title', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        if ($orderColumn === 'image') {
            $orderColumn = 'id';
        }

        $baseQuery = OnboardingScreen::query()->forApp($appType);
        $totalRecords = (clone $baseQuery)->count();

        if ($searchValue !== '') {
            $baseQuery->where(function ($query) use ($searchValue): void {
                $query->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('subtitle', 'like', "%{$searchValue}%");
            });
        }

        $filteredRecords = (clone $baseQuery)->count();
        $previewScreens = OnboardingScreen::query()
            ->forApp($appType)
            ->ordered()
            ->limit(OnboardingScreen::MAX_PER_APP)
            ->get()
            ->map(fn (OnboardingScreen $screen): array => $this->screenPayload($screen));
        $screens = $baseQuery
            ->orderBy($orderColumn, $orderDirection)
            ->orderBy('id')
            ->skip($start)
            ->take($length)
            ->get();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'screen_count' => $totalRecords,
            'max_screens' => OnboardingScreen::MAX_PER_APP,
            'can_create' => $totalRecords < OnboardingScreen::MAX_PER_APP,
            'preview' => $previewScreens,
            'data' => $screens->map(fn (OnboardingScreen $screen): array => [
                'id' => $screen->id,
                'image' => view('admin.onboarding-screens.partials.media-thumbnail', ['screen' => $screen])->render(),
                'details' => view('admin.onboarding-screens.partials.datatable-details', ['screen' => $screen])->render(),
                'created_at' => $screen->created_at?->format('Y-m-d'),
                'title_raw' => $screen->title,
                'subtitle_raw' => $screen->subtitle,
                'app_type' => $screen->app_type,
                'image_url' => null,
                'media_type' => null,
                'media_mime_type' => null,
                'background_image_url' => $screen->background_image,
                'overlay_image_url' => $screen->overlay_image,
                'design' => $screen->designPayload(),
                'update_url' => route('admin.onboarding-screens.update', $screen),
                'delete_url' => route('admin.onboarding-screens.destroy', $screen),
                'action' => view('admin.onboarding-screens.partials.actions', [
                    'screen' => $screen,
                    'editPermission' => $this->editPermission,
                    'deletePermission' => $this->deletePermission,
                ])->render(),
            ]),
        ]);
    }

    public function toggle(Request $request, string $appType): JsonResponse
    {
        $this->authorize('update', OnboardingScreen::class);
        if ($response = $this->demoWriteDeniedResponse()) {
            return $response;
        }

        abort_unless(in_array($appType, OnboardingAppTypeEnum::values(), true), 404);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $setting = OnboardingScreenSetting::forApp($appType);
        $setting->update(['is_enabled' => (bool) $validated['is_enabled']]);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.onboarding_screen_setting_updated_successfully',
            data: [
                'app_type' => $setting->app_type,
                'is_enabled' => $setting->is_enabled,
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

    private function screenPayload(OnboardingScreen $screen): array
    {
        return [
            'id' => $screen->id,
            'app_type' => $screen->app_type,
            'title' => $screen->title,
            'subtitle' => $screen->subtitle,
            'image' => null,
            'media_type' => null,
            'media_mime_type' => null,
            'background_image_url' => $screen->background_image,
            'overlay_image_url' => $screen->overlay_image,
            'design' => $screen->designPayload(),
            'update_url' => route('admin.onboarding-screens.update', $screen),
        ];
    }
}
