<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Admin\CategoryManagementResource;
use App\Models\Category;
use App\Models\CategorySearchLabel;
use App\Models\HomeNavbarItem;
use App\Services\Ai\AiManager;
use App\Services\CatalogLanguageService;
use App\Services\CategoryService;
use App\Support\DemoMode;
use App\Support\HomeCache;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $createPermission = false;

    // Define media collections for easier management
    private array $mediaCollections = [
        'image' => SpatieMediaCollectionName::CATEGORY_IMAGE,
        'icon' => SpatieMediaCollectionName::CATEGORY_ICON,
        'active_icon' => SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON,
        'background_image' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE,
        'background_image_web' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB,
        'background_image_web_mobile' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE,
        'background_image_app' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP,
        'background_animation' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION,
        'background_animation_web' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB,
        'background_animation_app' => SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP,
    ];

    public function __construct(
        protected CategoryService $categoryService,
        protected AiManager $aiManager,
    ) {
        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::CATEGORY_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::CATEGORY_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::CATEGORY_CREATE());
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image')],
            ['data' => 'parent', 'name' => 'parent', 'title' => __('labels.parent')],
            ['data' => 'commission', 'name' => 'commission', 'title' => __('labels.commission')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'requires_approval', 'name' => 'requires_approval', 'title' => __('labels.requires_approval')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        if ($this->getPanel() === 'admin') {
            array_splice($columns, 5, 0, [[
                'data' => 'products_count',
                'name' => 'products_count',
                'title' => __('labels.products'),
            ]]);
        }

        if ($this->getPanel() === 'admin' && $this->deletePermission) {
            array_unshift($columns, [
                'data' => 'select',
                'name' => 'select',
                'title' => '<input type="checkbox" id="select-all-categories" class="form-check-input bulk-delete-select-all">',
                'orderable' => false,
                'searchable' => false,
            ]);
        }

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $catalogLanguages = app(CatalogLanguageService::class)->active();
        $aiTranslationEnabled = (bool) ($this->aiManager->getAiSettings()['enableAiDescription'] ?? false);

        return view($this->panelView('categories.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission', 'catalogLanguages', 'aiTranslationEnabled'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view($this->panelView('categories.form'), [
            'category' => null,
            'pageMode' => 'create',
            'catalogLanguages' => app(CatalogLanguageService::class)->active(),
            'aiTranslationEnabled' => (bool) ($this->aiManager->getAiSettings()['enableAiDescription'] ?? false),
        ]);
    }

    /**
     * Show the form for editing the specified category.
     */
    public function editPage($id): View
    {
        $category = Category::with(['parent', 'searchLabelSetting'])->findOrFail($id);
        $this->authorize('update', $category);

        return view($this->panelView('categories.form'), [
            'category' => $category,
            'pageMode' => 'edit',
            'catalogLanguages' => app(CatalogLanguageService::class)->active(),
            'aiTranslationEnabled' => (bool) ($this->aiManager->getAiSettings()['enableAiDescription'] ?? false),
        ]);
    }

    /**
     * Display sorting page for parent categories.
     */
    public function sort(): View
    {
        $this->authorize('viewAny', Category::class);
        // Only show home categories for sorting
        $parentCategories = Category::query()
            ->whereNull('parent_id')
            ->where('is_home_category', true)
            ->ordered()
            ->get();

        $canEdit = $this->hasPermission(AdminPermissionEnum::CATEGORY_EDIT()) || $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_EDIT());

        return view($this->panelView('categories.sort'), compact('parentCategories', 'canEdit'));
    }

    /**
     * Update sort order of parent categories.
     */
    public function updateSort(Request $request): JsonResponse
    {
        try {
            if (DemoMode::isDemoUser(auth()->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    status: 403
                );
            }

            // Ensure user has edit permission similar to update
            if (! $this->editPermission) {
                throw new AuthorizationException(__('labels.permission_denied'));
            }

            $request->validate([
                'categories' => 'required|array',
                'categories.*' => 'required|integer|exists:categories,id',
            ]);

            DB::beginTransaction();
            foreach ($request->categories as $index => $categoryId) {
                Category::where('id', $categoryId)->whereNull('parent_id')->update([
                    'sort_order' => $index + 1,
                ]);
            }
            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.sort_order_updated_successfully'),
                data: []
            );
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors()
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: []
            );
        }
    }

    /**
     * Update the list of parent categories marked as home categories.
     */
    public function updateHomeCategories(Request $request): JsonResponse
    {
        try {
            if (DemoMode::isDemoUser(auth()->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    status: 403
                );
            }

            if (! $this->editPermission) {
                throw new AuthorizationException(__('labels.permission_denied'));
            }

            $validated = $request->validate([
                'home_category_ids' => 'array',
                'home_category_ids.*' => [
                    'integer',
                    'exists:categories,id',
                    function ($attribute, $value, $fail) {
                        $category = Category::find($value);
                        if ($category && ! is_null($category->parent_id)) {
                            $fail(__('validation.home_category_must_be_root'));
                        }
                    },
                ],
            ]);

            $selectedIds = collect($validated['home_category_ids'] ?? [])->unique()->values();

            DB::beginTransaction();

            // Work only with parent categories - already validated above
            $parentIds = Category::query()->whereNull('parent_id')->pluck('id');

            // Set is_home_category=true for selected parent IDs, false for others
            Category::query()
                ->whereIn('id', $parentIds)
                ->update(['is_home_category' => false]);

            if ($selectedIds->isNotEmpty()) {
                Category::query()
                    ->whereIn('id', $parentIds->intersect($selectedIds))
                    ->update(['is_home_category' => true]);
            }

            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.updated_successfully'),
                data: [
                    'updated_ids' => $selectedIds,
                ]
            );
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors()
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: []
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            if (DemoMode::isDemoUser(auth()->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    status: 403
                );
            }

            $this->authorize('create', Category::class);
            $validated = $request->validated();

            // Set default values
            if (empty($request->status)) {
                $validated['status'] = CategoryStatusEnum::INACTIVE();
            }
            if (empty($request->requires_approval)) {
                $validated['requires_approval'] = false;
            }

            // Handle background type logic
            $this->handleBackgroundTypeLogic($validated);

            $category = $this->categoryService->createCategory($validated, $request->input('searchLabels', []));

            // Handle file uploads for creation
            $this->handleFileUploadsForStore($request, $category);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_created_successfully'),
                data: new CategoryManagementResource($category->load(['parent', 'searchLabelSetting'])),
                status: 201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $category = Category::with(['parent', 'searchLabelSetting'])->findOrFail($id);
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_retrieved_successfully'),
                data: new CategoryManagementResource($category)
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.category_not_found')
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, $id): JsonResponse
    {
        try {
            if (DemoMode::isDemoUser(auth()->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    status: 403
                );
            }

            $category = Category::findOrFail($id);
            $this->authorize('update', $category);
            $validated = $request->validated();

            if (empty($request->status)) {
                $validated['status'] = CategoryStatusEnum::INACTIVE();
            }
            if (empty($request->requires_approval)) {
                $validated['requires_approval'] = false;
            }

            // Handle background type logic
            $this->handleBackgroundTypeLogic($validated, $category);

            $category = $this->categoryService->updateCategory($category, $validated, $request->input('searchLabels', []));

            // Handle file uploads and removals for update
            $this->handleFileUploadsForUpdate($request, $category);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_updated_successfully'),
                data: new CategoryManagementResource($category->load(['parent', 'searchLabelSetting']))
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.category_not_found',
                data: [],
                status: 404
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        }
    }

    /**
     * Update category header appearance specifically (AJAX)
     */
    public function updateAppearance(Request $request, $id): JsonResponse
    {
        try {
            if (DemoMode::isDemoUser(auth()->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    status: 403
                );
            }

            if (! $this->editPermission) {
                throw new AuthorizationException(__('labels.permission_denied'));
            }

            $isCustom = $request->boolean('is_custom') || $request->input('is_custom') === '1';
            $category = null;
            $navbarItem = null;

            if ($isCustom) {
                $navbarItem = HomeNavbarItem::find($id);
            } else {
                $category = Category::find($id);
                if (! $category) {
                    $navbarItem = HomeNavbarItem::find($id);
                }
            }

            if ($navbarItem) {
                $validated = $request->validate([
                    'background_type' => 'nullable|string|in:color,image,animation,none',
                    'background_color_web' => 'nullable|string|max:20',
                    'background_color_app' => 'nullable|string|max:20',
                    'font_color' => 'nullable|string|max:20',
                    'icon' => 'nullable|file|image|max:10240',
                    'active_icon' => 'nullable|file|image|max:10240',
                    'remove_icon' => 'nullable|boolean',
                    'remove_active_icon' => 'nullable|boolean',
                    'background_image_web' => 'nullable|file|image|max:10240',
                    'background_image_web_mobile' => 'nullable|file|image|max:10240',
                    'background_image_app' => 'nullable|file|image|max:10240',
                    'background_animation_web' => 'nullable|file|mimes:json,txt|max:10240',
                    'background_animation_app' => 'nullable|file|mimes:json,txt|max:10240',
                    'remove_background_image_web' => 'nullable|boolean',
                    'remove_background_image_web_mobile' => 'nullable|boolean',
                    'remove_background_image_app' => 'nullable|boolean',
                    'remove_background_animation_web' => 'nullable|boolean',
                    'remove_background_animation_app' => 'nullable|boolean',
                ]);

                $bgType = $validated['background_type'] ?? 'none';
                $storedBgType = match ($bgType) {
                    'color' => 'color',
                    'image' => 'image',
                    'animation' => 'lottie',
                    default => 'none',
                };

                $homeAppearance = $navbarItem->home_appearance ?? [];
                // Keep flat keys for the existing builder UI and nested keys for public APIs.
                $homeAppearance['backgroundType'] = $bgType;
                if (isset($validated['background_color_web'])) {
                    $homeAppearance['backgroundColorWeb'] = $validated['background_color_web'];
                }
                if (isset($validated['background_color_app'])) {
                    $homeAppearance['backgroundColorApp'] = $validated['background_color_app'];
                }
                if (isset($validated['font_color'])) {
                    $homeAppearance['fontColor'] = $validated['font_color'];
                }
                $homeAppearance['app'] = array_merge((array) ($homeAppearance['app'] ?? []), [
                    'background_type' => $storedBgType,
                    'gradient_start' => $validated['background_color_app'] ?? ($homeAppearance['backgroundColorApp'] ?? '#ffffff'),
                    'gradient_end' => $validated['background_color_app'] ?? ($homeAppearance['backgroundColorApp'] ?? '#ffffff'),
                    'font_color' => $validated['font_color'] ?? ($homeAppearance['fontColor'] ?? '#475569'),
                ]);
                $homeAppearance['desktop'] = array_merge((array) ($homeAppearance['desktop'] ?? []), [
                    'background_type' => $storedBgType,
                    'gradient_start' => $validated['background_color_web'] ?? ($homeAppearance['backgroundColorWeb'] ?? '#ffffff'),
                    'gradient_end' => $validated['background_color_web'] ?? ($homeAppearance['backgroundColorWeb'] ?? '#ffffff'),
                    'font_color' => $validated['font_color'] ?? ($homeAppearance['fontColor'] ?? '#475569'),
                ]);

                $navbarItem->update([
                    'home_appearance' => $homeAppearance,
                ]);

                if ($request->hasFile('icon')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON->value);
                    $navbarItem->addMediaFromRequest('icon')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON->value);
                } elseif ($request->boolean('remove_icon')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON->value);
                }

                if ($request->hasFile('active_icon')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON->value);
                    $navbarItem->addMediaFromRequest('active_icon')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON->value);
                } elseif ($request->boolean('remove_active_icon')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON->value);
                }

                if ($bgType !== 'image') {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND->value);
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                }

                if ($request->hasFile('background_image_web')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                    $navbarItem->addMediaFromRequest('background_image_web')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                } elseif ($request->boolean('remove_background_image_web')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                }

                if ($request->hasFile('background_image_web_mobile')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND->value);
                    $navbarItem->addMediaFromRequest('background_image_web_mobile')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND->value);
                } elseif ($request->boolean('remove_background_image_web_mobile')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND->value);
                }

                if ($request->hasFile('background_image_app')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                    $navbarItem->addMediaFromRequest('background_image_app')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                } elseif ($request->boolean('remove_background_image_app')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                }

                if ($request->hasFile('background_animation_web')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                    $navbarItem->addMediaFromRequest('background_animation_web')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                } elseif ($request->boolean('remove_background_animation_web')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND->value);
                }

                if ($request->hasFile('background_animation_app')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                    $navbarItem->addMediaFromRequest('background_animation_app')->toMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                } elseif ($request->boolean('remove_background_animation_app')) {
                    $navbarItem->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND->value);
                }

                HomeCache::flushLayout();
                $navbarItem->refresh();

                return ApiResponseType::sendJsonResponse(
                    success: true,
                    message: __('labels.category_updated_successfully'),
                    data: [
                        'id' => $navbarItem->id,
                        'title' => $navbarItem->title,
                        'icon' => $navbarItem->icon,
                        'active_icon' => $navbarItem->active_icon,
                        'background_type' => $navbarItem->background_type,
                        'background_color_web' => $navbarItem->background_color_web,
                        'background_color_app' => $navbarItem->background_color_app,
                        'background_image_web' => $navbarItem->background_image_web,
                        'background_image_web_mobile' => $navbarItem->background_image_web_mobile,
                        'background_image_app' => $navbarItem->background_image_app,
                        'background_animation_web' => $navbarItem->background_animation_web,
                        'background_animation_app' => $navbarItem->background_animation_app,
                        'font_color' => $navbarItem->font_color,
                    ]
                );
            }

            if (! $category) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.category_not_found',
                    data: [],
                    status: 404
                );
            }

            $this->authorize('update', $category);

            $validated = $request->validate([
                'background_type' => 'nullable|string|in:color,image,animation,none',
                'background_color_web' => 'nullable|string|max:20',
                'background_color_app' => 'nullable|string|max:20',
                'font_color' => 'nullable|string|max:20',
                'icon' => 'nullable|file|image|max:10240',
                'active_icon' => 'nullable|file|image|max:10240',
                'remove_icon' => 'nullable|boolean',
                'remove_active_icon' => 'nullable|boolean',
                'background_image_web' => 'nullable|file|image|max:10240',
                'background_image_web_mobile' => 'nullable|file|image|max:10240',
                'background_image_app' => 'nullable|file|image|max:10240',
                'background_animation_web' => 'nullable|file|mimes:json,txt|max:10240',
                'background_animation_app' => 'nullable|file|mimes:json,txt|max:10240',
                'remove_background_image_web' => 'nullable|boolean',
                'remove_background_image_web_mobile' => 'nullable|boolean',
                'remove_background_image_app' => 'nullable|boolean',
                'remove_background_animation_web' => 'nullable|boolean',
                'remove_background_animation_app' => 'nullable|boolean',
            ]);

            if (isset($validated['background_type']) && $validated['background_type'] === 'none') {
                $validated['background_type'] = null;
            }

            $this->handleBackgroundTypeLogic($validated, $category);

            $category->update($validated);

            // Handle icon and active_icon uploads
            if ($request->hasFile('icon')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_ICON());
                $category->addMediaFromRequest('icon')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_ICON());
            } elseif ($request->boolean('remove_icon')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_ICON());
            }

            if ($request->hasFile('active_icon')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
                $category->addMediaFromRequest('active_icon')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
            } elseif ($request->boolean('remove_active_icon')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
            }

            // Handle background uploads
            if ($request->hasFile('background_image_web')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
                $category->addMediaFromRequest('background_image_web')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
            } elseif ($request->boolean('remove_background_image_web')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
            }

            if ($request->hasFile('background_image_web_mobile')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
                $category->addMediaFromRequest('background_image_web_mobile')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
            } elseif ($request->boolean('remove_background_image_web_mobile')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
            }

            if ($request->hasFile('background_image_app')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
                $category->addMediaFromRequest('background_image_app')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
            } elseif ($request->boolean('remove_background_image_app')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
            }

            if ($request->hasFile('background_animation_web')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
                $category->addMediaFromRequest('background_animation_web')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
            } elseif ($request->boolean('remove_background_animation_web')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
            }

            if ($request->hasFile('background_animation_app')) {
                $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
                $category->addMediaFromRequest('background_animation_app')->toMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
            } elseif ($request->boolean('remove_background_animation_app')) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
            }

            $category->refresh();

            $bgType = is_object($category->background_type) ? $category->background_type->value : ($category->background_type ?? 'none');

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_updated_successfully'),
                data: [
                    'id' => $category->id,
                    'title' => $category->getTranslation('title', 'en', false),
                    'icon' => $category->icon,
                    'active_icon' => $category->active_icon,
                    'background_type' => $bgType,
                    'background_color_web' => $category->background_color_web,
                    'background_color_app' => $category->background_color_app,
                    'background_image_web' => $category->background_image_web,
                    'background_image_web_mobile' => $category->background_image_web_mobile,
                    'background_image_app' => $category->background_image_app,
                    'background_animation_web' => $category->background_animation_web,
                    'background_animation_app' => $category->background_animation_app,
                    'font_color' => $category->font_color,
                ]
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
                status: 403
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.operation_failed'),
                data: ['error' => $e->getMessage()],
                status: 500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            if (DemoMode::isDemoUser(auth()->user())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.demo_mode_message_placeholder'),
                    status: 403
                );
            }

            DB::beginTransaction();
            $category = Category::findOrFail($id);
            $this->authorize('delete', $category);
            // Prevent deletion if category has any products assigned
            if ($category->products()->exists()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.category_cannot_be_deleted_with_products'),
                );
            }
            // Prevent deletion if any direct child category has products assigned
            if ($category->children()->whereHas('products')->exists()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.category_cannot_be_deleted_with_products'),
                );
            }

            $category->delete();
            $category->clearMediaCollection('category');
            $category->clearMediaCollection('icon');
            $category->clearMediaCollection('active_icon');
            $category->clearMediaCollection('background_image');
            $category->children()->update(['parent_id' => null]);
            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_deleted_successfully'),
            );
        } catch (ModelNotFoundException) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.category_not_found'),
                status: 404
            );
        } catch (AuthorizationException) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.operation_failed'),
                data: ['error' => $e->getMessage()],
            );
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['required', 'integer', 'distinct'],
            ]);

            $deletedCount = 0;
            $skipped = [];
            $failed = [];

            foreach ($validated['ids'] as $categoryId) {
                $category = Category::find($categoryId);

                if (! $category) {
                    $skipped[] = [
                        'id' => (int) $categoryId,
                        'reason' => __('labels.category_not_found'),
                    ];

                    continue;
                }

                try {
                    $this->authorize('delete', $category);

                    if ($category->products()->exists() || $category->children()->whereHas('products')->exists()) {
                        $skipped[] = [
                            'id' => (int) $category->id,
                            'title' => $category->getTranslation('title', 'en', false),
                            'reason' => __('messages.category_cannot_be_deleted_with_products'),
                        ];

                        continue;
                    }

                    DB::beginTransaction();
                    $category->delete();
                    $category->clearMediaCollection('category');
                    $category->clearMediaCollection('icon');
                    $category->clearMediaCollection('active_icon');
                    $category->clearMediaCollection('background_image');
                    $category->children()->update(['parent_id' => null]);
                    DB::commit();

                    $deletedCount++;
                } catch (AuthorizationException) {
                    if (DB::transactionLevel() > 0) {
                        DB::rollBack();
                    }
                    $skipped[] = [
                        'id' => (int) $category->id,
                        'title' => $category->getTranslation('title', 'en', false),
                        'reason' => __('labels.permission_denied'),
                    ];
                } catch (\Throwable $e) {
                    if (DB::transactionLevel() > 0) {
                        DB::rollBack();
                    }
                    $failed[] = [
                        'id' => (int) $category->id,
                        'title' => $category->getTranslation('title', 'en', false),
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.bulk_delete_completed'),
                data: [
                    'deleted_count' => $deletedCount,
                    'skipped' => $skipped,
                    'failed' => $failed,
                ]
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        }
    }

    /**
     * Handle file uploads during category creation
     */
    private function handleFileUploadsForStore(StoreCategoryRequest $request, Category $category): void
    {
        foreach ($this->mediaCollections as $requestField => $collectionName) {
            if ($request->hasFile($requestField)) {
                $collectionValue = is_callable($collectionName) ? $collectionName() : $collectionName;
                $category->addMediaFromRequest($requestField)->toMediaCollection($collectionValue);
            }
        }
    }

    /**
     * Handle file uploads and removals during category update
     */
    private function handleFileUploadsForUpdate(UpdateCategoryRequest $request, Category $category): void
    {
        foreach ($this->mediaCollections as $requestField => $collectionName) {
            $collectionValue = is_callable($collectionName) ? $collectionName() : $collectionName;

            if ($request->hasFile($requestField)) {
                // File exists in request, handle upload
                $this->handleSingleFileUpload($request, $category, $requestField, $collectionValue);
            } elseif ($requestField !== 'image' && $request->boolean('remove_'.$requestField)) {
                // File doesn't exist in request and remove requested, remove from media library
                $this->removeMediaFromCollection($category, $collectionValue);
            }
        }
    }

    /**
     * Handle single file upload with duplicate check
     */
    private function handleSingleFileUpload(UpdateCategoryRequest $request, Category $category, string $requestField, string $collectionName): void
    {
        $newFile = $request->file($requestField);
        $existingMedia = $category->getFirstMedia($collectionName);
        $newFileName = $newFile->getClientOriginalName();

        // Only upload if file doesn't exist or is different
        if (! $existingMedia || $existingMedia->file_name !== $newFileName) {
            $category->clearMediaCollection($collectionName);
            $category->addMedia($newFile)->toMediaCollection($collectionName);
        }
    }

    /**
     * Remove media from collection
     */
    private function removeMediaFromCollection(Category $category, string $collectionName): void
    {
        $existingMedia = $category->getFirstMedia($collectionName);
        if ($existingMedia) {
            $existingMedia->delete();
        }
    }

    /**
     * Handle background type logic
     */
    private function handleBackgroundTypeLogic(array &$validated, ?Category $category = null): void
    {
        $bgType = $validated['background_type'] ?? null;
        if ($bgType instanceof CategoryBackgroundTypeEnum) {
            $bgType = $bgType->value;
        }

        if (empty($bgType)) {
            $validated['background_type'] = null;
            $validated['background_color'] = null;
            $this->clearBackgroundColorMetadata($validated, $category);
            if ($category) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
            }
        } elseif ($bgType === CategoryBackgroundTypeEnum::IMAGE->value || $bgType === CategoryBackgroundTypeEnum::IMAGE()) {
            $validated['background_color'] = null;
            $this->clearBackgroundColorMetadata($validated, $category);
            if ($category) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
            }
        } elseif ($bgType === CategoryBackgroundTypeEnum::ANIMATION->value || $bgType === CategoryBackgroundTypeEnum::ANIMATION()) {
            $validated['background_color'] = null;
            $this->clearBackgroundColorMetadata($validated, $category);
            if ($category) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
            }
        } elseif ($bgType === CategoryBackgroundTypeEnum::COLOR->value || $bgType === CategoryBackgroundTypeEnum::COLOR()) {
            $metadata = $validated['metadata'] ?? ($category?->metadata ?? []);
            if (isset($validated['background_color_web'])) {
                $metadata['background_color_web'] = $validated['background_color_web'];
            }
            if (isset($validated['background_color_app'])) {
                $metadata['background_color_app'] = $validated['background_color_app'];
            }
            $validated['metadata'] = $metadata;
            $validated['background_color'] = $validated['background_color_web'] ?? ($validated['background_color_app'] ?? ($validated['background_color'] ?? null));

            if ($category) {
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
                $this->removeMediaFromCollection($category, SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
            }
        }
    }

    private function clearBackgroundColorMetadata(array &$validated, ?Category $category = null): void
    {
        if (isset($validated['metadata'])) {
            unset($validated['metadata']['background_color_web'], $validated['metadata']['background_color_app']);
        } elseif ($category && is_array($category->metadata)) {
            $meta = $category->metadata;
            unset($meta['background_color_web'], $meta['background_color_app']);
            $validated['metadata'] = $meta;
        }
    }

    private function syncSearchLabels(Category $category, array $searchLabels): void
    {
        $normalizedSearchLabels = collect($searchLabels)
            ->filter(fn ($label) => is_string($label))
            ->map(fn ($label) => trim($label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedSearchLabels)) {
            $category->searchLabelSetting()->delete();

            return;
        }

        CategorySearchLabel::updateOrCreate(
            ['category_id' => $category->id],
            ['search_labels' => $normalizedSearchLabels]
        );
    }

    public function getCategories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';
        $selectedIds = $this->selectedIdsFromRequest($request);

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $requestedColumn = (string) $request->input("columns.{$orderColumnIndex}.data", 'id');
        $sortableColumns = [
            'id' => 'categories.id',
            'title' => 'title',
            'parent' => 'categories.parent_id',
            'commission' => 'categories.commission',
            'products_count' => 'products_count',
            'status' => 'categories.status',
            'requires_approval' => 'categories.requires_approval',
            'created_at' => 'categories.created_at',
        ];

        $orderColumn = $sortableColumns[$requestedColumn] ?? 'categories.id';
        if (! in_array($orderDirection, ['asc', 'desc'], true) || ! array_key_exists($requestedColumn, $sortableColumns)) {
            $orderDirection = 'asc';
        }

        $query = Category::with('parent');
        if ($this->getPanel() === 'admin') {
            // Product count mirrors the direct category_id products used by storefront category listings.
            $query->withCount('products');
        }

        $totalRecords = Category::count();
        $filteredRecords = $totalRecords;

        // Search filter
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->whereEnglishTextLike($searchValue)
                    ->orWhereHas('parent', function ($q) use ($searchValue) {
                        $q->whereEnglishTitleLike($searchValue);
                    });
            });
        }

        if (! empty($selectedIds) && ! empty($searchValue)) {
            // Keep previously selected rows visible at the top even after the user searches elsewhere.
            $query->orWhereIn('id', $selectedIds);
        }

        $filteredRecords = $query->count();

        $query->when(! empty($selectedIds), fn ($q) => $this->orderSelectedFirst($q, $selectedIds, 'categories.id'));
        $requestedColumn === 'title'
            ? $query->orderByEnglishTitle($orderDirection)
            : $query->orderBy($orderColumn, $orderDirection);

        $data = $query
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($category) {
                $title = $category->getTranslation('title', 'en', false);
                $parentTitle = $category->parent?->getTranslation('title', 'en', false);
                $row = [
                    'id' => $category->id,
                    'title' => $title,
                    'image' => view('partials.image', ['image' => (! empty($category->image) ? $category->image : asset('assets/images/category-placeholder.png')), 'title' => $title])->render(),
                    'status' => view('partials.status', ['status' => $category->status ?? ''])->render(),
                    'requires_approval' => '<span class="badge text-uppercase '.($category->requires_approval == 1 ? 'bg-info-lt' : 'bg-warning-lt').'">'.($category->requires_approval == 1 ? __('labels.required') : __('labels.not_required')).'</span>',
                    'created_at' => $category->created_at->format('Y-m-d'),
                    'parent' => $parentTitle ?: 'N/A',
                    'commission' => (max($category->commission, 0)).'%',
                    'action' => view('partials.actions', [
                        'modelName' => 'category',
                        'id' => $category->id,
                        'title' => $title,
                        'mode' => 'page_view',
                        'route' => route('admin.categories.edit', ['id' => $category->id]),
                        'editPermission' => $this->editPermission,
                        'deletePermission' => $this->deletePermission,
                    ])->render(),
                ];

                if ($this->getPanel() === 'admin') {
                    $row['products_count'] = $category->products_count;
                }

                if ($this->getPanel() === 'admin' && $this->deletePermission) {
                    $row = ['select' => '<input type="checkbox" class="form-check-input bulk-delete-row-checkbox" value="'.$category->id.'" data-title="'.e($title).'">'] + $row;
                }

                return $row;
            })
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    private function selectedIdsFromRequest(Request $request): array
    {
        $ids = $request->input('selected_ids', []);

        return collect(is_array($ids) ? $ids : [$ids])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function orderSelectedFirst($query, array $selectedIds, string $column)
    {
        $ids = implode(',', array_map('intval', $selectedIds));

        return $query->orderByRaw("CASE WHEN {$column} IN ({$ids}) THEN 0 ELSE 1 END");
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('search'); // Get the search query
        $exceptId = $request->input('exceptId'); // Get the search query
        $findId = $request->input('find_id'); // Specific category ID to find
        $type = $request->input('type'); // Specific category ID to find

        if ($findId) {
            // If find_id is set and not empty, fetch only that category
            $categories = Category::where('id', $findId)
                ->select('id', 'title')
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->get();
        } elseif (! empty($type) && $type == 'root') {
            $query = $request->input('q');
            $categories = Category::whereEnglishTextLike((string) $query)
                ->select('id', 'title')
                ->where('parent_id', null)
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->get();
        } else {
            // Fetch categories matching the search query
            $categories = Category::whereEnglishTextLike((string) $query)
                ->select('id', 'title') // Fetch only required fields
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->get();
        }

        $results = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'value' => $category->id,
                'text' => $category->getTranslation('title', 'en', false),
            ];
        });

        // Return the categories as JSON
        return response()->json($results);
    }

    public function searchLabels(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return response()->json([]);
        }

        $results = CategorySearchLabel::query()
            ->whereNotNull('search_labels')
            ->pluck('search_labels')
            ->flatten(1)
            ->filter(function ($label) use ($query) {
                return is_string($label) && stripos($label, $query) !== false;
            })
            ->map(function ($label) {
                return trim($label);
            })
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->map(function ($label) {
                return [
                    'value' => $label,
                    'text' => $label,
                ];
            });

        return response()->json($results);
    }

    /**
     * Show bulk upload page for categories.
     */
    public function bulkUploadPage(): View
    {
        $this->authorize('create', Category::class);
        $editPermission = $this->editPermission;
        $createPermission = $this->createPermission;

        // Reuse the panel-aware path
        return view($this->panelView('categories.bulk_upload'), compact('editPermission', 'createPermission'));
    }

    /**
     * Bulk upload categories via CSV.
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', Category::class);

            $request->validate([
                'csv-file' => 'required|file|mimes:csv,txt|max:10240', // up to 10MB
            ]);

            $file = $request->file('csv-file');
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                return ApiResponseType::sendJsonResponse(false, 'labels.file_open_error', [], 422);
            }

            $headers = fgetcsv($handle);
            if (! $headers) {
                fclose($handle);

                return ApiResponseType::sendJsonResponse(false, 'labels.csv_no_header', [], 422);
            }

            // Normalize headers to lowercase keys
            $normalizedHeaders = array_map(function ($h) {
                return strtolower(trim($h));
            }, $headers);

            $expected = [
                'title',
                'parent_id',
                'parent_title',
                'description',
                'status',
                'requires_approval',
                'commission',
                'background_type',
                'background_color',
                'font_color',
                'meta_title',
                'meta_keywords',
                'meta_description',
            ];

            // Ensure required header exists
            if (! in_array('title', $normalizedHeaders, true)) {
                fclose($handle);

                return ApiResponseType::sendJsonResponse(false, 'labels.csv_missing_title', [], 422);
            }

            $successCount = 0;
            $failed = [];
            $rowNumber = 1; // header is row 1

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                // Map row by header
                $data = [];
                foreach ($normalizedHeaders as $idx => $key) {
                    $data[$key] = $row[$idx] ?? null;
                }

                // Basic sanitization
                $title = trim((string) ($data['title'] ?? ''));
                if ($title === '') {
                    $failed[] = ['row' => $rowNumber, 'title' => $title, 'error' => __('labels.validation_failed').': '.__('validation.required', ['attribute' => 'title'])];

                    continue;
                }

                try {
                    DB::beginTransaction();

                    // Resolve parent
                    $parentId = null;
                    if (! empty($data['parent_id'])) {
                        $parentId = is_numeric($data['parent_id']) ? (int) $data['parent_id'] : null;
                        $parent = $parentId ? Category::where('id', $parentId)->first() : null;
                        if (! $parent) {
                            throw new \Exception(__('labels.invalid_parent'));
                        }
                        if (strcasecmp($parent->getTranslation('title', 'en', false), $title) === 0) {
                            throw new \Exception(__('labels.parent_cannot_be_self'));
                        }
                    } elseif (! empty($data['parent_title'])) {
                        $parentTitle = trim((string) $data['parent_title']);
                        if (strcasecmp($parentTitle, $title) === 0) {
                            throw new \Exception(__('labels.parent_cannot_be_self'));
                        }
                        $parent = Category::query()->whereEnglishTitle($parentTitle)->first();
                        if ($parent) {
                            $parentId = $parent->id;
                        } else {
                            throw new \Exception(__('labels.invalid_parent'));
                        }
                    }

                    // Map status
                    $statusRaw = strtolower(trim((string) ($data['status'] ?? '')));
                    $status = in_array($statusRaw, CategoryStatusEnum::values(), true) ? $statusRaw : CategoryStatusEnum::INACTIVE->value;

                    // Boolean requires_approval
                    $requiresApproval = filter_var($data['requires_approval'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    // Commission numeric 0-100
                    $commission = null;
                    if (($data['commission'] ?? '') !== '') {
                        $commission = (float) $data['commission'];
                        if ($commission < 0 || $commission > 100) {
                            throw new \Exception(__('labels.invalid_commission'));
                        }
                    }

                    // Background fields
                    $backgroundType = null;
                    $bgTypeRaw = strtolower(trim((string) ($data['background_type'] ?? '')));
                    if (in_array($bgTypeRaw, ['image', 'color'], true)) {
                        $backgroundType = $bgTypeRaw;
                    }
                    $backgroundColor = $data['background_color'] ?? null;
                    $fontColor = $data['font_color'] ?? null;

                    $categoryData = [
                        'parent_id' => $parentId,
                        'title' => ['en' => $title],
                        'description' => ! empty($data['description']) ? ['en' => $data['description']] : null,
                        'status' => $status,
                        'requires_approval' => $requiresApproval,
                        'commission' => $commission,
                        'background_type' => $backgroundType,
                        'background_color' => $backgroundColor,
                        'font_color' => $fontColor,
                    ];

                    // Ensure unique title
                    if (Category::withTrashed()->whereEnglishTitle($title)->exists()) {
                        throw new \Exception(__('labels.title_already_exists'));
                    }

                    $this->categoryService->createCategory($categoryData);

                    DB::commit();
                    $successCount++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $failed[] = [
                        'row' => $rowNumber,
                        'title' => $title,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            fclose($handle);

            $result = [
                'success_count' => $successCount,
                'failed_count' => count($failed),
                'failed_rows' => $failed,
            ];

            return ApiResponseType::sendJsonResponse(true, 'labels.bulk_upload_completed', $result, 200);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.validation_failed', $e->errors(), 422);
        }
    }

    /**
     * Download simple CSV template for bulk upload.
     */
    public function downloadTemplate()
    {
        $this->authorize('viewAny', Category::class);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="category_bulk_template.csv"',
        ];

        $columns = ['title', 'parent_id', 'parent_title', 'description', 'status', 'requires_approval', 'commission', 'background_type', 'background_color', 'font_color'];
        $sample = ['Fruits', '', '', 'Fresh fruits', 'active', 'false', '5', 'color', '#FFFFFF', '#000000'];

        $callback = function () use ($columns, $sample) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns);
            fputcsv($output, $sample);
            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
