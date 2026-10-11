<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\AdminAdCampaignActionRequest;
use App\Models\AdCampaign;
use App\Models\Seller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AdCampaignDashboardService;
use App\Services\AdCampaignService;
use App\Services\CurrencyService;
use App\Services\WalletService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdCampaignController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $viewPermission = false;

    protected bool $approvePermission = false;

    protected bool $rejectPermission = false;

    protected bool $forceStopPermission = false;

    protected bool $dashboardViewPermission = false;

    public function __construct(
        protected AdCampaignService $adCampaignService,
        protected AdCampaignDashboardService $dashboardService,
        protected WalletService $walletService,
        protected CurrencyService $currencyService,
    ) {
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::AD_CAMPAIGN_VIEW());
        $this->approvePermission = $this->hasPermission(AdminPermissionEnum::AD_CAMPAIGN_APPROVE());
        $this->rejectPermission = $this->hasPermission(AdminPermissionEnum::AD_CAMPAIGN_REJECT());
        $this->forceStopPermission = $this->hasPermission(AdminPermissionEnum::AD_CAMPAIGN_FORCE_STOP());
        $this->dashboardViewPermission = $this->hasPermission(AdminPermissionEnum::AD_CAMPAIGN_DASHBOARD_VIEW());
    }

    /**
     * All campaigns — filterable by status.
     */
    public function index(Request $request): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'product', 'name' => 'product', 'title' => __('labels.ad_campaign_product')],
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller')],
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
            AdCampaignStatusEnum::FORCE_STOPPED() => __('labels.force_stopped'),
            AdCampaignStatusEnum::REJECTED() => __('labels.rejected'),
        ];

        $campaignInsights = [
            'total' => AdCampaign::count(),
            'running' => AdCampaign::where('status', AdCampaignStatusEnum::RUNNING())->count(),
            'pending' => AdCampaign::where('status', AdCampaignStatusEnum::PENDING_APPROVAL())->count(),
            'rejected' => AdCampaign::where('status', AdCampaignStatusEnum::REJECTED())->count(),
        ];

        return view('admin.ads.campaigns.index', [
            'columns' => $columns,
            'statusOptions' => $statusOptions,
            'approvePermission' => $this->approvePermission,
            'rejectPermission' => $this->rejectPermission,
            'forceStopPermission' => $this->forceStopPermission,
            'campaignInsights' => $campaignInsights,
        ]);
    }

    /**
     * Approve, reject, or force-stop a campaign.
     */
    public function action(AdminAdCampaignActionRequest $request, int $id): JsonResponse
    {
        $admin = auth()->user();
        $action = $request->validated()['action'];
        $reason = $request->validated()['reason'] ?? '';

        try {
            $result = match ($action) {
                'approve' => $this->handleApprove($id, $admin),
                'reject' => $this->handleReject($id, $admin, $reason),
                'force_stop' => $this->handleForceStop($id, $admin, $reason),
            };

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'],
            );
        } catch (\Exception $e) {
            Log::error('Admin AdCampaignController::action failed', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * DataTable JSON for admin campaign listing.
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 5;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';

        $sortableColumns = ['id', 'id', 'id', 'status', 'budget', 'created_at', 'id'];
        $orderColumn = $sortableColumns[$orderColumnIndex] ?? 'created_at';

        $query = AdCampaign::query()->with(['product.media', 'seller.user', 'stats']);

        $totalRecords = AdCampaign::count();

        // Status filter
        $statusFilter = $request->get('status');
        if (! empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        // Seller filter
        $sellerFilter = $request->get('seller_id');
        if (! empty($sellerFilter)) {
            $query->where('seller_id', $sellerFilter);
        }

        // Search filter
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('title', 'like', "%{$searchValue}%"));
            });
        }

        $filteredRecords = $query->count();

        $approvePermission = $this->approvePermission;
        $rejectPermission = $this->rejectPermission;
        $forceStopPermission = $this->forceStopPermission;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($campaign) use ($approvePermission, $rejectPermission, $forceStopPermission) {
                $status = $campaign->status;
                $progress = $campaign->spend_progress;
                $totalClicks = $campaign->stats->sum('clicks');
                $totalImpr = $campaign->stats->sum('impressions');

                $statusVal = $campaign->status?->value ?? (string) $campaign->status;
                $requiresAction = $statusVal === 'pending_approval';
                $actionDot = $requiresAction ? '<span class="status-dot status-dot-animated bg-danger me-1" title="'.e(__('labels.action_required') ?? 'Action Required').'"></span>' : '';
                $formattedId = $actionDot ? '<div class="d-inline-flex align-items-center">'.$actionDot.'<span>#'.e($campaign->id).'</span></div>' : '#'.e($campaign->id);

                return [
                    'id' => $formattedId,
                    'product' => view('admin.ads.campaigns._product_cell', compact('campaign'))->render(),
                    'seller' => $campaign->seller?->user?->name ?? '—',
                    'status' => view('admin.ads.campaigns._status_cell', compact('campaign', 'status'))->render(),
                    'budget' => view('admin.ads.campaigns._budget_cell', compact('campaign', 'progress'))->render(),
                    'clicks' => number_format($totalClicks),
                    'impressions' => number_format($totalImpr),
                    'created_at' => $campaign->created_at->format('d M Y'),
                    'action' => view('admin.ads.campaigns._action_cell', compact('campaign', 'status', 'approvePermission', 'rejectPermission', 'forceStopPermission'))->render(),
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

    /**
     * Ad campaign statistics dashboard.
     */
    public function dashboard(): View
    {
        abort_unless($this->dashboardViewPermission, 403, __('labels.unauthorized_access'));

        $data = $this->dashboardService->getDashboardData(sellerId: null, days: 7);

        return view('admin.ads.campaigns.dashboard', [
            'data' => $data,
        ]);
    }

    /**
     * Pending ad-wallet topups that need manual admin approval.
     */
    public function topups(): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $columns = [
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller')],
            ['data' => 'wallet_balance', 'name' => 'wallet_balance', 'title' => __('labels.ad_wallet_balance')],
            ['data' => 'total_spent', 'name' => 'total_spent', 'title' => __('labels.spent')],
            ['data' => 'running_ads', 'name' => 'running_ads', 'title' => __('labels.running_ads')],
            ['data' => 'total_campaigns', 'name' => 'total_campaigns', 'title' => __('labels.ad_campaigns')],
            ['data' => 'last_topup_at', 'name' => 'last_topup_at', 'title' => __('labels.last_topup_at')],
        ];

        $activityOptions = [
            '' => __('labels.all').' '.__('labels.seller'),
            'running_ads' => __('labels.running_ads'),
            'no_running_ads' => __('labels.no_running_ads'),
            'wallet_balance' => __('labels.has_wallet_balance'),
        ];

        return view('admin.ads.topups.index', compact('columns', 'activityOptions'));
    }

    /**
     * Pending ad-wallet topups that need manual admin approval.
     */
    public function topupTransactions(Request $request): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $paymentMethods = $this->adTopupPaymentMethods();
        $selectedSeller = $this->findSelectedSeller($request);

        return view('admin.ads.topups.transactions', compact('columns', 'paymentMethods', 'selectedSeller'));
    }

    /**
     * Full ad-wallet topup transaction history.
     */
    public function topupTransactionHistory(Request $request): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];

        $paymentMethods = $this->adTopupPaymentMethods();
        $statusOptions = [
            '' => __('labels.all').' '.__('labels.status'),
            WalletTransactionStatusEnum::PENDING() => __('labels.pending'),
            WalletTransactionStatusEnum::COMPLETED() => __('labels.completed'),
            WalletTransactionStatusEnum::FAILED() => __('labels.failed'),
            WalletTransactionStatusEnum::CANCELLED() => __('labels.cancelled'),
        ];
        $selectedSeller = $this->findSelectedSeller($request);

        return view('admin.ads.topups.history', compact('columns', 'paymentMethods', 'statusOptions', 'selectedSeller'));
    }

    /**
     * DataTable JSON for ad-wallet seller overview.
     */
    public function topupsDatatable(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));
        $sellerId = (int) $request->get('seller_id', 0);
        $activityFilter = trim((string) $request->get('activity_filter', ''));

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 5;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $columns = ['seller_name', 'wallet_balance', 'total_spent', 'running_ads', 'total_campaigns', 'last_topup_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'last_topup_at';

        $query = Seller::query()
            ->with(['user', 'wallet'])
            ->select('sellers.*')
            ->leftJoin('users', 'users.id', '=', 'sellers.user_id')
            ->leftJoin('wallets as ad_wallets', function ($join) {
                $join->on('ad_wallets.user_id', '=', 'sellers.user_id')
                    ->where('ad_wallets.type', WalletTypeEnum::SELLER_AD);
            })
            ->leftJoinSub(
                AdCampaign::query()
                    ->selectRaw('seller_id, COALESCE(SUM(spent), 0) as total_spent, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as running_ads, COUNT(*) as total_campaigns', [AdCampaignStatusEnum::RUNNING()])
                    ->groupBy('seller_id'),
                'campaign_stats',
                fn ($join) => $join->on('campaign_stats.seller_id', '=', 'sellers.id')
            )
            ->leftJoinSub(
                WalletTransaction::query()
                    ->selectRaw('user_id, MAX(created_at) as last_topup_at')
                    ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT())
                    ->whereHas('wallet', fn ($walletQuery) => $walletQuery->where('type', WalletTypeEnum::SELLER_AD()))
                    ->groupBy('user_id'),
                'topup_stats',
                fn ($join) => $join->on('topup_stats.user_id', '=', 'sellers.user_id')
            )
            ->addSelect([
                'seller_name' => \DB::raw('users.name'),
                'wallet_balance' => \DB::raw('COALESCE(ad_wallets.balance, 0)'),
                'total_spent' => \DB::raw('COALESCE(campaign_stats.total_spent, 0)'),
                'running_ads' => \DB::raw('COALESCE(campaign_stats.running_ads, 0)'),
                'total_campaigns' => \DB::raw('COALESCE(campaign_stats.total_campaigns, 0)'),
                'last_topup_at' => \DB::raw('topup_stats.last_topup_at'),
            ]);

        $totalRecords = (clone $query)->count();

        if ($sellerId > 0) {
            $query->where('sellers.id', $sellerId);
        }

        if ($activityFilter === 'running_ads') {
            $query->whereRaw('COALESCE(campaign_stats.running_ads, 0) > 0');
        } elseif ($activityFilter === 'no_running_ads') {
            $query->whereRaw('COALESCE(campaign_stats.running_ads, 0) = 0');
        } elseif ($activityFilter === 'wallet_balance') {
            $query->whereRaw('COALESCE(ad_wallets.balance, 0) > 0');
        }

        if ($searchValue !== '') {
            $query->where(function ($sellerQuery) use ($searchValue) {
                $sellerQuery->where('users.name', 'like', "%{$searchValue}%")
                    ->orWhere('users.email', 'like', "%{$searchValue}%")
                    ->orWhere('users.mobile', 'like', "%{$searchValue}%");
            });
        }

        $filteredRecords = (clone $query)->count();

        $sellers = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();
        $data = $sellers->map(function (Seller $seller) {
            return [
                'seller' => view('admin.ads.topups._seller_overview_cell', compact('seller'))->render(),
                'wallet_balance' => $this->currencyService->format((float) ($seller->wallet_balance ?? 0)),
                'total_spent' => $this->currencyService->format((float) ($seller->total_spent ?? 0)),
                'running_ads' => (int) ($seller->running_ads ?? 0),
                'total_campaigns' => (int) ($seller->total_campaigns ?? 0),
                'last_topup_at' => $seller->last_topup_at ? Carbon::parse($seller->last_topup_at)->format('Y-m-d H:i:s') : 'N/A',
            ];
        });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * DataTable JSON for pending ad-wallet topups.
     */
    public function topupTransactionsDatatable(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));
        $sellerId = (int) $request->get('seller_id', 0);
        $paymentMethod = trim((string) $request->get('payment_method', ''));

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 4;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $columns = ['id', 'user_id', 'amount', 'payment_method', 'created_at', 'id'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

        $query = $this->adTopupTransactionQuery()
            ->where('status', WalletTransactionStatusEnum::PENDING());

        $totalRecords = (clone $query)->count();

        if ($sellerId > 0) {
            $sellerUserId = Seller::query()->where('id', $sellerId)->value('user_id');
            $query->where('user_id', $sellerUserId ?: 0);
        }

        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($searchValue !== '') {
            $query->where(function ($walletTransactionQuery) use ($searchValue) {
                $walletTransactionQuery->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('amount', 'like', "%{$searchValue}%")
                    ->orWhere('payment_method', 'like', "%{$searchValue}%")
                    ->orWhere('transaction_reference', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($userQuery) use ($searchValue) {
                        $userQuery->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%")
                            ->orWhere('mobile', 'like', "%{$searchValue}%");
                    });
            });
        }

        $filteredRecords = (clone $query)->count();

        $transactions = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();
        $sellerMetrics = $this->adSellerMetricsForTransactions($transactions);

        $data = $transactions->map(function (WalletTransaction $transaction) use ($sellerMetrics) {
            $metrics = $sellerMetrics[$transaction->user_id] ?? null;

            return [
                'id' => $transaction->id,
                'seller' => view('admin.ads.topups._seller_cell', [
                    'transaction' => $transaction,
                    'metrics' => $metrics,
                ])->render(),
                'amount' => $this->currencyService->format($transaction->amount).'<p class="mt-2 mb-0 fs-5">'.__('labels.transaction_reference').' - '.e($transaction->transaction_reference ?? '-').' </p>',
                'payment_method' => e(Str::headline($transaction->payment_method ?? 'N/A')),
                'created_at' => $transaction->created_at?->format('Y-m-d H:i:s'),
                'action' => view('admin.ads.topups._transaction_action_cell', [
                    'transaction' => $transaction,
                    'amount' => $this->currencyService->format($transaction->amount),
                ])->render(),
            ];
        });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * DataTable JSON for full ad-wallet topup transaction history.
     */
    public function topupTransactionHistoryDatatable(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));
        $sellerId = (int) $request->get('seller_id', 0);
        $paymentMethod = trim((string) $request->get('payment_method', ''));
        $statusFilter = trim((string) $request->get('status', ''));

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 5;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $columns = ['id', 'user_id', 'amount', 'status', 'payment_method', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

        $query = $this->adTopupTransactionQuery();

        $totalRecords = (clone $query)->count();

        if ($sellerId > 0) {
            $sellerUserId = Seller::query()->where('id', $sellerId)->value('user_id');
            $query->where('user_id', $sellerUserId ?: 0);
        }

        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if ($searchValue !== '') {
            $query->where(function ($walletTransactionQuery) use ($searchValue) {
                $walletTransactionQuery->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('amount', 'like', "%{$searchValue}%")
                    ->orWhere('payment_method', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%")
                    ->orWhere('transaction_reference', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($userQuery) use ($searchValue) {
                        $userQuery->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%")
                            ->orWhere('mobile', 'like', "%{$searchValue}%");
                    });
            });
        }

        $filteredRecords = (clone $query)->count();

        $transactions = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();
        $sellerMetrics = $this->adSellerMetricsForTransactions($transactions);

        $data = $transactions->map(function (WalletTransaction $transaction) use ($sellerMetrics) {
            $metrics = $sellerMetrics[$transaction->user_id] ?? null;

            return [
                'id' => $transaction->id,
                'seller' => view('admin.ads.topups._seller_cell', [
                    'transaction' => $transaction,
                    'metrics' => $metrics,
                ])->render(),
                'amount' => $this->currencyService->format($transaction->amount).'<p class="mt-2 mb-0 fs-5">'.__('labels.transaction_reference').' - '.e($transaction->transaction_reference ?? '-').' </p>',
                'status' => view('partials.status', ['status' => $transaction->status])->render(),
                'payment_method' => e(Str::headline($transaction->payment_method ?? 'N/A')),
                'created_at' => $transaction->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'draw' => (int) $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Process a pending ad-wallet topup.
     */
    public function processTopup(Request $request, int $id): JsonResponse
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:500',
        ]);

        $approve = $request->input('action') === 'approve';
        $note = $approve ? __('labels.deposit_approve_by_admin') : __('labels.deposit_rejected_by_admin');

        $result = $this->walletService->finalizeDeposit($id, $approve, $note);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * AJAX endpoint for dashboard period changes.
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        abort_unless($this->dashboardViewPermission, 403, __('labels.unauthorized_access'));

        try {
            $days = (int) $request->input('days', 7);
            $data = $this->dashboardService->getDashboardData(sellerId: null, days: $days);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Admin ad dashboard data failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => __('labels.something_went_wrong')], 500);
        }
    }

    // ---------- private helpers ----------

    private function handleApprove(int $id, $admin): array
    {
        abort_unless($this->approvePermission, 403, __('labels.unauthorized_access'));

        return $this->adCampaignService->approve($id, $admin);
    }

    private function handleReject(int $id, $admin, string $reason): array
    {
        abort_unless($this->rejectPermission, 403, __('labels.unauthorized_access'));

        return $this->adCampaignService->reject($id, $admin, $reason);
    }

    private function handleForceStop(int $id, $admin, string $reason): array
    {
        abort_unless($this->forceStopPermission, 403, __('labels.unauthorized_access'));

        return $this->adCampaignService->forceStop($id, $admin, $reason);
    }

    private function adTopupPaymentMethods(): array
    {
        return WalletTransaction::query()
            ->whereHas('wallet', fn ($walletQuery) => $walletQuery->where('type', WalletTypeEnum::SELLER_AD()))
            ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT())
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->filter()
            ->mapWithKeys(fn (string $method) => [$method => Str::headline($method)])
            ->all();
    }

    private function adTopupTransactionQuery()
    {
        return WalletTransaction::query()
            ->with(['user', 'wallet'])
            ->whereHas('wallet', fn ($walletQuery) => $walletQuery->where('type', WalletTypeEnum::SELLER_AD()))
            ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT());
    }

    private function findSelectedSeller(Request $request): ?Seller
    {
        $selectedSellerId = (int) $request->integer('seller_id');
        if ($selectedSellerId <= 0) {
            return null;
        }

        return Seller::query()
            ->with('user:id,name')
            ->find($selectedSellerId, ['id', 'user_id']);
    }

    private function adSellerMetricsForTransactions($transactions): array
    {
        $userIds = $transactions->pluck('user_id')->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return [];
        }

        $sellerIdsByUser = Seller::query()
            ->whereIn('user_id', $userIds)
            ->pluck('id', 'user_id');

        $walletBalances = Wallet::query()
            ->whereIn('user_id', $userIds)
            ->where('type', WalletTypeEnum::SELLER_AD())
            ->pluck('balance', 'user_id');

        $campaignMetrics = AdCampaign::query()
            ->selectRaw('seller_id, COALESCE(SUM(spent), 0) as total_spent, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as running_ads', [AdCampaignStatusEnum::RUNNING()])
            ->whereIn('seller_id', $sellerIdsByUser->values())
            ->groupBy('seller_id')
            ->get()
            ->keyBy('seller_id');

        $metrics = [];
        foreach ($userIds as $userId) {
            $sellerId = $sellerIdsByUser[$userId] ?? null;
            $campaignMetric = $sellerId ? $campaignMetrics->get($sellerId) : null;
            $metrics[$userId] = [
                'wallet_balance' => (float) ($walletBalances[$userId] ?? 0),
                'total_spent' => (float) ($campaignMetric->total_spent ?? 0),
                'running_ads' => (int) ($campaignMetric->running_ads ?? 0),
            ];
        }

        return $metrics;
    }
}
