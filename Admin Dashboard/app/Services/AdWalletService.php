<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the unified WalletService for the seller's ad-spend wallet.
 *
 * Reuses the existing `wallets` + `wallet_transactions` tables with a new
 * WalletTypeEnum::SELLER_AD type. All accounting writes are wrapped in a DB
 * transaction; every state change writes a transaction row for the audit trail.
 */
class AdWalletService
{
    public function __construct(
        protected WalletService $walletService,
        protected SettingService $settingService,
    ) {
    }

    /**
     * Get or create the seller's ad wallet.
     */
    public function getAdWallet(int $userId): array
    {
        return $this->walletService->getWallet($userId, WalletTypeEnum::SELLER_AD());
    }

    /**
     * Read paginated ad-wallet transactions for a seller.
     */
    public function getTransactions(int $userId, array $filters = []): array
    {
        return $this->walletService->getTransactions($userId, $filters, WalletTypeEnum::SELLER_AD());
    }

    /**
     * Read the live advertisement settings JSON.
     */
    public function getSettings(): array
    {
        $resource = $this->settingService->getSettingByVariable(SettingTypeEnum::ADVERTISEMENT());

        return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }

    public function isFeatureEnabled(): bool
    {
        return (bool) ($this->getSettings()['featureEnabled'] ?? false);
    }

    public function getMinTopUp(): ?float
    {
        $value = $this->getSettings()['walletMinTopup'] ?? null;
        return $value === null ? null : (float) $value;
    }

    /**
     * Initiate a gateway-funded top-up. Delegates the heavy lifting to the
     * unified WalletService — this method only enforces the master toggle and
     * pins the wallet type to SELLER_AD.
     */
    public function prepareGatewayTopUp(int $userId, array $data): array
    {
        if (!$this->isFeatureEnabled()) {
            return [
                'success' => false,
                'message' => __('labels.ad_feature_disabled'),
                'data' => [],
            ];
        }

        $minTopUp = $this->getMinTopUp();
        if ($minTopUp !== null && (float) ($data['amount'] ?? 0) < $minTopUp) {
            return [
                'success' => false,
                'message' => __('labels.ad_minimum_topup_required', ['min' => $minTopUp]),
                'data' => [],
            ];
        }

        $data['description'] = $data['description'] ?? __('labels.ad_wallet_topup_via_gateway');

        return $this->walletService->prepareWalletRecharge($userId, $data, WalletTypeEnum::SELLER_AD());
    }

