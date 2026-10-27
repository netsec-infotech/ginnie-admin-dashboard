<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\Admin\BrandManagementResource;
use App\Models\Brand;
use App\Services\Ai\AiManager;
use App\Services\BrandService;
use App\Services\CatalogLanguageService;
use App\Services\Visibility\BrandVisibilityInspector;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BrandController extends Controller
{
    use ChecksPermissions, PanelAware, AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;
    public $sellerId;

    public function __construct(
        protected BrandService $brandService,
        protected AiManager $aiManager,
    )
    {
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;

        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::BRAND_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::BRAND_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::BRAND_CREATE());
        }
    }

    public function index(): View
    {
        $this->authorize('viewAny', Brand::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
            ['data' => 'scope_type', 'name' => 'scope_type', 'title' => __('labels.scope_type')],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        if ($this->getPanel() === 'admin' && $this->deletePermission) {
            array_unshift($columns, [
                'data' => 'select',
                'name' => 'select',
                'title' => '<input type="checkbox" id="select-all-brands" class="form-check-input bulk-delete-select-all">',
                'orderable' => false,
                'searchable' => false,
            ]);
        }
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $catalogLanguages = app(CatalogLanguageService::class)->active();
        $aiTranslationEnabled = (bool) ($this->aiManager->getAiSettings()['enableAiDescription'] ?? false);

        return view($this->panelView('brands.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission', 'catalogLanguages', 'aiTranslationEnabled'));
    }

    /**
     * Store a newly created resource in storage. Defaults + persistence +
     * logo attach all live in BrandService — see §3.12 of CLAUDE.md.
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Brand::class);
            $brand = $this->brandService->createBrand(
                $request->validated(),
                $request->file('logo'),
            );

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.brand_created_successfully', data: new BrandManagementResource($brand), status: 201);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.validation_failed', data: $e->errors());
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $brand = Brand::find($id);
            if (! $brand) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'labels.brand_not_found', data: [], status: 404);
            }
            $this->authorize('update', $brand);

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.brand_retrieved_successfully', data: new BrandManagementResource($brand));
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.brand_not_found', data: $e, status: 404);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: [], status: 403);
        }
    }

    /**
     * Return the visibility inspection for the brand — what gates it passes
     * and which zones it's reachable in. Used by the edit-modal panel that
     * answers "why isn't my brand showing?".
     */
    public function visibility($id, BrandVisibilityInspector $inspector, \App\Services\ZonePreviewService $zoneSvc): JsonResponse
    {
        try {
            $brand = Brand::find($id);
            if (! $brand) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.brand_not_found',
                    data: [],
                );
            }
            $this->authorize('viewAny', $brand);

            // Sellers see only the zones their own stores cover. Admins see all active zones.
            $zoneScope = null;
            if ($this->getPanel() === 'seller' && $this->sellerId) {
                $zoneScope = $zoneSvc->sellerZoneIds($this->sellerId);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.fetched_successfully',
                data: $inspector->inspect($brand, $zoneScope),
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('BrandController@visibility error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: [],
            );
        }
    }

    /**
     * Update the specified resource in storage. Defaults + persistence +
     * logo attach all live in BrandService — see §3.12 of CLAUDE.md.
     */
    public function update(UpdateBrandRequest $request, $id): JsonResponse
    {
        try {
            $brand = Brand::find($id);
            if (! $brand) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.brand_not_found',
                    data: []
                );
            }
            $this->authorize('update', $brand);

            $brand = $this->brandService->updateBrand(
                $brand,
                $request->validated(),
                $request->file('logo'),
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.brand_updated_successfully',
                data: new BrandManagementResource($brand)
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed',
                data: $e->errors()
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.brand_not_found',
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: []
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     * @throws AuthorizationException
     */
    public function destroy($id): JsonResponse
    {
        $brand = Brand::find($id);
        $this->authorize('delete', $brand);
        if (!$brand) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.brand_not_found',
                data: [],
                status: 404
            );
        }
        $brand->delete();
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.brand_deleted_successfully',
            data: []
        );
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['required', 'integer', 'distinct'],
            ]);

            $deletedCount = 0;
            $skipped = [];
            $failed = [];

            foreach ($validated['ids'] as $brandId) {
                $brand = Brand::find($brandId);

                if (! $brand) {
                    $skipped[] = [
                        'id' => (int) $brandId,
                        'reason' => __('labels.brand_not_found'),
                    ];

                    continue;
                }

                try {
                    $this->authorize('delete', $brand);
                    $brand->delete();
                    $deletedCount++;
                } catch (AuthorizationException) {
                    $skipped[] = [
                        'id' => (int) $brand->id,
                        'title' => $brand->getTranslation('title', 'en', false),
                        'reason' => __('labels.permission_denied'),
                    ];
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id' => (int) $brand->id,
                        'title' => $brand->getTranslation('title', 'en', false),
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

    public function getBrands(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';
        $selectedIds = $this->selectedIdsFromRequest($request);

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = $this->getPanel() === 'admin' && $this->deletePermission
            ? ['select', 'id', 'title', 'scope_type', 'image', 'status', 'created_at', 'action']
            : ['id', 'title', 'scope_type', 'image', 'status', 'created_at', 'action'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        if (in_array($orderColumn, ['select', 'image', 'action'], true)) {
            $orderColumn = 'id';
        }

        $query = Brand::query();

        $totalRecords = Brand::count();
        $filteredRecords = $totalRecords;
        // Search filter
        if (!empty($searchValue)) {
            $query->whereTitleLike($searchValue, 'en');
        }

        if (! empty($selectedIds) && ! empty($searchValue)) {
            // Keep previously selected rows visible at the top even after the user searches elsewhere.
            $query->orWhereIn('id', $selectedIds);
        }

        $filteredRecords = $query->count();

        $data = $query
            ->when(! empty($selectedIds), fn ($q) => $this->orderSelectedFirst($q, $selectedIds, 'brands.id'))
            ->when(
                $orderColumn === 'title',
                fn ($q) => $q->orderByTitle($orderDirection, 'en'),
                fn ($q) => $q->orderBy($orderColumn, $orderDirection),
            )
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($brand) {
                $englishTitle = $brand->getTranslation('title', 'en', false);
                $scopeDisplay = $brand->scope_type;
                if ($brand->scope_type === HomePageScopeEnum::CATEGORY() && $brand->scopeCategory) {
                    $scopeDisplay .= ' ('.$brand->scopeCategory->getTranslation('title', 'en', false).')';
                }
                $row = [
                    'id' => $brand->id,
                    'title' => $englishTitle,
                    'scope_type' => view('partials.status', [
                        'status' => $scopeDisplay,
                    ])->render(),
                    'image' => view('partials.image', ['image' => (!empty($brand->logo) ? $brand->logo : asset('assets/images/brand-placeholder.png')), 'title' => $englishTitle])->render(),
                    'status' => view('partials.status', ['status' => $brand->status ?? ""])->render(),
                    'created_at' => $brand->created_at->format('Y-m-d'),
                    'action' => view('partials.actions', ['modelName' => 'brand', 'id' => $brand->id, 'title' => $englishTitle, 'mode' => 'model_view', 'editPermission' => $this->editPermission, 'deletePermission' => $this->deletePermission])->render(),
                ];

                if ($this->getPanel() === 'admin' && $this->deletePermission) {
                    $row = ['select' => '<input type="checkbox" class="form-check-input bulk-delete-row-checkbox" value="'.$brand->id.'" data-title="'.e($englishTitle).'">'] + $row;
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
        $query = (string) $request->input('q', '');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');

        if ($findId) {
            $brands = Brand::where('id', $findId)
                ->where('status', BrandStatusEnum::ACTIVE())
                ->get();
        } else {
            $brands = Brand::whereTitleLike($query, 'en')
                ->where('status', BrandStatusEnum::ACTIVE())
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->get();
        }
        $results = $brands->map(function ($brand) {
            return [
                'id' => $brand->id,
                'value' => $brand->id,
                'text' => $brand->getTranslation('title', 'en', false),
            ];
        });
        // Return the categories as JSON
        return response()->json($results);
    }

}
