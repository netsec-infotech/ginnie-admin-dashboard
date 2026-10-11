<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\Seller;
use App\Services\BrandService;
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

    public function __construct(protected BrandService $brandService)
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
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;

        return view($this->panelView('brands.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission'));
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

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.brand_created_successfully', data: $brand, status: 201);
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
            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.brand_retrieved_successfully', data: new BrandResource($brand));
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.brand_not_found', data: $e);
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
                data: $brand
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

    public function getBrands(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'title', 'status', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = Brand::query();

        $totalRecords = Brand::count();
        $filteredRecords = $totalRecords;
        // Search filter
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%");
            });
            $filteredRecords = $query->count();
        }


        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($brand) {
                $scopeDisplay = $brand->scope_type;
                if ($brand->scope_type === HomePageScopeEnum::CATEGORY() && $brand->scopeCategory) {
                    $scopeDisplay .= ' (' . $brand->scopeCategory->title . ')';
                }
                return [
                    'id' => $brand->id,
                    'title' => $brand->title,
                    'scope_type' => view('partials.status', [
                        'status' => $scopeDisplay,
                    ])->render(),
                    'image' => view('partials.image', ['image' => (!empty($brand->logo) ? $brand->logo : asset('assets/images/brand-placeholder.png')), 'title' => $brand->title])->render(),
                    'status' => view('partials.status', ['status' => $brand->status ?? ""])->render(),
                    'created_at' => $brand->created_at->format('Y-m-d'),
                    'action' => view('partials.actions', ['modelName' => 'brand', 'id' => $brand->id, 'title' => $brand->title, 'mode' => 'model_view', 'editPermission' => $this->editPermission, 'deletePermission' => $this->deletePermission])->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q'); // Get the search query
        $exceptId = $request->input('exceptId'); // Get the search query
        $findId = $request->input('find_id'); // Specific brand ID to find

        if ($findId) {
            // If find_id is set and not empty, fetch only that brand
            $brands = Brand::where('id', $findId)
                ->where('status', BrandStatusEnum::ACTIVE())
                ->select('id', 'title')
                ->get();
        } else {
            // Fetch brands matching the search query
            $brands = Brand::where('title', 'LIKE', '%' . $query . '%')
                ->where('status', BrandStatusEnum::ACTIVE()) // Only active brands
                ->select('id', 'title') // Fetch only required fields
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->get();
        }
        $results = $brands->map(function ($brand) {
            return [
                'id' => $brand->id,
                'value' => $brand->id,
                'text' => $brand->title,
            ];
        });
        // Return the categories as JSON
        return response()->json($results);
    }

}
