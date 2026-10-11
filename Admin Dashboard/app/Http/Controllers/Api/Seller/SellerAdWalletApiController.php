<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\TopUpAdWalletRequest;
use App\Http\Requests\Advertisement\TransferFromEarningWalletRequest;
use App\Http\Resources\User\WalletResource;
use App\Http\Resources\User\WalletTransactionResource;
use App\Models\Seller;
use App\Services\AdWalletService;
use App\Services\WalletService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group('Seller Ad Wallet')]
class SellerAdWalletApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AdWalletService $adWalletService,
        protected WalletService $walletService,
    ) {
    }

    /**
     * Get the authenticated seller's ad wallet balance and details.
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adWalletService->getAdWallet($seller->user_id);

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(false, $result['message'], $result['data'] ?? []);
            }

            $wallet = $result['data'];
            $settings = $this->adWalletService->getSettings();

            return ApiResponseType::sendJsonResponse(true, 'labels.ad_wallet_fetched_successfully', [
                'wallet'          => new WalletResource($wallet),
                'feature_enabled' => $this->adWalletService->isFeatureEnabled(),
                'min_topup'       => $this->adWalletService->getMinTopUp(),
                'currency_symbol' => $settings['currencySymbol'] ?? '',
            ]);
        } catch (Throwable $e) {
            Log::error('SellerAdWalletApiController::show failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Top up ad wallet via a payment gateway.
     *
     * @return JsonResponse
     */
    public function topupGateway(TopUpAdWalletRequest $request): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adWalletService->prepareGatewayTopUp(
                $seller->user_id,
                $request->validated(),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
                status: $result['success'] ? 200 : 422,
            );
        } catch (Throwable $e) {
            Log::error('SellerAdWalletApiController::topupGateway failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Transfer funds from the seller's earnings wallet into the ad wallet.
     *
     * @return JsonResponse
     */
    public function topupFromEarnings(TransferFromEarningWalletRequest $request): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adWalletService->transferFromEarningWallet(
                $seller->user_id,
                (float) $request->validated('amount'),
            );

            $data = $result['data'] ?? [];
            if ($result['success']) {
                $data = [
                    'earning_wallet' => isset($data['earning_wallet']) ? new WalletResource($data['earning_wallet']) : null,
                    'ad_wallet'      => isset($data['ad_wallet']) ? new WalletResource($data['ad_wallet']) : null,
                ];
            }

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $data,
            );
        } catch (Throwable $e) {
            Log::error('SellerAdWalletApiController::topupFromEarnings failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * List ad wallet transactions with pagination.
     *
     * @return JsonResponse
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of transactions per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('transaction_type', description: 'Filter by transaction type.', type: 'string', example: 'deposit,payment')]
    #[QueryParameter('status', description: 'Filter by transaction status.', type: 'string', example: 'completed,pending,failed,cancelled')]
    public function transactions(Request $request): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adWalletService->getTransactions($seller->user_id, $request->all());

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(false, $result['message'], $result['data'] ?? []);
            }

            $transactions = $result['data'];
            $transactions->getCollection()->transform(fn ($txn) => new WalletTransactionResource($txn));

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.ad_wallet_transactions_fetched_successfully',
                ApiResponseType::responseFromPaginator($transactions),
            );
        } catch (Throwable $e) {
            Log::error('SellerAdWalletApiController::transactions failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Get a single ad wallet transaction by ID.
     *
     * @param int $id Transaction ID.
     * @return JsonResponse
     */
    public function transaction(int $id): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->walletService->getTransaction(
                $seller->user_id,
                $id,
                \App\Enums\Wallet\WalletTypeEnum::SELLER_AD(),
            );

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(false, $result['message'], $result['data'] ?? [], 404);
            }

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.ad_wallet_transaction_fetched_successfully',
                new WalletTransactionResource($result['data']),
            );
        } catch (Throwable $e) {
            Log::error('SellerAdWalletApiController::transaction failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Resolve the seller model for the authenticated user.
     */
    private function resolveSeller(): ?Seller
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $seller = Seller::where('user_id', $user->id)->first();
        if (! $seller) {
            $seller = $user->seller();
        }

        return $seller;
    }
}
