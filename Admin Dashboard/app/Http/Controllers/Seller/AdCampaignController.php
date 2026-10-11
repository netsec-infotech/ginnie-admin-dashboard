<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\CreateAdCampaignRequest;
use App\Models\AdCampaign;
use App\Models\Product;
use App\Services\AdCampaignDashboardService;
use App\Services\AdCampaignService;
use App\Services\AdWalletService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdCampaignController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $viewPermission = false;

    protected bool $createPermission = false;

    protected bool $pausePermission = false;

    protected bool $dashboardViewPermission = false;

    public function __construct(
        protected AdCampaignService $adCampaignService,
        protected AdWalletService $adWalletService,
        protected AdCampaignDashboardService $dashboardService,
    ) {
        $user = auth()->user();

        if ($user) {
            $isOwner = $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->viewPermission = $isOwner || $this->hasPermission(SellerPermissionEnum::AD_CAMPAIGN_VIEW());
            $this->createPermission = $isOwner || $this->hasPermission(SellerPermissionEnum::AD_CAMPAIGN_CREATE());
            $this->pausePermission = $isOwner || $this->hasPermission(SellerPermissionEnum::AD_CAMPAIGN_PAUSE());
            $this->dashboardViewPermission = $isOwner || $this->hasPermission(SellerPermissionEnum::AD_CAMPAIGN_DASHBOARD_VIEW());
        }
    }

    /**
     * Campaign listing for the seller.
     */
    public function index(): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $columns = [
            ['data' => 'product', 'name' => 'product', 'title' => __('labels.ad_campaign_product')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.ad_campaign_status')],
            ['data' => 'budget', 'name' => 'budget', 'title' => __('labels.ad_campaign_budget_label'), 'orderable' => true, 'searchable' => false],
            ['data' => 'clicks', 'name' => 'clicks', 'title' => __('labels.ad_campaign_clicks'), 'orderable' => false, 'searchable' => false],
            ['data' => 'impressions', 'name' => 'impressions', 'title' => __('labels.ad_campaign_impressions'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.ad_campaign_created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $statusOptions = [
            '' => __('labels.ad_campaign_all'),
            AdCampaignStatusEnum::PENDING_APPROVAL() => __('labels.ad_campaign_pending'),
            AdCampaignStatusEnum::RUNNING() => __('labels.ad_campaign_running'),
            AdCampaignStatusEnum::PAUSED() => __('labels.paused'),
            AdCampaignStatusEnum::COMPLETED() => __('labels.completed'),
            AdCampaignStatusEnum::REJECTED() => __('labels.rejected'),
        ];

        $seller = $this->ensureSeller();
        $sellerId = $seller?->id ?? 0;

        $campaignInsights = [
            'running' => AdCampaign::where('seller_id', $sellerId)->where('status', AdCampaignStatusEnum::RUNNING())->count(),
            'pending' => AdCampaign::where('seller_id', $sellerId)->where('status', AdCampaignStatusEnum::PENDING_APPROVAL())->count(),
            'rejected' => AdCampaign::where('seller_id', $sellerId)->where('status', AdCampaignStatusEnum::REJECTED())->count(),
            'force_stopped' => AdCampaign::where('seller_id', $sellerId)->where('status', AdCampaignStatusEnum::FORCE_STOPPED())->count(),
        ];

        return view('seller.ads.campaigns.index', [
            'columns' => $columns,
            'statusOptions' => $statusOptions,
            'createPermission' => $this->createPermission,
            'pausePermission' => $this->pausePermission,
            'campaignInsights' => $campaignInsights,
        ]);
    }

    /**
     * Campaign creation form.
     */
    public function create(): View
    {
        abort_unless($this->createPermission, 403, __('labels.unauthorized_access'));

        $seller = $this->ensureSeller();
        $settings = $this->adWalletService->getSettings();
        $featureEnabled = $this->adWalletService->isFeatureEnabled();

        $walletBalance = 0.0;
        try {
            $wallet = $this->adWalletService->getAdWallet($seller->user_id);
            $walletBalance = (float) ($wallet['balance'] ?? 0);
        } catch (\Exception $e) {
            Log::warning('AdCampaignController::create — could not load ad wallet', ['error' => $e->getMessage()]);
        }

        // media eager-loaded so the main_image accessor works without N+1.
        $products = Product::where('seller_id', $seller->id)
            ->whereNull('deleted_at')
            ->select(['id', 'title'])
            ->with('media')
            ->orderBy('title')
            ->get();

        $impressionMultiplierMin = (int) ($settings['impressionMultiplierMin'] ?? 12);
        $impressionMultiplierMax = (int) ($settings['impressionMultiplierMax'] ?? 20);

        return view('seller.ads.campaigns.create', compact(
            'settings',
            'featureEnabled',
            'walletBalance',
            'products',
            'impressionMultiplierMin',
            'impressionMultiplierMax',
        ));
    }

    /**
     * Store a new campaign — validate, deduct wallet, submit for approval.
     */
    public function store(CreateAdCampaignRequest $request): JsonResponse
    {
        abort_unless($this->createPermission, 403, __('labels.unauthorized_access'));

        try {
            $seller = $this->ensureSeller();
            $result = $this->adCampaignService->create(
                userId: $seller->user_id,
                sellerId: $seller->id,
                data: $request->validated(),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'],
            );
        } catch (\Exception $e) {
            Log::error('AdCampaignController::store failed', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Seller pauses a running campaign.
     */
    public function pause(int $id): JsonResponse
    {
        abort_unless($this->pausePermission, 403, __('labels.unauthorized_access'));

        try {
            $seller = $this->ensureSeller();
            $result = $this->adCampaignService->pause($id, $seller->id);

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'],
            );
        } catch (\Exception $e) {
            Log::error('AdCampaignController::pause failed', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Seller resumes a paused campaign.
     */
    public function resume(int $id): JsonResponse
    {
        abort_unless($this->pausePermission, 403, __('labels.unauthorized_access'));

        try {
            $seller = $this->ensureSeller();
            $result = $this->adCampaignService->resume($id, $seller->id);

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'],
            );
        } catch (\Exception $e) {
            Log::error('AdCampaignController::resume failed', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Ad campaign statistics dashboard.
     */
    public function dashboard(): View
    {
        abort_unless($this->dashboardViewPermission, 403, __('labels.unauthorized_access'));

        $seller = $this->ensureSeller();
        $data = $this->dashboardService->getDashboardData(
            sellerId: $seller->id,
            days: 7,
            userId: $seller->user_id,
        );

        return view('seller.ads.campaigns.dashboard', [
            'data' => $data,
        ]);
    }

    /**
     * AJAX endpoint for dashboard period changes.
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        abort_unless($this->dashboardViewPermission, 403, __('labels.unauthorized_access'));

        try {
            $seller = $this->ensureSeller();
            $days = (int) $request->input('days', 7);
            $data = $this->dashboardService->getDashboardData(
                sellerId: $seller->id,
                days: $days,
                userId: $seller->user_id,
            );

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Seller ad dashboard data failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => __('labels.something_went_wrong')], 500);
        }
    }

    /**
     * DataTable JSON for the campaign listing.
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $seller = $this->ensureSeller();

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 5;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';

        $columns = ['id', 'status', 'budget', 'id', 'id', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

        $query = AdCampaign::forSeller($seller->id)->with(['product.media', 'stats']);

        $totalRecords = AdCampaign::forSeller($seller->id)->count();

        // Status filter
        $statusFilter = $request->get('status');
        if (! empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        // Search filter
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('title', 'like', "%{$searchValue}%"));
            });
        }

        $filteredRecords = $query->count();

        $pausePermission = $this->pausePermission;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($campaign) use ($pausePermission) {
                $status = $campaign->status;
                $progress = $campaign->spend_progress;
                $totalClicks = $campaign->stats->sum('clicks');
                $totalImpr = $campaign->stats->sum('impressions');

                return [
                    'product' => view('seller.ads.campaigns._product_cell', compact('campaign'))->render(),
                    'status' => view('seller.ads.campaigns._status_cell', compact('campaign', 'status'))->render(),
                    'budget' => view('seller.ads.campaigns._budget_cell', compact('campaign', 'progress'))->render(),
                    'clicks' => number_format($totalClicks),
                    'impressions' => number_format($totalImpr),
                    'created_at' => $campaign->created_at->format('d M Y'),
                    'action' => view('seller.ads.campaigns._action_cell', compact('campaign', 'status', 'pausePermission'))->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }
}
