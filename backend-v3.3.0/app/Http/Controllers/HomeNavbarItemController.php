<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Resources\HomeNavbarItemResource;
use App\Models\HomeLayout;
use App\Models\HomeNavbarItem;
use App\Support\HomeCache;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomeNavbarItemController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    private const MEDIA = [
        'app_icon' => SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON,
        'app_active_icon' => SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON,
        'app_background_image' => SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND,
        'app_tablet_background_image' => SpatieMediaCollectionName::HOME_NAVBAR_APP_TABLET_BACKGROUND,
        'desktop_icon' => SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ICON,
        'desktop_active_icon' => SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ACTIVE_ICON,
        'desktop_background_image' => SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND,
        'desktop_tablet_background_image' => SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_TABLET_BACKGROUND,
        'desktop_mobile_background_image' => SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND,
    ];

    /**
     * List all navbar categories for admin manager.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);
        $validated = $request->validate([
            'platform' => ['sometimes', 'in:app,web'],
        ]);
        $platform = $validated['platform'] ?? 'app';

        $items = HomeNavbarItem::query()
            ->with('media')
            ->forPlatform($platform)
            ->ordered($platform)
            ->get();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: HomeNavbarItemResource::collection($items)->resolve($request),
        );
    }

    /**
     * Quick-create a new custom navbar category without products.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureCanEdit();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'show_on_app' => ['nullable', 'boolean'],
            'show_on_web' => ['nullable', 'boolean'],
        ]);
        $this->ensurePlatformSelected(
            (bool) ($validated['show_on_app'] ?? true),
            (bool) ($validated['show_on_web'] ?? true),
        );

        $item = HomeNavbarItem::query()->create([
            'title' => trim($validated['title']),
            'slug' => $this->uniqueSlug($validated['title']),
            'sort_order' => (int) HomeNavbarItem::query()->max('sort_order') + 1,
            'app_sort_order' => (int) HomeNavbarItem::query()->max('app_sort_order') + 1,
            'web_sort_order' => (int) HomeNavbarItem::query()->max('web_sort_order') + 1,
            'is_active' => true,
            'show_on_app' => $validated['show_on_app'] ?? true,
            'show_on_web' => $validated['show_on_web'] ?? true,
            'home_appearance' => $this->defaultAppearance(),
            'search_labels' => [],
        ]);

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Navbar item created successfully',
            data: (new HomeNavbarItemResource($item->load('media')))->resolve($request),
        );
    }

    /**
     * Show custom navbar item details for editor.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);
        $homeNavbarItem = $id instanceof HomeNavbarItem ? $id : HomeNavbarItem::findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: (new HomeNavbarItemResource($homeNavbarItem->load('media')))->resolve($request),
        );
    }

    /**
     * Update custom navbar category appearance, media, platforms, and search labels.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $this->ensureCanEdit();
        $homeNavbarItem = $id instanceof HomeNavbarItem ? $id : HomeNavbarItem::findOrFail($id);
        $validated = $request->validate($this->updateRules($homeNavbarItem));
        $this->ensurePlatformSelected(
            (bool) $validated['show_on_app'],
            (bool) $validated['show_on_web'],
        );
        $this->ensureRequiredBackgroundMedia($request, $validated, $homeNavbarItem);

        DB::transaction(function () use ($request, $validated, $homeNavbarItem) {
            $homeNavbarItem->update([
                'title' => trim($validated['title']),
                'slug' => $validated['slug'],
                'is_active' => $validated['is_active'] ?? true,
                'show_on_app' => $validated['show_on_app'] ?? false,
                'show_on_web' => $validated['show_on_web'] ?? false,
                'home_appearance' => $validated['home_appearance'],
                'search_labels' => array_values(array_filter(
                    $validated['search_labels'] ?? [],
                    fn ($label) => is_string($label) && trim($label) !== '',
                )),
            ]);

            $this->clearBackgroundMediaForInactiveTypes($homeNavbarItem, $validated['home_appearance']);

            foreach (self::MEDIA as $field => $collection) {
                if ($request->hasFile($field)) {
                    $homeNavbarItem->clearMediaCollection($collection->value);
                    $homeNavbarItem->addMedia($request->file($field))->toMediaCollection($collection->value);
                } elseif ($request->boolean('remove_'.$field)) {
                    $homeNavbarItem->clearMediaCollection($collection->value);
                }
            }

            foreach ($validated['copy_media'] ?? [] as $target => $source) {
                if ($target === $source || ! isset(self::MEDIA[$target], self::MEDIA[$source])) {
                    continue;
                }
                $targetCollection = self::MEDIA[$target]->value;
                $sourceCollection = self::MEDIA[$source]->value;
                $sourceMedia = $homeNavbarItem->getFirstMedia($sourceCollection);
                $homeNavbarItem->clearMediaCollection($targetCollection);
                $sourceMedia?->copy($homeNavbarItem, $targetCollection);
            }
        });

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.updated_successfully'),
            data: (new HomeNavbarItemResource($homeNavbarItem->refresh()->load('media')))->resolve($request),
        );
    }

    /**
     * Persist drag-and-drop sort order per platform (app vs web).
     */
    public function order(Request $request): JsonResponse
    {
        $this->ensureCanEdit();
        $validated = $request->validate([
            'platform' => ['required', 'in:app,web'],
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer', 'distinct', Rule::exists('home_navbar_items', 'id')->whereNull('deleted_at')],
        ]);

        $platform = $validated['platform'];
        $sortColumn = HomeNavbarItem::sortColumn($platform);

        DB::transaction(function () use ($validated, $platform, $sortColumn) {
            $submittedIds = array_values($validated['item_ids']);
            $remainingIds = HomeNavbarItem::query()
                ->forPlatform($platform)
                ->whereNotIn('id', $submittedIds)
                ->ordered($platform)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            foreach (array_merge($submittedIds, $remainingIds) as $index => $id) {
                $values = [$sortColumn => $index + 1];
                if ($platform === 'app') {
                    $values['sort_order'] = $index + 1;
                }
                HomeNavbarItem::query()->whereKey($id)->update($values);
            }
        });

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.updated_successfully'),
            data: HomeNavbarItemResource::collection(
                HomeNavbarItem::query()
                    ->with('media')
                    ->forPlatform($platform)
                    ->ordered($platform)
                    ->get(),
            )->resolve($request),
        );
    }

    /**
     * Delete custom navbar category and its layout.
     */
    public function destroy($id): JsonResponse
    {
        $this->ensureCanEdit();
        $homeNavbarItem = $id instanceof HomeNavbarItem ? $id : HomeNavbarItem::findOrFail($id);
        $homeNavbarItem->delete();
        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Navbar item deleted successfully',
            data: [],
        );
    }

    private function updateRules(HomeNavbarItem $item): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('home_navbar_items', 'slug')->ignore($item->id)],
            'is_active' => ['required', 'boolean'],
            'show_on_app' => ['required', 'boolean'],
            'show_on_web' => ['required', 'boolean'],
            'search_labels' => ['nullable', 'array'],
            'search_labels.*' => ['nullable', 'string', 'max:191'],
            'home_appearance' => ['required', 'array'],
            'home_appearance.app' => ['required', 'array'],
            'home_appearance.desktop' => ['required', 'array'],
            'home_appearance.*.background_type' => ['required', Rule::in(['none', 'gradient', 'image', 'lottie'])],
            'home_appearance.*.gradient_start' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.gradient_end' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.gradient_angle' => ['nullable', 'integer', 'min:0', 'max:360'],
            'home_appearance.*.font_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.active_font_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'app_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'app_active_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'app_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'app_tablet_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'desktop_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'desktop_active_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'desktop_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'desktop_tablet_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'desktop_mobile_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'copy_media' => ['nullable', 'array'],
            'copy_media.*' => ['string', Rule::in(array_keys(self::MEDIA))],
        ];
    }

    private function defaultAppearance(): array
    {
        $style = [
            'background_type' => 'none',
            'gradient_start' => '#ffffff',
            'gradient_end' => '#ffffff',
            'gradient_angle' => 90,
            'font_color' => '#475569',
            'active_font_color' => '#111827',
        ];

        return ['app' => $style, 'desktop' => $style];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'navbar-item';
        $slug = $base;
        $suffix = 2;
        while (HomeNavbarItem::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function ensureCanEdit(): void
    {
        $user = auth()->user();
        if ($user && ($user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) || $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN->value) || $user->hasRole('super-admin'))) {
            return;
        }

        if (! $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_EDIT())
            && ! $this->hasPermission(AdminPermissionEnum::CATEGORY_DELETE())
            && ! $this->hasPermission(AdminPermissionEnum::CATEGORY_EDIT())
        ) {
            throw new AuthorizationException(__('labels.permission_denied'));
        }
    }

    private function ensurePlatformSelected(bool $showOnApp, bool $showOnWeb): void
    {
        if (! $showOnApp && ! $showOnWeb) {
            throw ValidationException::withMessages([
                'show_on_app' => 'Select app, web, or both for this navbar item.',
            ]);
        }
    }

    private function ensureRequiredBackgroundMedia(Request $request, array $validated, HomeNavbarItem $item): void
    {
        $appearance = $validated['home_appearance'];
        $requiresAppBackground = (bool) $validated['show_on_app']
            && in_array($appearance['app']['background_type'], ['image', 'lottie'], true);
        $requiresDesktopBackground = (bool) $validated['show_on_web']
            && in_array($appearance['desktop']['background_type'], ['image', 'lottie'], true);
        $copies = (array) ($validated['copy_media'] ?? []);

        $errors = [];
        if ($requiresAppBackground && ! $this->mediaWillExist($request, $item, 'app_background_image', $copies)) {
            $errors['app_background_image'] = 'The app mobile background is required for an image or Lottie header.';
        }
        if ($requiresDesktopBackground && ! $this->mediaWillExist($request, $item, 'desktop_background_image', $copies)) {
            $errors['desktop_background_image'] = 'The web desktop background is required for an image or Lottie header.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function mediaWillExist(
        Request $request,
        HomeNavbarItem $item,
        string $field,
        array $copies,
        array $visited = [],
    ): bool {
        if (in_array($field, $visited, true) || ! isset(self::MEDIA[$field])) {
            return false;
        }
        if ($request->hasFile($field)) {
            return true;
        }
        if (isset($copies[$field])) {
            return $this->mediaWillExist($request, $item, $copies[$field], $copies, [...$visited, $field]);
        }
        if ($request->boolean('remove_'.$field)) {
            return false;
        }

        return $item->hasMedia(self::MEDIA[$field]->value);
    }

    private function clearBackgroundMediaForInactiveTypes(HomeNavbarItem $item, array $appearance): void
    {
        if (! in_array($appearance['app']['background_type'] ?? 'none', ['image', 'lottie'], true)) {
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_TABLET_BACKGROUND());
        }

        if (! in_array($appearance['desktop']['background_type'] ?? 'none', ['image', 'lottie'], true)) {
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_TABLET_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND());
        }
    }
}
