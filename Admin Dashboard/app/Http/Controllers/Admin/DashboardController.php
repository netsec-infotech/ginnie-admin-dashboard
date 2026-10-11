<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Services\DashboardService;
use App\Services\WalletService;
use App\Models\Seller;
use App\Models\User;
use App\Models\Order;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ChecksPermissions;
    protected DashboardService $dashboardService;
    protected CurrencyService $currencyService;
    protected WalletService $walletService;
    protected bool $viewPermission = true;

    public function __construct(
        DashboardService $dashboardService,
        CurrencyService  $currencyService,
        WalletService    $walletService
    )
    {
        $this->dashboardService = $dashboardService;
        $this->currencyService = $currencyService;
        $this->walletService = $walletService;
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::DASHBOARD_VIEW());
    }

    /**
     * Display the admin dashboard with dynamic data.
     */
    public function index(Request $request): View
    {
        $currencyService = $this->currencyService;
        $dashboardService = $this->dashboardService;
        $zoneId = $request->input('zone_id') ? (int) $request->input('zone_id') : null;

        $adminCommissionChart = $dashboardService->getAdminCommissionChartsData(days: 30, zoneId: $zoneId);
        $adminInsights = $dashboardService->getAdminInsightsData(zoneId: $zoneId);
        $conversionRateData = $dashboardService->getAdminConversionRateData(days: 30, zoneId: $zoneId);
        $revenueDataBg = $dashboardService->getRevenueData(days: 30, zoneId: $zoneId);
        $dailyPurchaseHistory = $dashboardService->getDailyPurchaseHistory(days: 30, zoneId: $zoneId);
        $todaysEarning = $dashboardService->getTodaysEarning(zoneId: $zoneId);
        $categoryProductWeightage = $dashboardService->getCategoryProductWeightage();
        $newUserRegistrationsData = $dashboardService->getNewUserRegistrationsData(days: 30, zoneId: $zoneId);

        // Top performers
        $topSellers = $dashboardService->getTopSellers(days: 30, limit: 5, zoneId: $zoneId);
        $topSellingProducts = $dashboardService->getTopSellingProducts(days: 30, limit: 5, zoneId: $zoneId);
        $topDeliveryBoys = $dashboardService->getTopDeliveryBoys(days: 30, limit: 5, zoneId: $zoneId);
        $topStores = $dashboardService->getTopStores(days: 30, limit: 5, zoneId: $zoneId);
        $topZones = $dashboardService->getTopZones(days: 30, limit: 5);
        $categoriesWithFilters = $dashboardService->getCategoriesWithFilters(sortBy: 'products_count', filterBy: 'all');
        $enhancedCommissionsData = $dashboardService->getEnhancedCommissionsData(days: 30, type: 'all', zoneId: $zoneId);

        // Revenue vs Orders chart
        $revenueVsOrders = $dashboardService->getRevenueVsOrdersData(days: 30, zoneId: $zoneId);

        // New widgets
        $orderFunnel = $dashboardService->getOrderFunnelData(days: 30, zoneId: $zoneId);
        $alertsData = $dashboardService->getAlertsData(zoneId: $zoneId);
        $customerInsights = $dashboardService->getCustomerInsightsData(days: 30, zoneId: $zoneId);
        $zoneHealth = $dashboardService->getZoneHealthData(days: 30, zoneId: $zoneId);
        $activeZones = $dashboardService->getActiveZones();

        // Financial widgets
        $sellerSettlements = $dashboardService->getSellerSettlementsData(days: 1, zoneId: $zoneId);
        $dbSettlements = $dashboardService->getDeliveryBoySettlementsData(days: 1, zoneId: $zoneId);
        $withdrawals = $dashboardService->getWithdrawalsData(days: 1, zoneId: $zoneId);
        $cashCollection = $dashboardService->getCashCollectionData(days: 1, zoneId: $zoneId);
        $adCampaigns = $dashboardService->getAdCampaignsData(days: 1, zoneId: $zoneId);
        $subscriptions = $dashboardService->getSubscriptionsData(days: 1, zoneId: $zoneId);

        $viewPermission = $this->viewPermission;
        return view('admin.dashboard', compact(
            'currencyService',
            'adminCommissionChart',
            'adminInsights',
            'conversionRateData',
            'revenueDataBg',
            'dailyPurchaseHistory',
            'todaysEarning',
            'categoryProductWeightage',
            'newUserRegistrationsData',
            'topSellers',
            'topSellingProducts',
            'topDeliveryBoys',
            'topStores',
            'topZones',
            'categoriesWithFilters',
            'enhancedCommissionsData',
            'revenueVsOrders',
            'orderFunnel',
            'alertsData',
            'customerInsights',
            'zoneHealth',
            'activeZones',
            'sellerSettlements',
            'dbSettlements',
            'withdrawals',
            'cashCollection',
            'adCampaigns',
            'subscriptions',
            'zoneId',
            'viewPermission'
        ));
    }

    /**
     * Get dashboard data via AJAX for dynamic updates.
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $days = (int) $request->input('days', 7);
        $limit = (int) $request->input('limit', 5);
        $sortBy = $request->input('sort_by', 'name');
        $filterBy = $request->input('filter_by', 'all');
        $commissionType = $request->input('commission_type', 'all');
        $zoneId = $request->input('zone_id') ? (int) $request->input('zone_id') : null;
        $rankBy = $request->input('rank_by', 'revenue');

        $data = match ($type) {
            'sales' => $this->dashboardService->getAdminConversionRateData(days: $days, zoneId: $zoneId),
            'revenue' => $this->dashboardService->getRevenueData(days: $days, zoneId: $zoneId),
            'new_users' => $this->dashboardService->getNewUserRegistrationsData(days: $days, zoneId: $zoneId),
            'top_sellers' => $this->dashboardService->getTopSellers(days: $days, limit: $limit, sortBy: $rankBy, zoneId: $zoneId),
            'top_products' => $this->dashboardService->getTopSellingProducts(days: $days, limit: $limit, sortBy: $rankBy, zoneId: $zoneId),
            'top_delivery_boys' => $this->dashboardService->getTopDeliveryBoys(days: $days, limit: $limit, sortBy: $rankBy, zoneId: $zoneId),
            'top_stores' => $this->dashboardService->getTopStores(days: $days, limit: $limit, sortBy: $rankBy, zoneId: $zoneId),
            'top_zones' => $this->dashboardService->getTopZones(days: $days, limit: $limit, sortBy: $rankBy),
            'categories' => $this->dashboardService->getCategoriesWithFilters(sortBy: $sortBy, filterBy: $filterBy),
            'commissions' => $this->dashboardService->getEnhancedCommissionsData(days: $days, type: $commissionType, zoneId: $zoneId),
            'revenue_vs_orders' => $this->dashboardService->getRevenueVsOrdersData(days: $days, zoneId: $zoneId),
            'order_funnel' => $this->dashboardService->getOrderFunnelData(days: $days, zoneId: $zoneId),
            'alerts' => $this->dashboardService->getAlertsData(zoneId: $zoneId),
            'customer_insights' => $this->dashboardService->getCustomerInsightsData(days: $days, zoneId: $zoneId),
            'zone_health' => $this->dashboardService->getZoneHealthData(days: $days, zoneId: $zoneId),
            'insights' => $this->dashboardService->getAdminInsightsData(zoneId: $zoneId),
            'seller_settlements' => $this->dashboardService->getSellerSettlementsData(days: $days, zoneId: $zoneId),
            'db_settlements' => $this->dashboardService->getDeliveryBoySettlementsData(days: $days, zoneId: $zoneId),
            'withdrawals' => $this->dashboardService->getWithdrawalsData(days: $days, zoneId: $zoneId),
            'cash_collection' => $this->dashboardService->getCashCollectionData(days: $days, zoneId: $zoneId),
            'ad_campaigns' => $this->dashboardService->getAdCampaignsData(days: $days, zoneId: $zoneId),
            'subscriptions' => $this->dashboardService->getSubscriptionsData(days: $days, zoneId: $zoneId),
            default => null,
        };

        if ($data === null) {
            return response()->json(['error' => 'Invalid data type requested'], 400);
        }

        return response()->json($data);
    }
}
