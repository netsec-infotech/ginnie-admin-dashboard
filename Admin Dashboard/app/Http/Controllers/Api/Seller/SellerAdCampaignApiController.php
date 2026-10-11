<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdPlacementEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\CreateAdCampaignRequest;
use App\Http\Resources\Advertisement\AdCampaignResource;
use App\Models\AdCampaign;
use App\Models\Seller;
use App\Services\AdCampaignService;
use App\Services\AdWalletService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group('Seller Ad Campaigns')]
class SellerAdCampaignApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AdCampaignService $adCampaignService,
        protected AdWalletService $adWalletService,
    ) {
    }

    /**
     * List ad campaigns for the authenticated seller with pagination and filters.
     *
     * @return JsonResponse
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search by product title.', type: 'string', example: 'smartphone')]
    #[QueryParameter('status', description: 'Filter by campaign status.', type: 'string', example: 'draft, pending_approval, approved, rejected, running, paused, paused_by_admin, completed, force_stopped')]
    public function index(Request $request): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $perPage = (int) $request->input('per_page', 15);
            $search  = trim((string) $request->input('search', ''));
            $status  = $request->input('status');

            $query = AdCampaign::forSeller($seller->id)
                ->with(['product.media', 'stats']);

            if ($search !== '') {
                $query->whereHas('product', fn ($q) => $q->where('title', 'like', "%{$search}%"));
            }

            if ($status !== null && $status !== '') {
                $query->where('status', $status);
            }

            $paginator = $query->orderByDesc('id')->paginate($perPage);
            $paginator->getCollection()->transform(fn ($campaign) => new AdCampaignResource($campaign));

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.ad_campaigns_fetched_successfully',
                ApiResponseType::responseFromPaginator($paginator),
            );
        } catch (Throwable $e) {
            Log::error('SellerAdCampaignApiController::index failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Show a single ad campaign owned by the authenticated seller.
     *
     * @param int $id Ad campaign ID.
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $campaign = AdCampaign::forSeller($seller->id)
                ->with(['product.media', 'stats'])
                ->findOrFail($id);

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.ad_campaign_fetched_successfully',
                new AdCampaignResource($campaign),
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.ad_campaign_not_found', [], 404);
        } catch (Throwable $e) {
            Log::error('SellerAdCampaignApiController::show failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Create a new ad campaign — deducts budget from ad wallet and submits for admin approval.
     *
     * @return JsonResponse
     */
    public function store(CreateAdCampaignRequest $request): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adCampaignService->create(
                userId: $seller->user_id,
                sellerId: $seller->id,
                data: $request->validated(),
            );

            $data = [];
            if ($result['success'] && isset($result['data']['campaign'])) {
                $campaign = $result['data']['campaign']->load(['product.media', 'stats']);
                $data = new AdCampaignResource($campaign);
            }

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $data,
                status: $result['success'] ? 201 : 422,
            );
        } catch (Throwable $e) {
            Log::error('SellerAdCampaignApiController::store failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Pause a running campaign.
     *
     * @param int $id Ad campaign ID.
     * @return JsonResponse
     */
    public function pause(int $id): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adCampaignService->pause($id, $seller->id);

            $data = [];
            if ($result['success'] && isset($result['data']['campaign'])) {
                $data = new AdCampaignResource($result['data']['campaign']->load(['product.media', 'stats']));
            }

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $data,
            );
        } catch (Throwable $e) {
            Log::error('SellerAdCampaignApiController::pause failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Resume a paused campaign.
     *
     * @param int $id Ad campaign ID.
     * @return JsonResponse
     */
    public function resume(int $id): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->adCampaignService->resume($id, $seller->id);

            $data = [];
            if ($result['success'] && isset($result['data']['campaign'])) {
                $data = new AdCampaignResource($result['data']['campaign']->load(['product.media', 'stats']));
            }

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $data,
            );
        } catch (Throwable $e) {
            Log::error('SellerAdCampaignApiController::resume failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Enum options for building the campaign form on clients.
     *
     * @return JsonResponse
     */
    public function config(): JsonResponse
    {
        try {
            $seller = $this->resolveSeller();
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $settings = $this->adWalletService->getSettings();
            $featureEnabled = $this->adWalletService->isFeatureEnabled();

            $walletBalance = 0.0;
            try {
                $wallet = $this->adWalletService->getAdWallet($seller->user_id);
                $walletBalance = (float) ($wallet['balance'] ?? 0);
            } catch (\Exception $e) {
                Log::warning('SellerAdCampaignApiController::enums — wallet fetch failed', ['error' => $e->getMessage()]);
            }

            return ApiResponseType::sendJsonResponse(
                true,
                __('labels.ad_campaign_enums_fetched_successfully'),
                [
                    'feature_enabled' => $featureEnabled,
                    'wallet_balance'  => $walletBalance,
                    'cpc_rate'        => (float) ($settings['cpcRate'] ?? 0),
                    'min_budget'      => (float) ($settings['minBudget'] ?? 1),
                    'max_budget'      => (float) ($settings['maxBudget'] ?? 5000),
                    'impression_multiplier_min' => (int) ($settings['impressionMultiplierMin'] ?? 12),
                    'impression_multiplier_max' => (int) ($settings['impressionMultiplierMax'] ?? 20),
                ],
            );
        } catch (Throwable $e) {
            Log::error('SellerAdCampaignApiController::enums failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), [], 500);
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
