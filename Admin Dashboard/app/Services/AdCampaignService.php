<?php

namespace App\Services;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdPlacementEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\AdCampaignStatusNotification;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdCampaignService
{
    public function __construct(
        protected AdWalletService $adWalletService,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Seller actions
    // -------------------------------------------------------------------------

    /**
     * Create and submit a campaign for admin approval.
     * Checks feature flag and wallet balance before persisting.
     */
    public function create(int $userId, int $sellerId, array $data): array
    {
        if (!$this->adWalletService->isFeatureEnabled()) {
            return $this->fail('labels.ad_feature_disabled');
        }

        $settings = $this->adWalletService->getSettings();
        $cpcRate = (float)($settings['cpcRate'] ?? 0);

        if ($cpcRate <= 0) {
            return $this->fail('labels.ad_cpc_rate_not_configured');
        }

        $budget = (float)$data['budget'];

        try {
            return DB::transaction(function () use ($userId, $sellerId, $data, $budget, $cpcRate) {
                // Lock wallet and verify balance
                $wallet = Wallet::where('user_id', $userId)
                    ->where('type', WalletTypeEnum::SELLER_AD())
                    ->lockForUpdate()
                    ->first();

                if (!$wallet || (float)$wallet->balance < $budget) {
                    return $this->fail('labels.ad_insufficient_wallet_balance');
                }

                // Deduct budget from ad wallet immediately on campaign approval submission
                $wallet->balance = (float)$wallet->balance - $budget;
                $wallet->save();

                $transaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $userId,
                    'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                    'payment_method' => 'ad_campaign_budget',
                    'amount' => $budget,
                    'currency_code' => $wallet->currency_code,
                    'status' => WalletTransactionStatusEnum::COMPLETED(),
                    'description' => __('labels.ad_campaign_budget_deducted'),
                ]);

                $campaign = AdCampaign::create([
                    'seller_id' => $sellerId,
                    'product_id' => $data['product_id'],
                    'budget' => $budget,
                    'spent' => 0,
                    'cpc_rate_snapshot' => $cpcRate,
                    'placements' => AdCampaign::defaultPlacements(),
                    'status' => AdCampaignStatusEnum::PENDING_APPROVAL(),
                    'wallet_transaction_id' => $transaction->id,
                ]);

                return [
                    'success' => true,
                    'message' => 'labels.ad_campaign_submitted_for_approval',
                    'data' => ['campaign' => $campaign],
                ];
            });
        } catch (Exception $e) {
            Log::error('AdCampaignService::create failed', ['error' => $e->getMessage()]);
            return $this->fail('labels.something_went_wrong');
        }
    }

    /**
     * Seller voluntarily pauses a running campaign.
     */
    public function pause(int $campaignId, int $sellerId): array
    {
        try {
            $campaign = $this->resolveSellerCampaign($campaignId, $sellerId);

            if ($campaign->status !== AdCampaignStatusEnum::RUNNING) {
                return $this->fail('labels.ad_campaign_cannot_pause');
            }

            $campaign->update(['status' => AdCampaignStatusEnum::PAUSED()]);

            return $this->ok('labels.ad_campaign_paused', $campaign);
        } catch (Exception $e) {
            Log::error('AdCampaignService::pause failed', ['error' => $e->getMessage()]);
            return $this->fail('labels.something_went_wrong');
        }
    }

    /**
     * Seller resumes a paused campaign (only seller-paused; admin-paused requires admin).
     */
    public function resume(int $campaignId, int $sellerId): array
    {
        try {
            $campaign = $this->resolveSellerCampaign($campaignId, $sellerId);

            if ($campaign->status !== AdCampaignStatusEnum::PAUSED) {
                return $this->fail('labels.ad_campaign_cannot_resume');
            }

            $campaign->update(['status' => AdCampaignStatusEnum::RUNNING()]);

            return $this->ok('labels.ad_campaign_resumed', $campaign);
        } catch (Exception $e) {
            Log::error('AdCampaignService::resume failed', ['error' => $e->getMessage()]);
            return $this->fail('labels.something_went_wrong');
        }
    }

    // -------------------------------------------------------------------------
    // Admin actions
    // -------------------------------------------------------------------------

    /**
     * Admin approves a pending campaign — transitions it to RUNNING.
     */
    public function approve(int $campaignId, User $admin): array
    {
        try {
            $campaign = AdCampaign::pending()->findOrFail($campaignId);

            $campaign->update([
                'status' => AdCampaignStatusEnum::RUNNING(),
                'approved_by' => $admin->id,
            ]);

            $this->notifySeller($campaign, 'approve');

            return $this->ok(__('labels.ad_campaign_approved'), $campaign);
        } catch (Exception $e) {
            Log::error('AdCampaignService::approve failed', ['error' => $e->getMessage()]);
            return $this->fail(__('labels.something_went_wrong'));
        }
    }

    /**
     * Admin rejects a pending campaign and refunds the budget.
     */
    public function reject(int $campaignId, User $admin, string $reason): array
    {
        try {
            return DB::transaction(function () use ($campaignId, $admin, $reason) {
                $campaign = AdCampaign::pending()->lockForUpdate()->findOrFail($campaignId);

                $campaign->update([
                    'status' => AdCampaignStatusEnum::REJECTED(),
                    'approved_by' => $admin->id,
                    'rejection_reason' => $reason,
                ]);

                // Refund the full budget back to the seller's ad wallet
                $this->refundBudget($campaign, __('labels.ad_campaign_refund_on_rejection'));

                $this->notifySeller($campaign, 'reject', $reason);

                return $this->ok(__('labels.ad_campaign_rejected'), $campaign->fresh());
            });
        } catch (Exception $e) {
            Log::error('AdCampaignService::reject failed', ['error' => $e->getMessage()]);
            return $this->fail(__('labels.something_went_wrong'));
        }
    }

    /**
     * Admin force-stops any non-terminal campaign and refunds remaining budget.
     */
    public function forceStop(int $campaignId, User $admin, string $reason): array
    {
        try {
            return DB::transaction(function () use ($campaignId, $admin, $reason) {
                $campaign = AdCampaign::lockForUpdate()->findOrFail($campaignId);

                if (in_array($campaign->status->value, array_map(
                    fn($s) => (string)$s,
                    AdCampaignStatusEnum::terminal(),
                ), true)) {
                    return $this->fail(__('labels.ad_campaign_already_terminal'));
                }

                $campaign->update([
                    'status' => AdCampaignStatusEnum::FORCE_STOPPED(),
                    'force_stopped_by' => $admin->id,
                    'force_stop_reason' => $reason,
                    'force_stopped_at' => now(),
                ]);

                // Refund unspent budget
                $remaining = (float)$campaign->budget - (float)$campaign->spent;
                if ($remaining > 0) {
                    $this->refundBudget($campaign, __('labels.ad_campaign_refund_on_force_stop'), $remaining);
                }

                $this->notifySeller($campaign, 'force_stop', $reason);

                return $this->ok(__('labels.ad_campaign_force_stopped'), $campaign->fresh());
            });
        } catch (Exception $e) {
            Log::error('AdCampaignService::forceStop failed', ['error' => $e->getMessage()]);
            return $this->fail(__('labels.something_went_wrong'));
        }
    }

    // -------------------------------------------------------------------------
    // Query helpers for controllers
    // -------------------------------------------------------------------------

    /**
     * Paginated campaign list for the seller dashboard.
     */
    public function sellerCampaigns(int $sellerId, array $filters = [])
    {
        return AdCampaign::forSeller($sellerId)
            ->with(['product.media', 'stats'])
            ->when(!empty($filters['status']), fn(Builder $q) => $q->where('status', $filters['status']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Paginated campaign list for the admin panel.
     */
    public function adminCampaigns(array $filters = [])
    {
        return AdCampaign::with(['product', 'seller', 'approvedBy'])
            ->when(!empty($filters['status']), fn(Builder $q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['seller_id']), fn(Builder $q) => $q->where('seller_id', $filters['seller_id']))
            ->when(!empty($filters['search']), function (Builder $q) use ($filters) {
                $q->whereHas('product', fn($pq) => $pq->where('name', 'like', "%{$filters['search']}%"));
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveSellerCampaign(int $campaignId, int $sellerId): AdCampaign
    {
        return AdCampaign::forSeller($sellerId)->findOrFail($campaignId);
    }

    /**
     * Credit the seller's ad wallet (budget refund on rejection / force-stop).
     */
    private function refundBudget(AdCampaign $campaign, string $description, ?float $amount = null): void
    {
        $refundAmount = $amount ?? (float)$campaign->budget;

        if ($refundAmount <= 0) {
            return;
        }

        $wallet = Wallet::where('user_id', $campaign->seller->user_id)
            ->where('type', WalletTypeEnum::SELLER_AD())
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            Log::warning('AdCampaignService::refundBudget — ad wallet not found', [
                'campaign_id' => $campaign->id,
            ]);
            return;
        }

        $wallet->increment('balance', $refundAmount);

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'transaction_type' => WalletTransactionTypeEnum::REFUND(),
            'payment_method' => 'ad_campaign_refund',
            'amount' => $refundAmount,
            'currency_code' => $wallet->currency_code,
            'status' => WalletTransactionStatusEnum::COMPLETED(),
            'description' => $description,
            'transaction_reference' => 'campaign:' . $campaign->id,
        ]);
    }

    private function notifySeller(AdCampaign $campaign, string $action, ?string $reason = null): void
    {
        try {
            $campaign->loadMissing(['seller.user', 'product']);
            $sellerUser = $campaign->seller?->user;

            if ($sellerUser) {
                $sellerUser->notify(new AdCampaignStatusNotification($campaign, $action, $reason));
            }
        } catch (Exception $e) {
            Log::error('AdCampaignService::notifySeller failed', ['error' => $e->getMessage(), 'campaign_id' => $campaign->id]);
        }
    }

    private function fail(string $messageKey): array
    {
        return ['success' => false, 'message' => $messageKey, 'data' => []];
    }

    private function ok(string $messageKey, mixed $data = []): array
    {
        return ['success' => true, 'message' => $messageKey, 'data' => is_array($data) ? $data : ['campaign' => $data]];
    }
}