    /**
     * Atomically transfer money from the seller's earnings wallet into the
     * ad wallet. Earnings wallet sees a PAYMENT, ad wallet sees a DEPOSIT —
     * both inside one DB transaction so the two ledgers stay consistent.
     */
    public function transferFromEarningWallet(int $userId, float $amount): array
    {
        if (!$this->isFeatureEnabled()) {
            return [
                'success' => false,
                'message' => __('labels.ad_feature_disabled'),
                'data' => [],
            ];
        }

        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => __('labels.amount_must_be_positive'),
                'data' => [],
            ];
        }

        $minTopUp = $this->getMinTopUp();
        if ($minTopUp !== null && $amount < $minTopUp) {
            return [
                'success' => false,
                'message' => __('labels.ad_minimum_topup_required', ['min' => $minTopUp]),
                'data' => [],
            ];
        }

        try {
            return DB::transaction(function () use ($userId, $amount) {
                $earningWallet = Wallet::where('user_id', $userId)
                    ->where('type', WalletTypeEnum::SELLER())
                    ->lockForUpdate()
                    ->first();

                if (!$earningWallet) {
                    return [
                        'success' => false,
                        'message' => __('labels.wallet_not_found'),
                        'data' => [],
                    ];
                }

                if ((float) $earningWallet->balance < $amount) {
                    return [
                        'success' => false,
                        'message' => __('labels.insufficient_wallet_balance'),
                        'data' => [],
                    ];
                }

                $adWallet = Wallet::firstOrCreate(
                    ['user_id' => $userId, 'type' => WalletTypeEnum::SELLER_AD()],
                    [
                        'balance' => 0.00,
                        'currency_code' => $earningWallet->currency_code,
                    ]
                );

                $earningWallet->balance = (float) $earningWallet->balance - $amount;
                $earningWallet->save();

                $adWallet->balance = (float) $adWallet->balance + $amount;
                $adWallet->save();

                $description = __('labels.ad_wallet_transfer_from_earnings');

                $debit = WalletTransaction::create([
                    'wallet_id' => $earningWallet->id,
                    'user_id' => $userId,
                    'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                    'payment_method' => 'ad_wallet_transfer',
                    'amount' => $amount,
                    'currency_code' => $earningWallet->currency_code,
                    'status' => WalletTransactionStatusEnum::COMPLETED(),
                    'description' => $description,
                ]);

                $credit = WalletTransaction::create([
                    'wallet_id' => $adWallet->id,
                    'user_id' => $userId,
                    'transaction_type' => WalletTransactionTypeEnum::DEPOSIT(),
                    'payment_method' => 'earning_wallet_transfer',
                    'amount' => $amount,
                    'currency_code' => $adWallet->currency_code,
                    'status' => WalletTransactionStatusEnum::COMPLETED(),
                    'description' => $description,
                    'transaction_reference' => 'transfer:' . $debit->id,
                ]);

                return [
                    'success' => true,
                    'message' => __('labels.ad_wallet_transfer_from_earnings_success'),
                    'data' => [
                        'earning_wallet' => $earningWallet->fresh(),
                        'ad_wallet' => $adWallet->fresh(),
                        'debit_transaction' => $debit,
                        'credit_transaction' => $credit,
                    ],
                ];
            });
        } catch (Exception $e) {
            Log::error('AdWalletService::transferFromEarningWallet failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Admin-issued positive credit to an ad wallet (e.g. promotional bonus).
     */
    public function promoCredit(int $userId, float $amount, ?string $note = null): array
    {
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => __('labels.amount_must_be_positive'),
                'data' => [],
            ];
        }

        return $this->walletService->addBalance(
            $userId,
            [
                'amount' => $amount,
                'payment_method' => 'admin_promo_credit',
                'description' => $note ?: __('labels.ad_wallet_promo_credit'),
            ],
            WalletTypeEnum::SELLER_AD(),
        );
    }

    /**
     * Signed admin adjustment to the ad wallet. Positive amount credits,
     * negative debits. Always inside a DB transaction.
     */
    public function adjust(int $userId, float $amount, ?string $note = null): array
    {
        if ($amount === 0.0) {
            return [
                'success' => false,
                'message' => __('labels.amount_must_not_be_zero'),
                'data' => [],
            ];
        }

        try {
            return DB::transaction(function () use ($userId, $amount, $note) {
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $userId, 'type' => WalletTypeEnum::SELLER_AD()],
                    [
                        'balance' => 0.00,
                        'currency_code' => 'USD',
                    ]
                );

                $newBalance = (float) $wallet->balance + $amount;
                if ($newBalance < 0) {
                    return [
                        'success' => false,
                        'message' => __('labels.insufficient_wallet_balance'),
                        'data' => [],
                    ];
                }

                $wallet->balance = $newBalance;
                $wallet->save();

                $transaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $userId,
                    'transaction_type' => WalletTransactionTypeEnum::ADJUSTMENT(),
                    'payment_method' => 'admin_adjustment',
                    'amount' => abs($amount),
                    'currency_code' => $wallet->currency_code,
                    'status' => WalletTransactionStatusEnum::COMPLETED(),
                    'description' => $note ?: __('labels.ad_wallet_admin_adjustment'),
                ]);

                return [
                    'success' => true,
                    'message' => __('labels.ad_wallet_adjusted_successfully'),
                    'data' => [
                        'wallet' => $wallet->fresh(),
                        'transaction' => $transaction,
                    ],
                ];
            });
        } catch (Exception $e) {
            Log::error('AdWalletService::adjust failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }
}
