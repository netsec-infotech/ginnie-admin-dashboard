<?php

namespace App\Services;

use App\Enums\DateRangeFilterEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum;
use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryZone;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    /**
     * @param int|null $zoneId Filter all stats by delivery zone
     */
    public function getDispatchStats(?int $zoneId = null): array
    {
        $activeRiders = DeliveryBoy::where('status', 'active')
            ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
            ->where('is_blocked', false)
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->count();

        $ridersOnDelivery = DeliveryBoy::where('status', 'active')
            ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
            ->where('is_blocked', false)
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->whereHas('assignments', function ($q) {
                $q->where('status', DeliveryBoyAssignmentStatusEnum::IN_PROGRESS())
                    ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY());
            })
            ->count();

        $unassignedStatuses = [
            OrderStatusEnum::ACCEPTED_BY_SELLER(),
            OrderStatusEnum::READY_FOR_PICKUP(),
        ];

        $unassignedOrders = Order::whereIn('status', $unassignedStatuses)
            ->whereNull('delivery_boy_id')
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->whereDoesntHave('sellerOrders', function ($q) {
                $q->where('delivery_type', 'pickup');
            })
            ->count();

        $readyForPickup = Order::where('status', OrderStatusEnum::READY_FOR_PICKUP())
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->whereDoesntHave('sellerOrders', function ($q) {
                $q->where('delivery_type', 'pickup');
            })
            ->count();

        $deliveredToday = Order::where('status', OrderStatusEnum::DELIVERED())
            ->whereDate('updated_at', now()->toDateString())
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->count();

        $avgAssignmentTime = DeliveryBoyAssignment::whereDate('assigned_at', now()->toDateString())
            ->when($zoneId, fn($q) => $q->whereHas('deliveryBoy', fn($dq) => $dq->where('delivery_zone_id', $zoneId)))
            ->join('orders', 'delivery_boy_assignments.order_id', '=', 'orders.id')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, orders.created_at, delivery_boy_assignments.assigned_at)) as avg_minutes'))
            ->value('avg_minutes');

        $dropsToday = DeliveryBoyAssignment::whereIn('status', [
            DeliveryBoyAssignmentStatusEnum::DROPPED(),
            DeliveryBoyAssignmentStatusEnum::CANCELED(),
        ])
            ->whereDate('created_at', now()->toDateString())
            ->when($zoneId, fn($q) => $q->whereHas('deliveryBoy', fn($dq) => $dq->where('delivery_zone_id', $zoneId)))
            ->count();

        $staleUnassigned = Order::whereIn('status', $unassignedStatuses)
            ->whereNull('delivery_boy_id')
            ->where('created_at', '<', now()->subMinutes(15))
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->whereDoesntHave('sellerOrders', function ($q) {
                $q->where('delivery_type', 'pickup');
            })
            ->count();

        $ongoingDeliveries = Order::whereIn('status', [
            OrderStatusEnum::COLLECTED(),
            OrderStatusEnum::OUT_FOR_DELIVERY(),
        ])
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->count();

        return [
            'active_riders' => $activeRiders,
            'riders_on_delivery' => $ridersOnDelivery,
            'idle_riders' => max(0, $activeRiders - $ridersOnDelivery),
            'unassigned_orders' => $unassignedOrders,
            'ready_for_pickup' => $readyForPickup,
            'ongoing_deliveries' => $ongoingDeliveries,
            'delivered_today' => $deliveredToday,
            'avg_assignment_time_minutes' => round((float)($avgAssignmentTime ?? 0), 1),
            'drops_today' => $dropsToday,
            'stale_unassigned_count' => $staleUnassigned,
        ];
    }

    /**
     * Server-side datatable for riders currently on delivery.
     */
    public function getRidersOnDeliveryDatatable(Request $request, array $filters): array
    {
        $query = DeliveryBoyAssignment::where('status', DeliveryBoyAssignmentStatusEnum::IN_PROGRESS())
            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
            ->with([
                'deliveryBoy.user',
                'deliveryBoy.location',
                'deliveryBoy.deliveryZone',
                'order.items.store',
            ]);

        if (!empty($filters['zone_id'])) {
            $query->whereHas('deliveryBoy', fn($q) => $q->where('delivery_zone_id', $filters['zone_id']));
        }
        if (!empty($filters['payment_type'])) {
            $query->whereHas('order', fn($q) => $q->where('payment_method', $filters['payment_type']));
        }
        if (!empty($filters['range']) && !$filters['stale_only']) {
            $fromDate = DateRangeFilterEnum::fromDate($filters['range']);
            $query->where('created_at', '>=', $fromDate);
        }
        if ($filters['stale_only']) {
            $query->where('created_at', '<', now()->subMinutes(15));
        }

        $totalRecords = $query->count();

        $searchValue = $request->input('search.value', '');
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->whereHas('deliveryBoy', fn($dq) => $dq->where('full_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('order', fn($oq) => $oq->where('id', $searchValue));
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columns = ['id', 'delivery_boy_id', 'order_id', 'assigned_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'assigned_at';
        $query->orderBy($orderColumn, $orderDir);

        $start = (int)$request->input('start', 0);
        $length = (int)$request->input('length', 10);
        $assignments = $query->skip($start)->take($length)->get();

        $data = $assignments->map(function (DeliveryBoyAssignment $assignment) {
            $rider = $assignment->deliveryBoy;
            $order = $assignment->order;

            $storeNames = $order?->items
                ->map(fn($so) => $so->store?->name)
                ->filter()
                ->unique()
                ->implode(', ') ?? '';

            $assignedAt = $assignment->assigned_at ? Carbon::parse($assignment->assigned_at) : null;
            $lastGps = $rider?->location?->recorded_at;

            return [
                'rider_name' => e($rider?->full_name ?? ''),
                'rider_phone' => e($rider?->user?->phone ?? ''),
                'order_id' => $order?->id,
                'order_uuid' => $order?->uuid,
                'order_status' => $this->getStatusBadgeHtml($order?->status ?? ''),
                'store_names' => e($storeNames),
                'assigned_at' => $assignedAt?->format('d M Y, h:i A') ?? '',
                'elapsed' => $assignedAt ? $assignedAt->diffForHumans(null, true) : '',
                'zone' => e($rider?->deliveryZone?->name ?? ''),
                'last_gps' => $lastGps ? Carbon::parse($lastGps)->format('d M, h:i A') : 'N/A',
            ];
        })->toArray();

        return [
            'draw' => (int)$request->input('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    /**
     * Server-side datatable for unassigned orders awaiting rider assignment.
     */
    public function getUnassignedOrdersDatatable(Request $request, array $filters): array
    {
        $query = Order::whereIn('status', [
            OrderStatusEnum::ACCEPTED_BY_SELLER(),
            OrderStatusEnum::READY_FOR_PICKUP(),
        ])
            ->whereNull('delivery_boy_id')
            ->whereDoesntHave('sellerOrders', function ($q) {
                $q->where('delivery_type', 'pickup');
            })
            ->with(['sellerOrders.seller.stores', 'deliveryZone']);

        $this->extracted($filters, $query);

        $totalRecords = $query->count();

        $searchValue = $request->input('search.value', '');
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', $searchValue)
                    ->orWhere('shipping_name', 'like', "%{$searchValue}%")
                    ->orWhere('uuid', 'like', "%{$searchValue}%");
            });
        }

        $filteredRecords = $query->count();

        $orderDir = $request->input('order.0.dir', 'asc');
        $query->orderBy('created_at', $orderDir);

        $start = (int)$request->input('start', 0);
        $length = (int)$request->input('length', 10);
        $orders = $query->skip($start)->take($length)->get();

        $data = $orders->map(function (Order $order) {
            $storeNames = $order->sellerOrders
                ->map(fn($so) => $so->seller?->stores?->first()?->name)
                ->filter()
                ->implode(', ');

            $waitingSince = Carbon::parse($order->updated_at);
            $minutesWaiting = $waitingSince->diffInMinutes(now());

            return [
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'customer_name' => e($order->shipping_name ?? ''),
                'store_names' => e($storeNames),
                'zone' => e($order->deliveryZone?->name ?? ''),
                'payment_method' => e($order->payment_method ?? ''),
                'total' => number_format((float)$order->final_total, 2),
                'time_waiting' => $waitingSince->diffForHumans(null, true),
                'created_at' => Carbon::parse($order->created_at)->format('d M Y, h:i A'),
                'is_stale' => $minutesWaiting > 15,
                'delivery_zone_id' => $order->delivery_zone_id,
                'actions' => '<button class="btn btn-sm btn-primary assign-rider-btn" '
                    . 'data-order-id="' . $order->id . '" '
                    . 'data-zone-id="' . ($order->delivery_zone_id ?? '') . '" '
                    . 'onclick="event.stopPropagation()">'
                    . '<i class="ti ti-user-plus me-1"></i>' . e(__('labels.assign_rider'))
                    . '</button>',
            ];
        })->toArray();

        return [
            'draw' => (int)$request->input('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    /**
     * Server-side datatable for orders ready for pickup.
     */
    public function getReadyForPickupDatatable(Request $request, $filters): array
    {
        $query = Order::where('status', OrderStatusEnum::READY_FOR_PICKUP())
            ->whereDoesntHave('sellerOrders', function ($q) {
                $q->where('delivery_type', 'pickup');
            })
            ->with(['deliveryBoy', 'sellerOrders.seller.stores', 'deliveryZone', 'items']);

        $this->extracted($filters, $query);
        $totalRecords = $query->count();

        $searchValue = $request->input('search.value', '');
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', $searchValue)
                    ->orWhere('uuid', 'like', "%{$searchValue}%")
                    ->orWhere('shipping_name', 'like', "%{$searchValue}%");
            });
        }

        $filteredRecords = $query->count();

        $orderDir = $request->input('order.0.dir', 'asc');
        $query->orderBy('updated_at', $orderDir);

        $start = (int)$request->input('start', 0);
        $length = (int)$request->input('length', 10);
        $orders = $query->skip($start)->take($length)->get();

        $data = $orders->map(function (Order $order) {
            $storeNames = $order->sellerOrders
                ->map(fn($so) => $so->seller?->stores?->first()?->name)
                ->filter()
                ->implode(', ');

            $riderName = $order->deliveryBoy?->full_name;
            $assignedRider = $riderName
                ? e($riderName)
                : '<span class="badge bg-warning-lt">Unassigned</span>';

            return [
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'store_names' => e($storeNames),
                'customer_area' => e($order->shipping_city ?? $order->shipping_address_1 ?? ''),
                'assigned_rider' => $assignedRider,
                'payment_method' => e($order->payment_method ?? ''),
                'ready_since' => Carbon::parse($order->updated_at)->diffForHumans(null, true),
                'items_count' => $order->items->count(),
            ];
        })->toArray();

        return [
            'draw' => (int)$request->input('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    /**
     * Zone-level rider availability overview.
     */
    public function getZoneAvailability(): array
    {
        $zones = DeliveryZone::all();

        return $zones->map(function (DeliveryZone $zone) {
            $totalRiders = DeliveryBoy::where('delivery_zone_id', $zone->id)->count();

            $activeRiders = DeliveryBoy::where('delivery_zone_id', $zone->id)
                ->where('status', 'active')
                ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
                ->where('is_blocked', false)
                ->count();

            $onDelivery = DeliveryBoy::where('delivery_zone_id', $zone->id)
                ->where('status', 'active')
                ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
                ->where('is_blocked', false)
                ->whereHas('assignments', function ($q) {
                    $q->where('status', DeliveryBoyAssignmentStatusEnum::IN_PROGRESS())
                        ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY());
                })
                ->count();

            return [
                'zone_name' => $zone->name,
                'total_riders' => $totalRiders,
                'active_riders' => $activeRiders,
                'on_delivery' => $onDelivery,
                'idle' => max(0, $activeRiders - $onDelivery),
            ];
        })->toArray();
    }

    /**
     * Recent assignment activity feed.
     */
    public function getRecentActivity(int $limit = 15, ?int $zoneId = null): array
    {
        $query = DeliveryBoyAssignment::with(['deliveryBoy.user', 'order'])
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($zoneId) {
            $query->whereHas('deliveryBoy', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        return $query->get()->map(function (DeliveryBoyAssignment $assignment) {
            $riderName = e($assignment->deliveryBoy?->full_name ?? 'Unknown');
            $orderId = $assignment->order?->id ?? '?';
            $status = $assignment->status ?? '';

            $message = match ($status) {
                DeliveryBoyAssignmentStatusEnum::ASSIGNED() => "{$riderName} assigned to Order #{$orderId}",
                DeliveryBoyAssignmentStatusEnum::IN_PROGRESS() => "{$riderName} picked up Order #{$orderId}",
                DeliveryBoyAssignmentStatusEnum::COMPLETED() => "{$riderName} delivered Order #{$orderId}",
                DeliveryBoyAssignmentStatusEnum::CANCELED() => "{$riderName} canceled Order #{$orderId}",
                DeliveryBoyAssignmentStatusEnum::DROPPED() => "{$riderName} dropped Order #{$orderId}",
                default => "{$riderName} — Order #{$orderId} ({$status})",
            };

            return [
                'message' => $message,
                'time_ago' => Carbon::parse($assignment->created_at)->diffForHumans(),
                'status' => $status,
                'order_id' => $assignment->order?->id,
            ];
        })->toArray();
    }

    /**
     * Hourly delivery completion counts for today (24 buckets).
     */
    public function getHourlyDeliveries(?int $zoneId = null): array
    {
        $rows = Order::where('status', OrderStatusEnum::DELIVERED())
            ->whereDate('updated_at', now()->toDateString())
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->select(DB::raw('HOUR(updated_at) as hour'), DB::raw('COUNT(*) as total'))
            ->groupBy('hour')
            ->pluck('total', 'hour')
            ->toArray();

        $data = [];
        $currentHour = (int)now()->format('G');
        for ($h = 0; $h <= $currentHour; $h++) {
            $data[] = [
                'hour' => sprintf('%02d:00', $h),
                'count' => $rows[$h] ?? 0,
            ];
        }

        return $data;
    }

    /**
     * Bootstrap badge HTML for an order status.
     */
    private function getStatusBadgeHtml(string $status): string
    {
        $class = match ($status) {
            OrderStatusEnum::DELIVERED() => 'bg-success-lt',
            OrderStatusEnum::OUT_FOR_DELIVERY(),
            OrderStatusEnum::COLLECTED() => 'bg-info-lt',
            OrderStatusEnum::READY_FOR_PICKUP(),
            OrderStatusEnum::ASSIGNED() => 'bg-primary-lt',
            OrderStatusEnum::ACCEPTED_BY_SELLER(),
            OrderStatusEnum::PREPARING(),
            OrderStatusEnum::PARTIALLY_ACCEPTED() => 'bg-warning-lt',
            OrderStatusEnum::CANCELLED(),
            OrderStatusEnum::FAILED(),
            OrderStatusEnum::REJECTED_BY_SELLER() => 'bg-danger-lt',
            default => 'bg-secondary-lt',
        };

        $label = e(str_replace('_', ' ', ucwords($status, '_')));

        return '<span class="badge ' . $class . '">' . $label . '</span>';
    }

    /**
     * @param $filters
     * @param $query
     * @return void
     */
    public function extracted($filters, $query): void
    {
        if (!empty($filters['zone_id'])) {
            $query->where('delivery_zone_id', $filters['zone_id']);
        }

        if (!empty($filters['payment_type'])) {
            $query->where('payment_method', $filters['payment_type']);
        }

        if (!empty($filters['range']) && !$filters['stale_only']) {
            $fromDate = DateRangeFilterEnum::fromDate($filters['range']);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }
        }
        if ($filters['stale_only']) {
            $query->where('updated_at', '<', now()->subMinutes(15));
        }
    }
}
