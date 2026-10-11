<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Services\DispatchService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DispatchController extends Controller
{
    use ChecksPermissions;

    protected bool $viewPermission = false;

    public function __construct(protected DispatchService $dispatchService)
    {
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::DISPATCH_VIEW());
    }

    /**
     * Display the dispatch management dashboard.
     */
    public function index(Request $request): View
    {
        if (!$this->viewPermission) {
            abort(403, __('labels.unauthorized'));
        }

        $zoneId = $request->query('zone_id') ? (int)$request->query('zone_id') : null;
        $stats = $this->dispatchService->getDispatchStats($zoneId);
        $zones = DeliveryZone::orderBy('name')->get(['id', 'name']);
        $zoneAvailability = $this->dispatchService->getZoneAvailability();
        $recentActivity = $this->dispatchService->getRecentActivity(15, $zoneId);
        $hourlyDeliveries = $this->dispatchService->getHourlyDeliveries($zoneId);
        $editPermission = $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
        $ridersOnDeliveryColumns = [
            ['data' => 'rider_name', 'name' => 'rider_name', 'title' => __('labels.rider_name')],
            ['data' => 'rider_phone', 'name' => 'rider_phone', 'title' => __('labels.rider_phone'), 'orderable' => false],
            ['data' => 'order_id', 'name' => 'order_id', 'title' => __('labels.order_id')],
            ['data' => 'order_status', 'name' => 'order_status', 'title' => __('labels.order_status'), 'orderable' => false],
            ['data' => 'store_names', 'name' => 'store_names', 'title' => __('labels.store_names'), 'orderable' => false],
            ['data' => 'assigned_at', 'name' => 'assigned_at', 'title' => __('labels.assigned_at')],
            ['data' => 'elapsed', 'name' => 'elapsed', 'title' => __('labels.elapsed_time'), 'orderable' => false],
            ['data' => 'zone', 'name' => 'zone', 'title' => __('labels.zone'), 'orderable' => false],
            ['data' => 'last_gps', 'name' => 'last_gps', 'title' => __('labels.last_gps_update'), 'orderable' => false],
        ];
        $unassignedOrdersColumns = [
            ['data' => 'order_id', 'name' => 'order_id', 'title' => __('labels.order_id')],
            ['data' => 'order_uuid', 'name' => 'order_uuid', 'title' => __('labels.uuid'), 'orderable' => false],
            ['data' => 'customer_name', 'name' => 'customer_name', 'title' => __('labels.buyer_name'), 'orderable' => false],
            ['data' => 'store_names', 'name' => 'store_names', 'title' => __('labels.store_names'), 'orderable' => false],
            ['data' => 'zone', 'name' => 'zone', 'title' => __('labels.zone'), 'orderable' => false],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'orderable' => false],
            ['data' => 'total', 'name' => 'total', 'title' => __('labels.total'), 'orderable' => false],
            ['data' => 'time_waiting', 'name' => 'time_waiting', 'title' => __('labels.time_waiting'), 'orderable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];
        $readyForPickupColumns = [
            ['data' => 'order_id', 'name' => 'order_id', 'title' => __('labels.order_id')],
            ['data' => 'order_uuid', 'name' => 'order_uuid', 'title' => __('labels.uuid'), 'orderable' => false],
            ['data' => 'store_names', 'name' => 'store_names', 'title' => __('labels.store_names'), 'orderable' => false],
            ['data' => 'customer_area', 'name' => 'customer_area', 'title' => __('labels.customer_area'), 'orderable' => false],
            ['data' => 'assigned_rider', 'name' => 'assigned_rider', 'title' => __('labels.assigned_rider'), 'orderable' => false],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'orderable' => false],
            ['data' => 'ready_since', 'name' => 'ready_since', 'title' => __('labels.ready_since'), 'orderable' => false],
            ['data' => 'items_count', 'name' => 'items_count', 'title' => __('labels.items_count'), 'orderable' => false],
        ];
        if ($editPermission) {
            $unassignedOrdersColumns[] = ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false];
        }

        return view('admin.dispatch.index', compact(
            'stats',
            'zones',
            'zoneId',
            'zoneAvailability',
            'recentActivity',
            'hourlyDeliveries',
            'editPermission',
            'ridersOnDeliveryColumns',
            'unassignedOrdersColumns',
            'readyForPickupColumns',
        ));
    }

    /**
     * AJAX endpoint for auto-refresh stats polling.
     */
    public function stats(Request $request): JsonResponse
    {
        if (!$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        try {
            $zoneId = $request->query('zone_id') ? (int)$request->query('zone_id') : null;
            $stats = $this->dispatchService->getDispatchStats($zoneId);
            $zoneAvailability = $this->dispatchService->getZoneAvailability();
            $recentActivity = $this->dispatchService->getRecentActivity(15, $zoneId);
            $hourlyDeliveries = $this->dispatchService->getHourlyDeliveries($zoneId);

            return ApiResponseType::sendJsonResponse(true, 'labels.success', [
                'stats' => $stats,
                'zone_availability' => $zoneAvailability,
                'recent_activity' => $recentActivity,
                'hourly_deliveries' => $hourlyDeliveries,
            ]);
        } catch (\Throwable $e) {
            Log::error('Dispatch stats fetch failed', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null);
        }
    }

    /**
     * Server-side datatable: riders currently on delivery.
     */
    public function ridersOnDelivery(Request $request): JsonResponse
    {
        if (!$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        try {
            $filters = $this->filterArray($request);

            return response()->json(
                $this->dispatchService->getRidersOnDeliveryDatatable($request, $filters)
            );
        } catch (\Throwable $e) {
            Log::error('Dispatch riders datatable failed', ['error' => $e->getMessage()]);

            return response()->json([
                'draw' => (int)$request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }
    }

    private function filterArray($request): array
    {
        return [
            'zone_id' => $request->query('zone_id') ? (int)$request->query('zone_id') : null,
            'payment_type' => $request->query('payment_type'),
            'range' => $request->query('range'),
            'stale_only' => $request->query('stale_only') && (bool)$request->query('stale_only'),
        ];
    }

    /**
     * Server-side datatable: unassigned orders.
     */
    public function unassignedOrders(Request $request): JsonResponse
    {
        if (!$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        try {
            $filters = $this->filterArray($request);

            return response()->json(
                $this->dispatchService->getUnassignedOrdersDatatable($request, $filters)
            );
        } catch (\Throwable $e) {
            Log::error('Dispatch unassigned datatable failed', ['error' => $e->getMessage()]);

            return response()->json([
                'draw' => (int)$request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }
    }

    /**
     * Server-side datatable: ready for pickup orders.
     */
    public function readyForPickup(Request $request): JsonResponse
    {
        if (!$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        try {
            $filters = $this->filterArray($request);

            return response()->json(
                $this->dispatchService->getReadyForPickupDatatable($request, $filters)
            );
        } catch (\Throwable $e) {
            Log::error('Dispatch ready-for-pickup datatable failed', ['error' => $e->getMessage()]);

            return response()->json([
                'draw' => (int)$request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }
    }
}
