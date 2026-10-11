<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Services\PosDashboardService;
use App\Services\SubscriptionUsageService;
use App\Traits\ChecksPermissions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosDashboardController extends Controller
{
    use ChecksPermissions;

    protected bool $viewPermission = false;
    protected bool $posSubscriptionActive = false;
    protected $seller;

    public function __construct(
        protected PosDashboardService $dashboardService,
    ) {
        $user = auth()->user();
        $this->seller = $user?->seller();
        $this->posSubscriptionActive = $this->seller
            ? app(SubscriptionUsageService::class)->hasPosAccess($this->seller->id)
            : false;
        $this->viewPermission = $this->posSubscriptionActive
            && ($this->hasPermission(SellerPermissionEnum::POS_VIEW())
                || $user->hasRole(DefaultSystemRolesEnum::SELLER()));
    }

    /**
     * POS analytics dashboard.
     */
    public function index(Request $request): View
    {
        $seller = $this->seller;

        if (!$seller) {
            abort(404, __('labels.seller_not_found'));
        }

        $stores = $seller->stores()->get(['id', 'name']);
        $storeId = $request->get('store_id') ? (int) $request->get('store_id') : null;

        [$from, $to] = $this->resolveDateRange($request);

        $data = $this->gatherDashboardData($seller->id, $storeId, $from, $to);

        return view('seller.pos.dashboard', array_merge($data, [
            'stores'          => $stores,
            'selectedStoreId' => $storeId,
            'dateRange'       => $request->get('range', 'today'),
            'dateFrom'        => $from->toDateString(),
            'dateTo'          => $to->toDateString(),
            'viewPermission'  => $this->viewPermission,
        ]));
    }

    /**
     * AJAX endpoint for chart/filter updates.
     */
    public function getData(Request $request): JsonResponse
    {
        $seller = $this->seller;

        if (!$seller) {
            return response()->json(['error' => __('labels.seller_not_found')], 404);
        }

        $storeId = $request->get('store_id') ? (int) $request->get('store_id') : null;

        [$from, $to] = $this->resolveDateRange($request);

        return response()->json($this->gatherDashboardData($seller->id, $storeId, $from, $to));
    }

    /**
     * Resolve date range from request params.
     */
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

    /**
     * Gather all dashboard data sections.
     */
    private function gatherDashboardData(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        return [
            'salesSummary'       => $this->dashboardService->getSalesSummary($sellerId, $storeId, $from, $to),
            'paymentBreakdown'   => $this->dashboardService->getPaymentBreakdown($sellerId, $storeId, $from, $to),
            'salesTrend'         => $this->dashboardService->getSalesTrend($sellerId, $storeId, $from, $to),
            'topProducts'        => $this->dashboardService->getTopProducts($sellerId, $storeId, $from, $to),
            'customerBreakdown'  => $this->dashboardService->getCustomerBreakdown($sellerId, $storeId, $from, $to),
            'refundSummary'      => $this->dashboardService->getRefundSummary($sellerId, $storeId, $from, $to),
            'discountSummary'    => $this->dashboardService->getDiscountSummary($sellerId, $storeId, $from, $to),
            'parkedSales'        => $this->dashboardService->getParkedSales($sellerId, $storeId),
            'lowStock'           => $this->dashboardService->getLowStockProducts($sellerId, $storeId),
            'storeRevenueSplit'  => $this->dashboardService->getStoreRevenueSplit($sellerId, $storeId, $from, $to),
        ];
    }
}
