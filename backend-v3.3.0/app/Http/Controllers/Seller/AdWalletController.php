<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\TopUpAdWalletRequest;
use App\Http\Requests\Advertisement\TransferFromEarningWalletRequest;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AdWalletService;
use App\Services\CurrencyService;
use App\Services\WalletService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

class AdWalletController extends Controller
{
    use AuthorizesRequests, PanelAware, ChecksPermissions;

    protected $seller;

    protected bool $viewPermission = false;
    protected bool $topupPermission = false;

    public function __construct(
        protected AdWalletService $adWalletService,
        protected WalletService $walletService,
        protected CurrencyService $currencyService,
    ) {
        $user = auth()->user();
        $this->seller = $user?->seller();

        if ($user) {
            $isOwner = $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->viewPermission = $isOwner || $this->hasPermission(SellerPermissionEnum::AD_WALLET_VIEW());
            $this->topupPermission = $isOwner || $this->hasPermission(SellerPermissionEnum::AD_WALLET_TOPUP());
        }
    }

    /**
     * Ad wallet landing — balance + recent activity summary.
     */
    public function index(): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $wallet = $this->resolveWallet();
        $settings = $this->adWalletService->getSettings();

        return view('seller.ads.wallet.index', [
            'wallet' => $wallet,
            'seller' => $this->seller,
            'settings' => $settings,
            'topupPermission' => $this->topupPermission,
            'featureEnabled' => (bool) ($settings['featureEnabled'] ?? false),
        ]);
    }

    /**
     * Top-up form — gateway funding or transfer from earnings wallet.
     */
    public function topupForm(): View
    {
        abort_unless($this->topupPermission, 403, __('labels.unauthorized_access'));

        $settings = $this->adWalletService->getSettings();
        if (!($settings['featureEnabled'] ?? false)) {
            abort(403, __('labels.ad_feature_disabled'));
        }

        $adWallet = $this->resolveWallet();
        $earningsWalletResult = $this->walletService->getWallet(
            $this->seller->user->id,
            WalletTypeEnum::SELLER(),
        );
        $earningsWallet = $earningsWalletResult['success'] ? $earningsWalletResult['data'] : null;

        return view('seller.ads.wallet.topup', [
            'wallet' => $adWallet,
            'earningsWallet' => $earningsWallet,
            'seller' => $this->seller,
            'settings' => $settings,
        ]);
    }

    /**
     * Top up via payment gateway. Funds land in the ad wallet on gateway callback
     * (existing payment-controller flow). We only prepare the intent here.
     */
    public function topupGateway(TopUpAdWalletRequest $request): JsonResponse
    {
        try {
            $result = $this->adWalletService->prepareGatewayTopUp(
                $this->seller->user->id,
                $request->validated(),
            );

            $data = $result['data'] ?? [];
            if (!empty($data['payment_url'])) {
                $data['redirect_url'] = $data['payment_url'];
            }

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $data,
            );
        } catch (\Throwable $e) {
            Log::error('AdWalletController::topupGateway failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Transfer money from the seller's earnings wallet into the ad wallet.
     * Atomic — both ledgers are updated in one transaction.
     */
    public function topupFromEarnings(TransferFromEarningWalletRequest $request): JsonResponse
    {
        try {
            $result = $this->adWalletService->transferFromEarningWallet(
                $this->seller->user->id,
                (float) $request->validated('amount'),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'],
            );
        } catch (\Throwable $e) {
            Log::error('AdWalletController::topupFromEarnings failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Transactions listing page (mirrors seller earning wallet pattern).
     */
    public function transactions(): View
    {
        abort_unless($this->viewPermission, 403, __('labels.unauthorized_access'));

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount')],
            ['data' => 'transaction_type', 'name' => 'transaction_type', 'title' => __('labels.transaction_type')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];

        return view('seller.ads.wallet.transactions', compact('columns'));
    }

    /**
     * DataTable JSON endpoint for ad-wallet transactions.
     */
    public function getTransactions(Request $request): JsonResponse
    {
        if (!$this->viewPermission) {
            return $this->unauthorizedResponse(__('labels.unauthorized_access'));
        }

        // DataTables sorting
        $columnIndex = $request->input('order.0.column');
        $columnName = $request->input("columns.$columnIndex.data");
        $orderDir = strtolower($request->input('order.0.dir', 'desc'));
  
        // Allow only valid sortable columns
        $allowedSortColumns = [
            'id',
            'amount',
            'transaction_type',
            'payment_method',
            'status',
            'created_at',
        ];
  
        $sort = in_array($columnName, $allowedSortColumns)
            ? $columnName
            : 'created_at';
    
        $filters = [
            'query'            => trim($request->input('search.value', '')),
            'transaction_type' => $request->input('transaction_type'),
            'status'           => $request->input('status'),
            'payment_method'   => $request->input('payment_method'),
            'min_amount'       => $request->input('min_amount'),
            'max_amount'       => $request->input('max_amount'),
            'sort'             => $sort,
            'order'            => in_array($orderDir, ['asc', 'desc']) ? $orderDir : 'desc',
            'per_page'         => (int) $request->input('length', 15),
        ];

        $result = $this->adWalletService->getTransactions(
            $this->seller->user->id,
            $filters,
        );

        if (!$result['success']) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $result['message'],
                data: $result['data'],
            );
        }

        $transactions = $result['data'];

        // Total records without search/filter
        $recordsTotal = WalletTransaction::where('user_id', $this->seller->user->id)
            ->whereHas('wallet', function ($q) {
                $q->where('type', WalletTypeEnum::SELLER_AD());
            })
            ->count();

        $data = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'amount' => $this->currencyService->format($transaction->amount),
                'transaction_type' => ucfirst(Str::replace('_', ' ', $transaction->transaction_type)),
                'payment_method' => ucfirst(Str::replace('_', ' ', $transaction->payment_method ?? '')),
                'status' => view('partials.status', ['status' => $transaction->status])->render(),
                'description' => $transaction->description ?? 'N/A',
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
            ];
        })->values()->toArray();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $transactions->total(),
            'data' => $data,
        ]);
    }

    /**
     * Show the payment status page for a wallet top-up transaction.
     */
    public function paymentStatus(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();
        $this->walletService->syncGatewayRechargeStatus($txn);
        $txn->refresh();

        return view('payments.payment-status', [
            'transaction' => $txn,
            'completedStatus' => WalletTransactionStatusEnum::COMPLETED(),
            'failedStatus' => WalletTransactionStatusEnum::FAILED(),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
                'backUrl' => route('seller.ads.wallet.index'),
                'backLabel' => __('labels.back_to_ad_wallet'),
            ],
        ]);
    }

    /**
     * Resolve the seller's ad wallet (lazy-creates on first access).
     */
    protected function resolveWallet(): ?Wallet
    {
        $result = $this->adWalletService->getAdWallet($this->seller->user->id);
        return $result['success'] ? $result['data'] : null;
    }
}
