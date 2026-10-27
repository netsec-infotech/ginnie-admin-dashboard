<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\StoryStyleEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\HomeBuilder\SaveHomeLayoutRequest;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\HomeLayout;
use App\Models\HomeLayoutRevision;
use App\Models\HomeNavbarItem;
use App\Models\HomeSavedPreset;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Product;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Services\HomeBuilderLegacyMigrationService;
use App\Services\HomeBuilderOptions;
use App\Services\HomeBuilderService;
use App\Services\HomeBuilderTemplates;
use App\Services\HomeLayoutResolver;
use App\Services\SettingService;
use App\Support\DemoMode;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class HomeBuilderController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    private const SECTION_MEDIA = [
        'background' => SpatieMediaCollectionName::HOME_SECTION_BACKGROUND,
        'hero_media' => SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA,
        'hero_media_web_mobile' => SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA_WEB_MOBILE,
        'card_background' => SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND,
    ];

    public function __construct(
        private readonly HomeBuilderService $service,
        private readonly HomeBuilderOptions $options,
        private readonly HomeLayoutResolver $resolver,
        private readonly HomeBuilderTemplates $templates,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', HomeLayout::class);

        $columns = [
            ['data' => 'zone_details', 'name' => 'name', 'title' => 'Zone Details'],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status'],
            ['data' => 'app_layout', 'name' => 'app_layout', 'title' => 'App Storefront Layout', 'orderable' => false, 'searchable' => false],
            ['data' => 'web_layout', 'name' => 'web_layout', 'title' => 'Web Storefront Layout', 'orderable' => false, 'searchable' => false],
            ['data' => 'category_layouts', 'name' => 'category_layouts', 'title' => 'Category Sub-Layouts', 'orderable' => false, 'searchable' => false],
            ['data' => 'action', 'name' => 'action', 'title' => 'Actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];

        return view('admin.home-builder.index', [
            'scopeTypes' => HomePageScopeEnum::values(),
            'appOptions' => $this->options->uiOptions(HomePagePlatformEnum::APP()),
            'webOptions' => $this->options->uiOptions(HomePagePlatformEnum::WEB()),
            'appTemplates' => $this->templates->sectionTemplates(HomePagePlatformEnum::APP()),
            'webTemplates' => $this->templates->sectionTemplates(HomePagePlatformEnum::WEB()),
            'appThemes' => $this->templates->layoutThemes(HomePagePlatformEnum::APP()),
            'webThemes' => $this->templates->layoutThemes(HomePagePlatformEnum::WEB()),
            'categories' => (function () {
                $catalogCategories = Category::query()
                    ->whereNull('parent_id')
                    ->where('status', 'active')
                    ->where('is_home_category', true)
                    ->ordered()
                    ->get();
                $customItems = HomeNavbarItem::query()
                    ->where('is_active', true)
                    ->ordered()
                    ->get();

                return $customItems->concat($catalogCategories)->sortBy(fn($i) => $i->sort_order ?? 0)->values();
            })(),
            'parentCategories' => Category::query()
                ->whereNull('parent_id')
                ->where('is_home_category', true)
                ->ordered()
                ->get(),
            'allParentCategories' => Category::query()
                ->whereNull('parent_id')
                ->ordered()
                ->get(),
            'customNavbarItems' => HomeNavbarItem::query()
                ->ordered()
                ->get(),
            'homeGeneralSettings' => (function () {
                $resource = app(SettingService::class)->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS());

                return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
            })(),
            'labels' => $this->builderLabels(),
            'canEdit' => $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_EDIT()),
            'zoneSearch' => route('admin.delivery-zones.search'),
            'columns' => $columns,
        ]);
    }

    public function zonesDatatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $query = DeliveryZone::query()->withCount(['stores']);
        $totalRecords = DeliveryZone::count();

        // Search
        if ($request->has('search') && ! empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where('name', 'like', "%{$searchValue}%");
        }

        $filteredRecords = $query->count();

        // Ordering
        if ($request->has('order')) {
            $orderColumnIndex = $request->order[0]['column'] ?? 0;
            $orderDirection = $request->order[0]['dir'] ?? 'asc';
            $cols = ['name', 'status'];
            $orderColumn = $cols[$orderColumnIndex] ?? 'name';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        if ($request->has('start') && $request->has('length')) {
            $query->skip((int) $request->start)->take((int) $request->length);
        }

        $zones = $query->get();

        $data = $zones->map(function (DeliveryZone $z) {
            $appLayout = HomeLayout::where('zone_id', $z->id)->where('platform', 'app')->where('scope_type', 'zone')->has('sections')->withCount('sections')->first();
            $webLayout = HomeLayout::where('zone_id', $z->id)->where('platform', 'web')->where('scope_type', 'zone')->has('sections')->withCount('sections')->first();
            $catLayoutsCount = HomeLayout::where('zone_id', $z->id)->whereIn('scope_type', ['category', 'zone_category'])->count();

            $hasCustomApp = (bool) $appLayout;
            $hasCustomWeb = (bool) $webLayout;
            $appCount = $appLayout?->sections_count ?? 0;
            $webCount = $webLayout?->sections_count ?? 0;

            $zoneDetailsHtml = '<div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm bg-primary-lt rounded">
                    <i class="ti ti-map-pin fs-3"></i>
                </div>
                <div>
                    <div class="fw-bold text-body fs-4">' . e($z->name) . '</div>
                    <div class="text-muted fs-5">Zone #' . $z->id . ' • ' . $z->stores_count . ' Stores Attached</div>
                </div>
            </div>';

            $statusBadge = ($z->status === 'active' || $z->status === 1 || $z->status === '1')
                ? '<span class="badge bg-success-lt"><i class="ti ti-circle-check me-1"></i>Active Zone</span>'
                : '<span class="badge bg-secondary-lt"><i class="ti ti-circle-x me-1"></i>Inactive</span>';

            $appBadge = $hasCustomApp
                ? '<span class="badge bg-purple-lt fs-5"><i class="ti ti-device-mobile me-1"></i>Customized (' . $appCount . ' Sections)</span>'
                : '<span class="badge bg-secondary-lt fs-5"><i class="ti ti-world me-1"></i>Global Fallback</span>';

            $webBadge = $hasCustomWeb
                ? '<span class="badge bg-azure-lt fs-5"><i class="ti ti-device-desktop me-1"></i>Customized (' . $webCount . ' Sections)</span>'
                : '<span class="badge bg-secondary-lt fs-5"><i class="ti ti-world me-1"></i>Global Fallback</span>';

            $catBadge = '<span class="badge bg-info-lt fs-5"><i class="ti ti-category me-1"></i>' . $catLayoutsCount . ' Custom</span>';

            $actionsHtml = '<div class="btn-list justify-content-end flex-nowrap">
                <button type="button" class="btn btn-sm btn-outline-primary" data-action="edit-zone-app" data-zone-id="' . $z->id . '" data-zone-name="' . e($z->name) . '" title="Edit App Layout">
                    <i class="ti ti-device-mobile me-1"></i>Edit App
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" data-action="edit-zone-web" data-zone-id="' . $z->id . '" data-zone-name="' . e($z->name) . '" title="Edit Web Layout">
                    <i class="ti ti-device-desktop me-1"></i>Edit Web
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary btn-icon" data-bs-toggle="dropdown" aria-expanded="false" title="More Zone Actions">
                        <i class="ti ti-dots-vertical fs-3"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="#" class="dropdown-item" data-action="copy-global-app" data-zone-id="' . $z->id . '">
                            <i class="ti ti-copy me-2 text-primary"></i>Copy Global Layout to App
                        </a>
                        <a href="#" class="dropdown-item" data-action="copy-global-web" data-zone-id="' . $z->id . '">
                            <i class="ti ti-copy me-2 text-info"></i>Copy Global Layout to Web
                        </a>';
            if ($hasCustomApp || $hasCustomWeb) {
                $actionsHtml .= '<div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item text-danger" data-action="reset-zone" data-zone-id="' . $z->id . '" data-zone-name="' . e($z->name) . '">
                            <i class="ti ti-rotate-clockwise me-2"></i>Reset Zone Layout (Use Global)
                        </a>';
            }
            $actionsHtml .= '</div></div></div>';

            return [
                'id' => $z->id,
                'zone_details' => $zoneDetailsHtml,
                'status' => $statusBadge,
                'app_layout' => $appBadge,
                'web_layout' => $webBadge,
                'category_layouts' => $catBadge,
                'action' => $actionsHtml,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function copyGlobalToZone(Request $request): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $request->validate([
            'zone_id' => ['required', 'integer', Rule::exists('delivery_zones', 'id')],
            'platform' => ['required', new Enum(HomePagePlatformEnum::class)],
        ]);

        $platform = $request->input('platform');
        $zoneId = (int) $request->input('zone_id');

        $globalLayout = $this->service->loadForScope(HomePageScopeEnum::GLOBAL(), null, $platform);
        $targetLayout = $this->service->loadForScope(HomePageScopeEnum::ZONE(), null, $platform, $zoneId);

        // Delete target layout existing sections
        $targetLayout->sections()->delete();

        // Duplicate sections and items from global layout
        foreach ($globalLayout->sections as $sec) {
            $newSec = $targetLayout->sections()->create([
                'type' => $sec->type,
                'title' => $sec->title,
                'style' => $sec->style,
                'config' => $sec->config,
                'sort_order' => $sec->sort_order,
                'is_active' => $sec->is_active,
                'start_at' => $sec->start_at,
                'end_at' => $sec->end_at,
            ]);

            foreach ($sec->items as $it) {
                $newSec->items()->create([
                    'purpose' => $it->purpose,
                    'itemable_type' => $it->itemable_type,
                    'itemable_id' => $it->itemable_id,
                    'title' => $it->title,
                    'link_url' => $it->link_url,
                    'config' => $it->config,
                    'sort_order' => $it->sort_order,
                ]);
            }
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Global layout copied successfully to zone',
            data: $this->presentLayout($targetLayout->fresh()),
        );
    }

    public function resetZoneLayout(Request $request): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $request->validate([
            'zone_id' => ['required', 'integer', Rule::exists('delivery_zones', 'id')],
            'platform' => ['required', new Enum(HomePagePlatformEnum::class)],
        ]);

        $zoneId = (int) $request->input('zone_id');
        $platform = $request->input('platform');

        HomeLayout::where('zone_id', $zoneId)
            ->where('platform', $platform)
            ->where('scope_type', 'zone')
            ->delete();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Zone layout reset to global fallback successfully',
        );
    }

    public function legacyMigrationStatus(HomeBuilderLegacyMigrationService $migrationService): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $migrationService->status(),
        );
    }

    public function homeBuilderBannerMigrationStatus(HomeBuilderLegacyMigrationService $migrationService): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $migrationService->homeBuilderBannerStatus(),
        );
    }

    public function migrateLegacy(HomeBuilderLegacyMigrationService $migrationService): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Legacy featured sections and banners migrated successfully.',
            data: $migrationService->migrate(),
        );
    }

    public function migrateHomeBuilderBannersOnly(HomeBuilderLegacyMigrationService $migrationService): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Existing Home Builder banner sections converted successfully.',
            data: $migrationService->migrateHomeBuilderBannersOnly(),
        );
    }

    private function builderLabels(): array
    {
        return [
            'scope' => __('labels.hb_scope') !== 'labels.hb_scope' ? __('labels.hb_scope') : 'Scope',
            'global' => __('labels.hb_global') !== 'labels.hb_global' ? __('labels.hb_global') : 'Global (Default)',
            'byCategory' => __('labels.hb_by_category') !== 'labels.hb_by_category' ? __('labels.hb_by_category') : 'Category',
            'selectCategory' => __('labels.hb_select_category') !== 'labels.hb_select_category' ? __('labels.hb_select_category') : 'Select Category',
            'sections' => __('labels.hb_sections') !== 'labels.hb_sections' ? __('labels.hb_sections') : 'Sections',
            'addSection' => __('labels.hb_add_section') !== 'labels.hb_add_section' ? __('labels.hb_add_section') : 'Add Section',
            'selectSection' => __('labels.hb_select_section') !== 'labels.hb_select_section' ? __('labels.hb_select_section') : 'Choose a section to configure',
            'save' => __('labels.hb_save') !== 'labels.hb_save' ? __('labels.hb_save') : 'Save Layout',
            'saved' => __('labels.home_layout_saved_successfully') !== 'labels.home_layout_saved_successfully' ? __('labels.home_layout_saved_successfully') : 'Home layout saved successfully.',
            'advanced' => __('labels.hb_advanced') !== 'labels.hb_advanced' ? __('labels.hb_advanced') : 'Advanced / Media',
            'markets' => __('labels.hb_zones') !== 'labels.hb_zones' ? __('labels.hb_zones') : 'Delivery Zones',
            'allMarkets' => __('labels.hb_all_zones') !== 'labels.hb_all_zones' ? __('labels.hb_all_zones') : 'All Zones',
            'addItem' => __('labels.hb_add_item') !== 'labels.hb_add_item' ? __('labels.hb_add_item') : 'Add Item',
            'removeConfirm' => __('labels.hb_remove_section_confirm') !== 'labels.hb_remove_section_confirm' ? __('labels.hb_remove_section_confirm') : 'Are you sure you want to remove this section?',
            'saveFirst' => __('labels.hb_save_first_upload') !== 'labels.hb_save_first_upload' ? __('labels.hb_save_first_upload') : 'Save section first to enable uploads',
            'upload' => __('labels.hb_upload') !== 'labels.hb_upload' ? __('labels.hb_upload') : 'Upload',
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $request->validate([
            'platform' => ['nullable', new Enum(HomePagePlatformEnum::class)],
            'scope_type' => ['nullable', new Enum(HomePageScopeEnum::class)],
            'scope_id' => ['nullable', 'integer'],
            'zone_id' => ['nullable', 'integer'],
        ]);

        $layout = $this->service->loadForScope(
            $request->input('scope_type', HomePageScopeEnum::GLOBAL()),
            $request->filled('scope_id') ? (int) $request->input('scope_id') : null,
            $request->input('platform', HomePagePlatformEnum::APP()),
            $request->filled('zone_id') ? (int) $request->input('zone_id') : null,
        );

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $this->presentLayout($layout),
        );
    }

    public function save(SaveHomeLayoutRequest $request): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $layout = $this->service->save($request->validated());
        $this->attachMedia($request, $layout);
        $layout = $this->service->loadForScope($layout->scope_type, $layout->scope_id, $layout->platform, $layout->zone_id);

        // Auto-create layout revision snapshot
        $layout->revisions()->create([
            'name' => 'Saved Layout Snapshot',
            'payload' => $this->presentLayout($layout),
            'created_by' => $request->user()?->id,
        ]);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_saved_successfully',
            data: $this->presentLayout($layout),
        );
    }

    public function revisions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $layout = $this->service->loadForScope(
            $request->input('scope_type', HomePageScopeEnum::GLOBAL()),
            $request->filled('scope_id') ? (int) $request->input('scope_id') : null,
            $request->input('platform', HomePagePlatformEnum::APP()),
            $request->filled('zone_id') ? (int) $request->input('zone_id') : null,
        );

        $revisions = $layout->revisions()
            ->with('creator:id,name')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name ?: 'Auto-save Snapshot',
                'creator_name' => $r->creator?->name ?: 'System Admin',
                'created_at' => $r->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $r->created_at->diffForHumans(),
                'payload' => $r->payload,
            ]);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Revisions retrieved successfully',
            data: $revisions,
        );
    }

    public function restoreRevision(HomeLayoutRevision $revision): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Revision snapshot restored successfully',
            data: $revision->payload,
        );
    }

    private function attachMedia(Request $request, HomeLayout $layout): void
    {
        $sections = $layout->sections->values();

        foreach ((array) $request->file('media', []) as $i => $kinds) {
            $section = $sections[$i] ?? null;
            if (! $section) {
                continue;
            }
            foreach ((array) $kinds as $kind => $file) {
                $collection = self::SECTION_MEDIA[$kind] ?? null;
                if (! $collection || ! $file) {
                    continue;
                }
                $section->clearMediaCollection($collection->value);
                $section->addMedia($file)->toMediaCollection($collection->value);
            }
        }

        foreach ($sections as $i => $section) {
            $this->attachRowBackgrounds($request, $section, (int) $i);
        }

        foreach ((array) $request->file('item_media', []) as $i => $items) {
            $section = $sections[$i] ?? null;
            if (! $section) {
                continue;
            }
            $sectionItems = $section->items->values();
            foreach ((array) $items as $j => $file) {
                $item = $sectionItems[$j] ?? null;
                if (! $item || ! $file) {
                    continue;
                }
                $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                $item->addMedia($file)->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
            }
        }

        foreach ((array) $request->file('item_media_mobile', []) as $i => $items) {
            $section = $sections[$i] ?? null;
            if (! $section) {
                continue;
            }
            $sectionItems = $section->items->values();
            foreach ((array) $items as $j => $file) {
                $item = $sectionItems[$j] ?? null;
                if (! $item || ! $file) {
                    continue;
                }
                $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE_WEB_MOBILE());
                $item->addMedia($file)->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE_WEB_MOBILE());
            }
        }

        foreach ($sections as $i => $section) {
            $sectionItems = $section->items->values();
            foreach ($sectionItems as $j => $item) {
                if ($request->boolean("remove_item_media.$i.$j")) {
                    $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                    $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE_WEB_MOBILE());
                }
            }
        }
    }

    private function attachRowBackgrounds(Request $request, HomeSection $section, int $sectionIndex): void
    {
        if ($section->type !== HomeSectionTypeEnum::PLAY_IMAGE()) {
            return;
        }

        $collection = SpatieMediaCollectionName::HOME_SECTION_ROW_BACKGROUND();
        $config = $section->config ?? [];
        $rows = array_values((array) ($config['rows'] ?? []));
        $activeKeys = [];

        foreach ($rows as $rowIndex => &$row) {
            $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($row['media_key'] ?? ''));
            if ($key === '') {
                $key = 'row_' . $section->id . '_' . $rowIndex;
            }

            $row['media_key'] = $key;
            $activeKeys[] = $key;
            $media = $section->getMedia($collection)->first(
                fn($item) => $item->getCustomProperty('row_key') === $key
            );
            $file = $request->file("row_media.$sectionIndex.$rowIndex");

            if (($row['background_type'] ?? 'none') !== 'image') {
                $media?->delete();
                $row['background_image'] = null;

                continue;
            }

            if ($file) {
                $media?->delete();
                $media = $section->addMedia($file)
                    ->withCustomProperties(['row_key' => $key])
                    ->toMediaCollection($collection);
            }

            $row['background_image'] = $media?->getFullUrl() ?: ($row['background_image'] ?? null);
        }
        unset($row);

        $section->getMedia($collection)
            ->reject(fn($media) => in_array($media->getCustomProperty('row_key'), $activeKeys, true))
            ->each->delete();

        $config['rows'] = $rows;
        $section->forceFill(['config' => $config])->save();
    }

    public function uploadSectionMedia(Request $request, HomeSection $section): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $validated = $request->validate([
            'collection' => ['required', Rule::in(array_keys(self::SECTION_MEDIA))],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $collection = self::SECTION_MEDIA[$validated['collection']];
        $section->clearMediaCollection($collection);
        $section->addMediaFromRequest('file')->toMediaCollection($collection);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_uploaded_successfully',
            data: ['url' => $section->refresh()->getFirstMediaUrl($collection) ?: null],
        );
    }

    public function deleteSectionMedia(Request $request, HomeSection $section): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $validated = $request->validate([
            'collection' => ['required', Rule::in(array_keys(self::SECTION_MEDIA))],
        ]);

        $section->clearMediaCollection(self::SECTION_MEDIA[$validated['collection']]);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_deleted_successfully',
        );
    }

    public function uploadItemImage(Request $request, HomeSectionItem $item): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $collection = SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE;
        $item->clearMediaCollection($collection);
        $item->addMediaFromRequest('file')->toMediaCollection($collection);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_uploaded_successfully',
            data: ['url' => $item->refresh()->image],
        );
    }

    public function deleteItemImage(HomeSectionItem $item): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_deleted_successfully',
        );
    }

    public function globalAppearanceShow(SettingService $settingService): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $resource = $settingService->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS());
        $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

        $catalogCategories = Category::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->where('is_home_category', true)
            ->ordered()
            ->get();

        $customItems = HomeNavbarItem::query()
            ->where('is_active', true)
            ->ordered()
            ->get();

        $categories = $customItems->concat($catalogCategories)
            ->sortBy(fn($item) => $item->sort_order ?? 0)
            ->values()
            ->map(function ($cat) {
                $bgType = is_object($cat->background_type) ? $cat->background_type->value : ($cat->background_type ?? 'none');

                return [
                    'id' => $cat->id,
                    'title' => $cat instanceof Category ? $cat->getTranslation('title', 'en', false) : $cat->title,
                    'icon' => $cat->icon,
                    'active_icon' => $cat->active_icon ?: $cat->icon,
                    'background_type' => $bgType,
                    'background_color' => $cat->background_color ?? ($cat->background_color_web ?? ''),
                    'background_color_web' => $cat->background_color_web,
                    'background_color_app' => $cat->background_color_app,
                    'background_image' => $cat->background_image ?? ($cat->background_image_web ?? ''),
                    'background_image_web' => $cat->background_image_web,
                    'background_image_web_mobile' => $cat->background_image_web_mobile,
                    'background_image_app' => $cat->background_image_app,
                    'background_animation' => $cat->background_animation ?? ($cat->background_animation_web ?? ''),
                    'background_animation_web' => $cat->background_animation_web,
                    'background_animation_app' => $cat->background_animation_app,
                    'font_color' => $cat->font_color,
                    'is_custom' => $cat instanceof HomeNavbarItem,
                ];
            });

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: array_merge($settings, [
                'settings' => $settings,
                'categories' => $categories,
            ]),
        );
    }

    public function globalAppearanceSave(Request $request, SettingService $settingService): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.demo_mode_message_placeholder'),
                status: 403
            );
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'searchLabels' => ['nullable', 'array'],
            'searchLabels.*' => ['nullable', 'string'],
            'backgroundType' => ['nullable', 'string', 'in:color,image,animation'],
            'backgroundColor' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'fontColor' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'activeIcon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'backgroundImage' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'backgroundAnimation' => ['nullable', 'file', 'mimetypes:application/json,text/plain,application/octet-stream', 'mimes:json,txt', 'max:8100'],
        ]);

        $existing = $settingService->getSettingValues(SettingTypeEnum::HOME_GENERAL_SETTINGS());

        $backgroundType = $validated['backgroundType'] ?? '';

        $value = [
            'title' => $validated['title'],
            'searchLabels' => array_values(array_filter((array) $request->input('searchLabels', []), fn($l) => is_string($l) && trim($l) !== '')),
            'backgroundType' => $backgroundType,
            'backgroundColor' => $validated['backgroundColor'] ?? '#ffffff',
            'fontColor' => $validated['fontColor'] ?? '#000000',
            'icon' => $existing['icon'] ?? '',
            'activeIcon' => $existing['activeIcon'] ?? '',
            'backgroundImage' => $existing['backgroundImage'] ?? '',
            'backgroundAnimation' => $existing['backgroundAnimation'] ?? '',
        ];

        foreach (['icon', 'activeIcon', 'backgroundImage', 'backgroundAnimation'] as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $value[$field] = $file->storeAs('settings', uniqid() . '-' . $file->getClientOriginalName(), 'public');
            } elseif ($request->boolean('remove_' . $field)) {
                $value[$field] = '';
            }
        }

        if ($backgroundType !== 'image') {
            $value['backgroundImage'] = '';
        }
        if ($backgroundType !== 'animation') {
            $value['backgroundAnimation'] = '';
        }

        $settingService->saveSetting(SettingTypeEnum::HOME_GENERAL_SETTINGS(), $value);

        $resource = $settingService->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS());

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.updated_successfully'),
            data: $resource ? ($resource->toArray(request())['value'] ?? []) : [],
        );
    }

    private function formatStoryForPreview(WatchBuyProfile $profile, int $statusCount = 1): array
    {
        $sellerStore = $profile->seller?->stores?->first();
        $avatar = $profile->photo_url
            ?: ($sellerStore?->store_logo
                ?: ($profile->getFirstMediaUrl(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO()) ?: null));
        $displayName = $sellerStore?->name
            ?: ($profile->username ?: 'Store');

        return [
            'id' => $profile->id,
            'handle' => $profile->username,
            'display_name' => $displayName,
            'avatar' => $avatar,
            'status_count' => $statusCount,
        ];
    }

    private function formatReelForPreview(WatchBuyReel $reel): array
    {
        $sellerStore = $reel->profile?->seller?->stores?->first();
        $thumb = $reel->cover_url
            ?: ($reel->getFirstMediaUrl(SpatieMediaCollectionName::WATCH_BUY_REEL_COVER()) ?: null);
        $storeName = $sellerStore?->name
            ?: ($reel->profile?->username ?: 'Official Store');

        return [
            'id' => $reel->id,
            'caption' => $reel->caption ?: 'Reel',
            'thumbnail' => $thumb,
            'like_count' => (int) $reel->like_count,
            'store_name' => $storeName,
        ];
    }

    private function resolvePreviewStoriesPool(?DeliveryZone $zone): array
    {
        $dummySection = new HomeSection([
            'type' => HomeSectionTypeEnum::STORIES(),
            'config' => ['limit' => 20],
        ]);

        $resolved = $this->resolver->resolveStories($dummySection, $zone);
        if ($resolved->isNotEmpty()) {
            return $resolved->map(fn($item) => $this->formatStoryForPreview($item['profile'], (int) $item['status_count']))->values()->all();
        }

        return [];
    }

    private function resolvePreviewReelsPool(?DeliveryZone $zone): array
    {
        $dummySection = new HomeSection([
            'type' => HomeSectionTypeEnum::REELS(),
            'config' => ['source' => 'trending', 'limit' => 20],
        ]);

        $resolved = $this->resolver->resolveReels($dummySection, $zone);
        if ($resolved->isNotEmpty()) {
            return $resolved->map(fn($r) => $this->formatReelForPreview($r))->values()->all();
        }

        return [];
    }

    private function presentLayout(HomeLayout $layout): array
    {
        $classToKey = array_flip(HomeBuilderService::ITEMABLE_MAP);
        $zone = $layout->zone_id ? DeliveryZone::find($layout->zone_id) : null;

        $sectionsCollection = $layout->sections;

        // If this is a zone layout, also merge active sections from Global Layout tagged for this zone
        if ($layout->scope_type === 'zone' && $layout->zone_id) {
            $globalZoneSections = HomeSection::query()
                ->whereHas('layout', fn($q) => $q->where('platform', $layout->platform)->where('scope_type', 'global'))
                ->whereHas('zones', fn($q) => $q->where('delivery_zones.id', $layout->zone_id))
                ->with(['media', 'items' => fn($q) => $q->orderBy('sort_order'), 'items.itemable', 'items.media', 'zones:id'])
                ->get();

            $existingIds = $sectionsCollection->pluck('id')->filter()->toArray();
            foreach ($globalZoneSections as $gSec) {
                if (! in_array($gSec->id, $existingIds, true)) {
                    $gSec->is_global_tagged = true;
                    $sectionsCollection->push($gSec);
                }
            }
        }

        $hasStoriesSection = $sectionsCollection->contains(fn(HomeSection $section) => $section->type === HomeSectionTypeEnum::STORIES());
        $hasReelsSection = $sectionsCollection->contains(fn(HomeSection $section) => $section->type === HomeSectionTypeEnum::REELS());
        $previewStoriesPool = $hasStoriesSection ? $this->resolvePreviewStoriesPool($zone) : [];
        $previewReelsPool = $hasReelsSection ? $this->resolvePreviewReelsPool($zone) : [];

        return [
            'id' => $layout->id,
            'platform' => $layout->platform,
            'scope_type' => $layout->scope_type,
            'scope_id' => $layout->scope_id,
            'zone_id' => $layout->zone_id,
            'is_active' => (bool) $layout->is_active,
            'preview_stories' => $previewStoriesPool,
            'preview_reels' => $previewReelsPool,
            'sections' => $sectionsCollection->map(function (HomeSection $section) use ($classToKey, $zone, $previewStoriesPool, $previewReelsPool) {
                $resolvedProducts = [];
                $resolvedCategories = [];
                $resolvedBrands = [];
                $resolvedStories = [];
                $resolvedReels = [];

                if ($section->type === HomeSectionTypeEnum::PRODUCTS()) {
                    $productsCol = $this->resolver->resolveProducts($section, null);
                    if ($productsCol->isEmpty()) {
                        $parentCatIds = $section->items->where('purpose', 'filter')->pluck('itemable_id')->filter()->values()->all();
                        $q = Product::query();
                        if (! empty($parentCatIds)) {
                            $catIds = Category::whereIn('parent_id', $parentCatIds)->pluck('id')->merge($parentCatIds)->unique()->all();
                            $q->whereIn('category_id', $catIds);
                        }
                        $productsCol = $q->latest()->take(6)->get();
                    }

                    $resolvedProducts = $productsCol->map(function ($p) {
                        $hasSpecial = $p->special_price > 0 && $p->special_price < $p->price;
                        $discountPercent = $hasSpecial ? round((($p->price - $p->special_price) / $p->price) * 100) : 0;

                        return [
                            'id' => $p->id,
                            'title' => $p->title,
                            'image' => $p->main_image,
                            'price' => $hasSpecial ? $p->special_price : $p->price,
                            'original_price' => $hasSpecial ? $p->price : null,
                            'discount_percent' => $discountPercent,
                        ];
                    })->values()->all();
                } elseif ($section->type === HomeSectionTypeEnum::CATEGORIES()) {
                    if ($section->style === 'custom_image') {
                        $itemsByCatId = $section->items
                            ->filter(fn($it) => $it->purpose === 'display')
                            ->keyBy('itemable_id');

                        $resolvedCategories = $this->resolver->resolveCategories($section)->map(function ($c) use ($itemsByCatId) {
                            $item = $itemsByCatId->get($c->id);

                            return [
                                'id' => $c->id,
                                'title' => $c->getTranslation('title', 'en', false),
                                'image' => $item?->image ?: ($c->image ?: ($c->icon ?: null)),
                            ];
                        })->values()->all();
                    } else {
                        $resolvedCategories = $this->resolver->resolveCategories($section)->map(fn($c) => [
                            'id' => $c->id,
                            'title' => $c->getTranslation('title', 'en', false),
                            'image' => $c->image ?: ($c->icon ?: null),
                        ])->values()->all();
                    }
                } elseif ($section->type === HomeSectionTypeEnum::BRANDS()) {
                    if ($section->style === 'custom_image') {
                        $itemsByBrandId = $section->items
                            ->filter(fn($it) => $it->purpose === 'display')
                            ->keyBy('itemable_id');

                        $resolvedBrands = $this->resolver->resolveBrands($section)->map(function ($b) use ($itemsByBrandId) {
                            $item = $itemsByBrandId->get($b->id);

                            return [
                                'id' => $b->id,
                                'title' => $b->title,
                                'image' => $item?->image ?: ($b->logo ?: null),
                            ];
                        })->values()->all();
                    } else {
                        $resolvedBrands = $this->resolver->resolveBrands($section)->map(fn($b) => [
                            'id' => $b->id,
                            'title' => $b->title,
                            'image' => $b->logo,
                        ])->values()->all();
                    }
                } elseif ($section->type === HomeSectionTypeEnum::STORIES()) {
                    $storiesCol = $this->resolver->resolveStories($section, $zone);
                    if ($storiesCol->isNotEmpty()) {
                        $resolvedStories = $storiesCol->map(fn($item) => $this->formatStoryForPreview($item['profile'], (int) $item['status_count']))->values()->all();
                    } else {
                        $resolvedStories = $previewStoriesPool;
                    }
                } elseif ($section->type === HomeSectionTypeEnum::REELS()) {
                    $reelsCol = $this->resolver->resolveReels($section, $zone);
                    if ($reelsCol->isNotEmpty()) {
                        $resolvedReels = $reelsCol->map(fn($r) => $this->formatReelForPreview($r))->values()->all();
                    } else {
                        $resolvedReels = $previewReelsPool;
                    }
                }

                $sectionConfig = (empty($section->config) || ! is_array($section->config)) ? [] : $section->config;
                if ($section->type === HomeSectionTypeEnum::REELS()) {
                    unset($sectionConfig['show_views']);
                    if (array_key_exists('show_title', $sectionConfig)) {
                        $sectionConfig['show_title'] = filter_var($sectionConfig['show_title'], FILTER_VALIDATE_BOOLEAN);
                    }
                    if (array_key_exists('show_likes', $sectionConfig)) {
                        $sectionConfig['show_likes'] = filter_var($sectionConfig['show_likes'], FILTER_VALIDATE_BOOLEAN);
                    }
                } elseif ($section->type === HomeSectionTypeEnum::STORIES()) {
                    if (array_key_exists('show_profile_name', $sectionConfig)) {
                        $sectionConfig['show_profile_name'] = filter_var($sectionConfig['show_profile_name'], FILTER_VALIDATE_BOOLEAN);
                    }
                } elseif ($section->type === HomeSectionTypeEnum::PLAY_IMAGE()) {
                    if (isset($sectionConfig['rows']) && is_array($sectionConfig['rows'])) {
                        foreach ($sectionConfig['rows'] as &$row) {
                            if (is_array($row)) {
                                if (array_key_exists('show_scroll_buttons', $row)) {
                                    $row['show_scroll_buttons'] = filter_var($row['show_scroll_buttons'], FILTER_VALIDATE_BOOLEAN);
                                }
                                if (array_key_exists('show_bullets', $row)) {
                                    $row['show_bullets'] = filter_var($row['show_bullets'], FILTER_VALIDATE_BOOLEAN);
                                }
                                if (array_key_exists('auto_scroll', $row)) {
                                    $row['auto_scroll'] = filter_var($row['auto_scroll'], FILTER_VALIDATE_BOOLEAN);
                                }
                            }
                        }
                        unset($row);
                    }
                }

                return [
                    'id' => $section->id,
                    'type' => $section->type,
                    'title' => $section->title,
                    'style' => $section->type === HomeSectionTypeEnum::STORIES() ? StoryStyleEnum::AVATAR_CIRCLE() : $section->style,
                    'config' => $sectionConfig === [] ? (object) [] : $sectionConfig,
                    'sort_order' => $section->sort_order,
                    'is_active' => (bool) $section->is_active,
                    'is_global_tagged' => (bool) ($section->is_global_tagged ?? false),
                    'start_at' => $section->start_at?->format('Y-m-d H:i:s'),
                    'end_at' => $section->end_at?->format('Y-m-d H:i:s'),
                    'background_image' => $section->background_image,
                    'hero_media' => $section->hero_media,
                    'hero_media_web_mobile' => $section->hero_media_web_mobile,
                    'card_background_image' => $section->card_background_image,
                    'zone_ids' => $section->zones->pluck('id')->all(),
                    'resolved_products' => $resolvedProducts,
                    'resolved_categories' => $resolvedCategories,
                    'resolved_brands' => $resolvedBrands,
                    'resolved_stories' => $resolvedStories,
                    'resolved_reels' => $resolvedReels,
                    'items' => $section->items->map(fn(HomeSectionItem $item) => [
                        'id' => $item->id,
                        'purpose' => $item->purpose,
                        'itemable_type' => $item->itemable_type ? ($classToKey[$item->itemable_type] ?? null) : null,
                        'itemable_id' => $item->itemable_id,
                        'itemable_label' => $item->itemable?->title ?? $item->itemable?->name,
                        'title' => $item->title,
                        'link_url' => $item->link_url,
                        'image' => $item->image ?: ($item->itemable?->main_image ?? $item->itemable?->image ?? $item->itemable?->icon ?? null),
                        'image_web_mobile' => $item->image_web_mobile,
                        'price' => $item->itemable?->price ?? $item->itemable?->special_price ?? null,
                        'sort_order' => $item->sort_order,
                        'config' => $item->config ?? [],
                    ])->all(),
                ];
            })->all(),
        ];
    }

    public function savedPresetsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $presets = HomeSavedPreset::query()
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $presets,
        ]);
    }

    public function savePreset(Request $request): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => __('labels.demo_mode_message_placeholder'),
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|string|max:100',
            'style' => 'nullable|string|max:100',
            'config' => 'nullable|array',
            'items' => 'nullable|array',
        ]);

        $preset = HomeSavedPreset::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'style' => $validated['style'] ?? null,
            'config' => $validated['config'] ?? [],
            'items' => $validated['items'] ?? [],
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preset saved successfully!',
            'data' => $preset,
        ]);
    }

    public function deletePreset(HomeSavedPreset $preset): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        if (DemoMode::isDemoUser(auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => __('labels.demo_mode_message_placeholder'),
            ], 403);
        }

        $preset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Saved preset deleted successfully!',
        ]);
    }
}
