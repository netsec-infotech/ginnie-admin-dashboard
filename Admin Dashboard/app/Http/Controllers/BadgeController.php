<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Http\Requests\Badge\BulkAssignBadgeRequest;
use App\Http\Requests\Badge\StoreUpdateBadgeRequest;
use App\Http\Resources\BadgeResource;
use App\Models\Badge;
use App\Services\BadgeService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BadgeController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $viewPermission = false;

    protected bool $createPermission = false;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    public function __construct(protected BadgeService $badgeService)
    {
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::BADGE_VIEW());
        $this->createPermission = $this->hasPermission(AdminPermissionEnum::BADGE_CREATE());
        $this->editPermission = $this->hasPermission(AdminPermissionEnum::BADGE_EDIT());
        $this->deletePermission = $this->hasPermission(AdminPermissionEnum::BADGE_DELETE());
    }

    /**
     * Display the badge library listing.
     */
    public function index(): View
    {
        abort_unless($this->viewPermission, 403);

        $columns = [
            ['data' => 'id',      'name' => 'id',      'title' => __('labels.id')],
            ['data' => 'preview', 'name' => 'preview', 'title' => __('labels.badge_preview'), 'orderable' => false, 'searchable' => false],
            ['data' => 'name',    'name' => 'name',    'title' => __('labels.badge_name')],
            ['data' => 'label',   'name' => 'label',   'title' => __('labels.badge_label')],
            ['data' => 'products_count', 'name' => 'products_count', 'title' => __('labels.products'), 'orderable' => false, 'searchable' => false],
            ['data' => 'action',  'name' => 'action',  'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.badges.index', [
            'columns' => $columns,
            'createPermission' => $this->createPermission,
            'editPermission' => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ]);
    }

    /**
     * DataTable JSON endpoint for the badge listing.
     */
    public function getList(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403);

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? '';
        $orderColumnIndex = (int) data_get($request->get('order'), '0.column', 0);
        $orderDirection = strtolower((string) data_get($request->get('order'), '0.dir', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $columns = [
            0 => 'id',
            2 => 'name',
            3 => 'label',
            4 => 'products_count', // added
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = Badge::withCount('products');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        $total = Badge::count();
        $filtered = $query->count();
        // FIXED: use dynamic sorting instead of hardcoded id desc
        $badges = $query->orderBy($orderColumn, $orderDirection)->skip($start)->take($length)->get();

        $data = $badges->map(function (Badge $badge) {
            $previewStyle = 'background-color:'.e($badge->bg_color).';color:'.e($badge->text_color).';'
                .($badge->border_color ? 'border:1px solid '.e($badge->border_color).';' : '');

            $preview = '<span class="badge" style="'.$previewStyle.'padding:4px 10px;border-radius:4px;font-size:0.75rem;">'
                .e($badge->label).'</span>';

            $actions = '';
            if ($this->editPermission) {
                $actions .= '<a href="javascript:void(0);"
                    class="btn btn-outline-blue me-2 p-1"
                    data-bs-toggle="modal"
                    data-bs-target="#badge-modal"
                    data-id="'.$badge->id.'"
                    data-name="'.e($badge->name).'"
                    data-label="'.e($badge->label).'"
                    data-bg-color="'.e($badge->bg_color).'"
                    data-text-color="'.e($badge->text_color).'"
                    data-border-color="'.e($badge->border_color ?? '').'"
                    title="'.__('labels.edit').'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon m-0">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/><path d="M16 5l3 3"/>
                    </svg>
                </a>';
            }
            if ($this->deletePermission) {
                $actions .= '<a href="javascript:void(0);" class="btn btn-outline-danger p-1 delete-badge"
                    data-id="'.$badge->id.'"
                    data-title="'.e($badge->name).'"
                    title="'.__('labels.delete').'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon m-0">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
                    </svg>
                </a>';
            }

            return [
                'id' => $badge->id,
                'preview' => $preview,
                'name' => e($badge->name),
                'label' => e($badge->label),
                'products_count' => $badge->products_count,
                'action' => '<div class="d-flex align-items-center">'.$actions.'</div>',
            ];
        })->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created badge.
     */
    public function store(StoreUpdateBadgeRequest $request): JsonResponse
    {
        try {
            abort_unless($this->createPermission, 403);

            $badge = $this->badgeService->create($request->validated());

            return ApiResponseType::sendJsonResponse(true, 'labels.badge_created_successfully', new BadgeResource($badge));
        } catch (\Exception $e) {
            Log::error('Badge store failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Update an existing badge.
     */
    public function update(StoreUpdateBadgeRequest $request, int $id): JsonResponse
    {
        try {
            abort_unless($this->editPermission, 403);

            $badge = Badge::findOrFail($id);
            $badge = $this->badgeService->update($badge, $request->validated());

            return ApiResponseType::sendJsonResponse(true, 'labels.badge_updated_successfully', new BadgeResource($badge));
        } catch (\Exception $e) {
            Log::error('Badge update failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Delete a badge.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            abort_unless($this->deletePermission, 403);

            $badge = Badge::findOrFail($id);
            $this->badgeService->delete($badge);

            return ApiResponseType::sendJsonResponse(true, 'labels.badge_deleted_successfully', []);
        } catch (\Exception $e) {
            Log::error('Badge delete failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Bulk assign a badge to selected products.
     * If badge_id is absent or falsy, treats the call as a bulk remove.
     */
    public function bulkAssign(BulkAssignBadgeRequest $request): JsonResponse
    {
        try {
            abort_unless($this->editPermission, 403);

            $data = $request->validated();
            $badgeId = ! empty($data['badge_id']) ? (int) $data['badge_id'] : null;

            if ($badgeId === null) {
                $count = $this->badgeService->bulkRemove($data['product_ids']);

                return ApiResponseType::sendJsonResponse(true, 'labels.badge_bulk_removed_successfully', ['count' => $count]);
            }

            $count = $this->badgeService->bulkAssign($data['product_ids'], $badgeId);

            return ApiResponseType::sendJsonResponse(true, 'labels.badge_bulk_assigned_successfully', ['count' => $count]);
        } catch (\Exception $e) {
            Log::error('Badge bulk assign failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Bulk remove badges from selected products.
     */
    public function bulkRemove(BulkAssignBadgeRequest $request): JsonResponse
    {
        try {
            abort_unless($this->editPermission, 403);

            $data = $request->validated();
            $count = $this->badgeService->bulkRemove($data['product_ids']);

            return ApiResponseType::sendJsonResponse(true, 'labels.badge_bulk_removed_successfully', ['count' => $count]);
        } catch (\Exception $e) {
            Log::error('Badge bulk remove failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Return all badges as JSON (for select dropdowns).
     */
    public function listAll(): JsonResponse
    {
        try {
            abort_unless($this->viewPermission, 403);

            return response()->json($this->badgeService->allForSelect());
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }

    /**
     * Search badges for async dropdowns.
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403);

        $query = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
        $findId = $request->input('find_id');

        $badges = Badge::query()
            ->when($findId, function ($badgeQuery) use ($findId) {
                $badgeQuery->where('id', $findId);
            }, function ($badgeQuery) use ($query) {
                $badgeQuery
                    ->when($query !== '', function ($searchQuery) use ($query) {
                        $searchQuery->where(function ($nestedQuery) use ($query) {
                            $nestedQuery
                                ->where('name', 'like', '%'.$query.'%')
                                ->orWhere('label', 'like', '%'.$query.'%');
                        });
                    })
                    ->orderBy('name')
                    ->limit(20);
            })
            ->get(['id', 'name', 'label']);

        return response()->json(
            $badges->map(function (Badge $badge): array {
                return [
                    'id' => $badge->id,
                    'value' => $badge->id,
                    'text' => trim($badge->name.' - '.$badge->label),
                ];
            })->values()
        );
    }
}
