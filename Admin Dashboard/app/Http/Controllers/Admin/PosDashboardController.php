<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Services\AdminPosDashboardService;
use App\Traits\ChecksPermissions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosDashboardController extends Controller
{
    use ChecksPermissions;

    protected bool $viewPermission = false;

    public function __construct(
        protected AdminPosDashboardService $dashboardService,
    ) {
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::POS_DASHBOARD_VIEW());
    }

    /**
     * Admin POS analytics dashboard.
     */
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveDateRange($request);

        $data = $this->gatherDashboardData($from, $to);

        return view('admin.pos-dashboard', array_merge($data, [
            'dateRange'      => $request->get('range', 'today'),
            'dateFrom'       => $from->toDateString(),
            'dateTo'         => $to->toDateString(),
            'viewPermission' => $this->viewPermission,
        ]));
    }

    /**
     * AJAX endpoint for chart/filter updates.
     */
    public function getData(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        return response()->json($this->gatherDashboardData($from, $to));
    }

    private function resolveDateRange(Request $request): array
    {
        $range = $request->get('range', 'today');

        return match ($range) {
            'today'   => [Carbon::today()->startOfDay(), Carbon::now()],
            '7days'   => [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()],
            '30days'  => [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()],
            'custom'  => [
                Carbon::parse($request->get('date_from', today()->toDateString()))->startOfDay(),
                Carbon::parse($request->get('date_to', today()->toDateString()))->endOfDay(),
            ],
            default   => [Carbon::today()->startOfDay(), Carbon::now()],
        };
    }

    private function gatherDashboardData(Carbon $from, Carbon $to): array
    {
        return [
            'salesSummary'      => $this->dashboardService->getSalesSummary($from, $to),
            'paymentBreakdown'  => $this->dashboardService->getPaymentBreakdown($from, $to),
            'salesTrend'        => $this->dashboardService->getSalesTrend($from, $to),
            'topSellers'        => $this->dashboardService->getTopSellers($from, $to),
            'topProducts'       => $this->dashboardService->getTopProducts($from, $to),
            'refundSummary'     => $this->dashboardService->getRefundSummary($from, $to),
            'customerBreakdown' => $this->dashboardService->getCustomerBreakdown($from, $to),
            'sellerAdoption'    => $this->dashboardService->getSellerAdoption(),
        ];
    }
}
