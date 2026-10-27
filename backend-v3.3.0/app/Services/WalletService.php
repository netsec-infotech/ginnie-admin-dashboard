<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\XenditController;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Currency\CurrencyWriteGuard;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WalletService
{
    protected RazorpayController $razorpayController;

    protected StripeController $stripeController;

    protected PaystackController $paystackController;

    protected FlutterwaveController $flutterwaveController;

    protected XenditController $xenditController;

    protected MercadoPagoController $mercadoPagoController;

    protected SettingService $settingService;

    protected CurrencyWriteGuard $currencyWriteGuard;

    public function __construct()
    {
        try {
            // Prevent DB calls if DB is not configured
            if (Schema::hasTable('settings')) {
                if (Schema::hasTable('settings')) {
                    $this->razorpayController = app(RazorpayController::class);
                    $this->stripeController = app(StripeController::class);
                    $this->paystackController = app(PaystackController::class);
                    $this->flutterwaveController = app(FlutterwaveController::class);
                    $this->xenditController = app(XenditController::class);
                    $this->mercadoPagoController = app(MercadoPagoController::class);
                    $this->settingService = app(SettingService::class);
                    $this->currencyWriteGuard = app(CurrencyWriteGuard::class);
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore during installation
            // Do NOT log here – installer should not fail
        }
    }

    /**
     * Normalize a wallet type input (enum, string, or null) to its string value.
     * Unknown values fall back to the customer wallet so legacy callers that
     * don't pass a type stay on customer funds.
     */
    protected function normalizeType(WalletTypeEnum|string|null $type): string
    {
        if ($type instanceof WalletTypeEnum) {
            return $type->value;
        }

        if (is_string($type) && WalletTypeEnum::tryFrom($type) !== null) {
            return $type;
        }

        return WalletTypeEnum::CUSTOMER->value;
    }

    /**
     * Get or create a user's wallet for the given ownership type.
     *
     * @param  int  $userId  The user ID
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type (customer/seller/delivery_boy); defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public function getWallet(int $userId, WalletTypeEnum|string|null $type = null): array
    {
        try {
            $walletType = $this->normalizeType($type);

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $walletType],
                [
                    'balance' => 0.00,
                    'blocked_balance' => 0.00,
                    // Phase 2: new wallets remember the configured base currency;
                    // existing wallet balances are intentionally untouched.
                    'currency_code' => app(CurrencyWriteGuard::class)->systemCurrency(),
                ]
            );
            $wallet->balance = number_format($wallet->balance, 2, '.', '');

            return [
                'success' => true,
                'message' => __('labels.wallet_retrieved_successfully'),
                'data' => $wallet,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving wallet: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Add balance to the user wallet
     *
     * @param  int  $userId  The user ID
     * @param  array  $data  Balance data including amount, payment method, etc.
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type; defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public function addBalance(int $userId, array $data, WalletTypeEnum|string|null $type = null): array
    {
        try {
            if (! empty($data['transaction_reference'])) {
                $existing = WalletTransaction::where('transaction_reference', $data['transaction_reference'])->first();
                if ($existing) {
                    return [
                        'success' => false,
                        'message' => __('labels.transaction_already_exists') ?? 'Transaction already exists',
                        'data' => [
                            'wallet' => $existing->wallet,
                            'transaction' => $existing,
                        ],
                    ];
                }
            }

            DB::beginTransaction();

            $walletType = $this->normalizeType($type);

            // Get or create a wallet scoped to (user, type)
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $walletType],
                [
                    'balance' => 0.00,
                    'currency_code' => app(CurrencyWriteGuard::class)->systemCurrency(),
                ]
            );

            // Add balance
            $amount = $data['amount'];
            $wallet->balance += $amount;
            $wallet->save();

            // Create a transaction record
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'order_id' => $data['order_id'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'transaction_type' => WalletTransactionTypeEnum::DEPOSIT(),
                'payment_method' => $data['payment_method'],
                'amount' => $amount,
                'currency_code' => app(CurrencyWriteGuard::class)->walletCurrency($wallet),
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'description' => $data['description'] ?? 'Wallet balance added',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.wallet_balance_added_successfully'),
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error adding wallet balance: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Prepare wallet recharge
     *
     * @param  int  $userId  The user ID
     * @param  array  $data  Balance data including amount, payment method, etc.
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type; defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public function prepareWalletRecharge(int $userId, array $data, WalletTypeEnum|string|null $type = null): array
    {
        try {
            DB::beginTransaction();

            $walletType = $this->normalizeType($type);

            // Get or create a wallet scoped to (user, type)
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $walletType],
                [
                    'balance' => 0.00,
                    'currency_code' => app(CurrencyWriteGuard::class)->systemCurrency(),
                ]
            );
            $availability = app(PaymentCurrencyAvailabilityService::class)
                ->availabilityForMethod($data['payment_method'], $wallet->currency_code);
            if (! $availability['is_available']) {
                DB::rollBack();

                return ApiResponseType::toArray(
                    success: false,
                    message: $availability['message'],
                    data: [
                        'currency_code' => $wallet->currency_code,
                        'reason_code' => $availability['reason_code'],
                        'available_payment_methods' => app(PaymentCurrencyAvailabilityService::class)
                            ->availabilityForCurrency($wallet->currency_code, [], $wallet->currency_code),
                    ]
                );
            }

            switch ($data['payment_method']) {
                case PaymentTypeEnum::PAYSTACK():
                    $paymentSettings = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
                    if (empty($paymentSettings->value['paystackCurrencyCode'])) {
                        return ApiResponseType::toArray(
                            success: false,
                            message: 'Paystack currency code not configured',
                        );
                    }
                    $currencyCode = $paymentSettings->value['paystackCurrencyCode'];
                    break;

                case PaymentTypeEnum::FLUTTERWAVE():
                case PaymentTypeEnum::MERCADO_PAGO():
                    $paymentSettings = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
                    $currencySettingKey = $data['payment_method'] === PaymentTypeEnum::MERCADO_PAGO()
                        ? 'mercadoPagoCurrencyCode'
                        : 'flutterwaveCurrencyCode';
                    if (empty($paymentSettings->value[$currencySettingKey])) {
                        return ApiResponseType::toArray(
                            success: false,
                            message: 'Payment currency code not configured',
                        );
                    }
                    $currencyCode = $paymentSettings->value[$currencySettingKey];
                    break;
                default:
                    $currencyCode = $wallet->currency_code;
                    break;
            }

            // Create a transaction record
            $data['currency'] = $currencyCode;
            $transaction = $this->makeWalletPaymentTransaction(wallet: $wallet, userId: $userId, data: $data, status: WalletTransactionStatusEnum::PENDING(), transactionType: WalletTransactionTypeEnum::DEPOSIT(), currencyCode: $currencyCode);
            $data['transaction_id'] = (string) $transaction->id;

            $paymentResponse = $this->paymentIntent($data['payment_method'], $data);
            if (! $paymentResponse['success']) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => $paymentResponse['message'],
                    'data' => $paymentResponse['data'],
                ];
            }

            DB::commit();

            $paymentUrl = $this->buildWalletPaymentUrl($data['payment_method'], $transaction->id);

            return [
                'success' => true,
                'message' => __('labels.wallet_recharge_prepared_successfully'),
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                    'payment_response' => $paymentResponse['data'],
                    'payment_url' => $paymentUrl,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error adding wallet balance: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Build the wallet payment page URL for the given gateway.
     */
    private function buildWalletPaymentUrl(string $paymentMethod, int $transactionId): ?string
    {
        $routeMap = [
            PaymentTypeEnum::STRIPE() => 'wallet-payment.stripe',
            PaymentTypeEnum::RAZORPAY() => 'wallet-payment.razorpay',
            PaymentTypeEnum::PAYSTACK() => 'wallet-payment.paystack',
            PaymentTypeEnum::FLUTTERWAVE() => 'wallet-payment.flutterwave',
            PaymentTypeEnum::XENDIT() => 'wallet-payment.xendit',
            PaymentTypeEnum::MERCADO_PAGO() => 'wallet-payment.mercadopago',
        ];

        $routeName = $routeMap[$paymentMethod] ?? null;

        return $routeName ? route($routeName, ['transaction' => $transactionId]) : null;
    }

    private function paymentIntent(string $paymentMethod, array $data): array
    {
        return match ($paymentMethod) {
            PaymentTypeEnum::RAZORPAY() => $this->razorpayController->createWalletRechargeOrder(data: $data),
            PaymentTypeEnum::STRIPE() => $this->stripeController->createWalletRechargeOrderPaymentIntent(data: $data),
            PaymentTypeEnum::PAYSTACK() => $this->paystackController->createWalletRechargeOrderPaymentIntent(data: $data),
            PaymentTypeEnum::FLUTTERWAVE() => $this->flutterwaveController->createWalletRechargeOrder(data: $data),
            PaymentTypeEnum::XENDIT() => $this->xenditController->createWalletRechargePayment(data: $data),
            PaymentTypeEnum::MERCADO_PAGO() => $this->mercadoPagoController->createWalletRechargePayment(data: $data),
            default => throw new Exception('Unsupported payment method: '.$paymentMethod),
        };

    }

    /**
     * Create a wallet payment transaction
     *
     * @param  Wallet  $wallet  The wallet instance
     * @param  int  $userId  The user ID
     * @param  array  $data  Transaction data including amount, payment method, etc.
     * @return WalletTransaction The created wallet transaction
     */
    public function makeWalletPaymentTransaction(
        Wallet $wallet,
        int $userId,
        array $data,
        string $status,
        string $transactionType,
        string $currencyCode
    ): WalletTransaction {
        if (! empty($data['transaction_reference'])) {
            $existing = WalletTransaction::where('transaction_reference', $data['transaction_reference'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'order_id' => $data['order_id'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'transaction_type' => $transactionType ?? WalletTransactionTypeEnum::DEPOSIT(),
            'payment_method' => $data['payment_method'],
            'amount' => $data['amount'],
            'currency_code' => app(CurrencyWriteGuard::class)->requireValid($currencyCode, 'Wallet transaction'),
            'status' => $status ?? WalletTransactionStatusEnum::PENDING(),
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'description' => $data['description'] ?? 'Wallet recharge initiated',
        ]);
    }

    /**
     * Deduct balance from the user wallet.
     *
     * Kept static for backward compatibility with callers in Order, OrderService,
     * SellerSubscriptionService and SellerEarningController. The third argument
     * scopes the deduction to a specific wallet type; omit it to keep the
     * historical "customer wallet" behavior.
     *
     * @param  int  $userId  The user ID
     * @param  array  $data  Deduction data including amount, order ID, etc.
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type; defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public static function deductBalance(
        int $userId,
        array $data,
        WalletTypeEnum|string|null $type = null,
        bool $allowNegative = false
    ): array {
        try {
            $walletType = match (true) {
                $type instanceof WalletTypeEnum => $type->value,
                is_string($type) && WalletTypeEnum::tryFrom($type) !== null => $type,
                default => WalletTypeEnum::CUSTOMER->value,
            };

            $wallet = Wallet::where('user_id', $userId)
                ->where('type', $walletType)
                ->first();

            if (! $wallet) {
                if ($allowNegative) {
                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => $userId, 'type' => $walletType],
                        [
                            'balance' => 0.00,
                            'blocked_balance' => 0.00,
                            'currency_code' => app(CurrencyWriteGuard::class)->systemCurrency(),
                        ]
                    );
                } else {
                    return [
                        'success' => false,
                        'message' => __('labels.wallet_not_found'),
                        'data' => [],
                    ];
                }
            }

            if (! empty($data['transaction_reference'])) {
                $existing = WalletTransaction::where('transaction_reference', $data['transaction_reference'])->first();
                if ($existing) {
                    return [
                        'success' => false,
                        'message' => __('labels.transaction_already_exists') ?? 'Transaction already exists',
                        'data' => [
                            'wallet' => $existing->wallet,
                            'transaction' => $existing,
                        ],
                    ];
                }
            }

            $amount = $data['amount'];

            if (! $allowNegative && $wallet->balance < $amount) {
                return [
                    'success' => false,
                    'message' => __('labels.insufficient_wallet_balance'),
                    'data' => [],
                ];
            }

            DB::beginTransaction();

            $balanceBefore = (float) $wallet->balance;
            // Deduct balance
            $wallet->balance -= $amount;
            $balanceAfter = (float) $wallet->balance;
            $wallet->save();

            // Create a transaction record
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'order_id' => $data['order_id'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'return_id' => $data['return_id'] ?? null,
                'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                'payment_method' => 'wallet',
                'amount' => $amount,
                'balance_before' => $data['balance_before'] ?? $balanceBefore,
                'balance_after' => $data['balance_after'] ?? $balanceAfter,
                'currency_code' => app(CurrencyWriteGuard::class)->walletCurrency($wallet),
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'description' => $data['description'] ?? 'Payment made from wallet',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.wallet_balance_deducted_successfully'),
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deducting wallet balance: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get wallet transactions with filtering and pagination
     *
     * @param  int  $userId  The user ID
     * @param  array  $filters  Filter parameters
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type to scope the listing to; defaults to customer
     * @return array Result containing success status, message, and transactions data
     */
    public function getTransactions(
        int $userId,
        array $filters = [],
        WalletTypeEnum|string|null $type = null
    ): array {
        try {
            $walletType = $this->normalizeType($type);

            // Scope to the user's wallet of the requested type — this keeps
            // seller/rider transactions out of the customer panel listing
            // (and vice versa) even though they all share users.id.
            $query = WalletTransaction::where('user_id', $userId)
                ->whereHas('wallet', fn ($q) => $q->where('type', $walletType));

            $perPage = $filters['per_page'] ?? 15; // Default to 15 items per page if not provided

            // Search functionality
            if (isset($filters['query'])) {
                $searchQuery = $filters['query'];
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('description', 'LIKE', '%'.$searchQuery.'%')
                        ->orWhere('transaction_reference', 'LIKE', '%'.$searchQuery.'%')
                        ->orWhere('payment_method', 'LIKE', '%'.$searchQuery.'%')
                        ->orWhere('amount', 'LIKE', '%'.$searchQuery.'%');
                });
            }

            // Filter by transaction type
            if (isset($filters['transaction_type'])) {
                $query->where('transaction_type', $filters['transaction_type']);
            }

            // Filter by status
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Filter by payment method
            if (isset($filters['payment_method'])) {
                $query->where('payment_method', 'LIKE', '%'.$filters['payment_method'].'%');
            }

            // Filter by amount range
            if (isset($filters['min_amount'])) {
                $query->where('amount', '>=', $filters['min_amount']);
            }

            if (isset($filters['max_amount'])) {
                $query->where('amount', '<=', $filters['max_amount']);
            }

            // Sorting functionality
            if (isset($filters['sort']) && isset($filters['order'])) {
                $sortField = $filters['sort'];
                $sortOrder = $filters['order'];

                // Validate sort field to prevent SQL injection
                $allowedSortFields = [
                    'id',
                    'amount',
                    'transaction_type',
                    'status',
                    'payment_method',
                    'created_at',
                    'updated_at',
                ];

                if (in_array($sortField, $allowedSortFields)) {
                    $query->orderBy($sortField, $sortOrder);
                }
            } else {
                // Default sorting by created_at desc
                $query->orderBy('created_at', 'desc');
            }

            $transactions = $query->paginate($perPage);

            return [
                'success' => true,
                'message' => __('labels.wallet_transactions_retrieved_successfully'),
                'data' => $transactions,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving wallet transactions: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get a single wallet transaction by ID
     *
     * @param  int  $userId  The user ID
     * @param  int  $transactionId  The transaction ID
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type to scope the lookup to; defaults to customer
     * @return array Result containing success status, message, and transaction data
     */
    public function getTransaction(
        int $userId,
        int $transactionId,
        WalletTypeEnum|string|null $type = null
    ): array {
        try {
            $walletType = $this->normalizeType($type);

            $transaction = WalletTransaction::where('user_id', $userId)
                ->where('id', $transactionId)
                ->whereHas('wallet', fn ($q) => $q->where('type', $walletType))
                ->first();

            if (! $transaction) {
                return [
                    'success' => false,
                    'message' => __('labels.wallet_transaction_not_found'),
                    'data' => [],
                ];
            }

            return [
                'success' => true,
                'message' => __('labels.wallet_transaction_retrieved_successfully'),
                'data' => $transaction,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving wallet transaction: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Finalize a pending wallet deposit manually by admin (complete or fail)
     *
     * @param  bool  $approve  true to complete, false to fail
     * @param  string|null  $adminNote  optional description/note to store
     */
    public function finalizeDeposit(int $transactionId, bool $approve, ?string $adminNote = null): array
    {
        try {
            DB::beginTransaction();

            $transaction = WalletTransaction::query()
                ->where('id', $transactionId)
                ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT())
                ->first();

            if (! $transaction) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.wallet_transaction_not_found'),
                    'data' => [],
                ];
            }

            if ($transaction->status !== WalletTransactionStatusEnum::PENDING()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.transaction_already_processed'),
                    'data' => [],
                ];
            }

            // Append admin note to description if provided
            if (! empty($adminNote)) {
                $transaction->description = trim(($transaction->description ?? '')."\nAdmin: ".$adminNote);
            }

            if ($approve) {
                if ($this->isGatewayDeposit($transaction) && $transaction->balance_after === null) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.payment_not_completed') !== 'labels.payment_not_completed'
                            ? __('labels.payment_not_completed')
                            : 'Payment has not been completed by the gateway.',
                        'data' => [],
                    ];
                }

                // credit wallet balance
                $wallet = Wallet::where('id', $transaction->wallet_id)->first();
                if (! $wallet) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.wallet_not_found'),
                        'data' => [],
                    ];
                }

                // Gateway callbacks set balance_after when they have already credited this deposit.
                if ($transaction->balance_after === null) {
                    $balanceBefore = (float) $wallet->balance;
                    $balanceAfter = $balanceBefore + (float) $transaction->amount;

                    $wallet->balance = $balanceAfter;
                    $wallet->save();

                    $transaction->balance_before = $transaction->balance_before ?? $balanceBefore;
                    $transaction->balance_after = $balanceAfter;
                }

                $transaction->status = WalletTransactionStatusEnum::COMPLETED();
            } else {
                $transaction->status = WalletTransactionStatusEnum::FAILED();
            }

            $transaction->save();

            DB::commit();

            return [
                'success' => true,
                'message' => $approve ? __('labels.wallet_recharge_success') : __('labels.wallet_recharge_failed'),
                'data' => [
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error finalizing wallet deposit: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function syncGatewayRechargeStatus(WalletTransaction $transaction): void
    {
        if ($transaction->status !== WalletTransactionStatusEnum::PENDING() || empty($transaction->transaction_reference)) {
            return;
        }

        try {
            match ((string) $transaction->payment_method) {
                PaymentTypeEnum::STRIPE() => $this->syncStripeWalletPayment($transaction),
                PaymentTypeEnum::XENDIT() => $this->xenditController->verifyPayment((string) $transaction->transaction_reference),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Wallet gateway status sync failed: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
                'payment_method' => $transaction->payment_method,
            ]);
        }
    }

    private function isGatewayDeposit(WalletTransaction $transaction): bool
    {
        return in_array((string) $transaction->payment_method, [
            PaymentTypeEnum::RAZORPAY(),
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
        ], true);
    }

    private function syncStripeWalletPayment(WalletTransaction $transaction): void
    {
        $verification = $this->stripeController->verifyPayment((string) $transaction->transaction_reference);
        if (! ($verification['success'] ?? false)) {
            return;
        }

        $paymentIntent = $verification['data'] ?? null;
        $receivedAmount = (float) (($paymentIntent->amount_received ?? 0) / 100);
        if ($receivedAmount <= 0 || abs($receivedAmount - (float) $transaction->amount) > 0.01) {
            Log::warning('Stripe wallet verification amount mismatch.', [
                'transaction_id' => $transaction->id,
                'expected_amount' => $transaction->amount,
                'received_amount' => $receivedAmount,
            ]);

            return;
        }

        $transaction->update([
            'amount' => $receivedAmount,
            'currency_code' => app(CurrencyWriteGuard::class)->requireValid($paymentIntent->currency ?? $transaction->currency_code, 'Wallet gateway transaction'),
            'description' => 'Wallet Recharge Payment Captured',
        ]);

        // Stripe redirects can beat the webhook; capture here so paid wallet topups do not stay pending.
        $result = Wallet::captureRecharge($transaction->id);
        if (! ($result['success'] ?? false)) {
            Log::error('Stripe wallet recharge capture failed after verification: '.($result['message'] ?? 'Unknown error'), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * Directly credit amount to user's wallet (e.g. for tips, refunds, bonuses)
     */
    public function creditWallet(int $userId, WalletTypeEnum|string|null $walletType, float $amount, string $description, ?string $referenceId = null): array
    {
        try {
            DB::beginTransaction();

            $type = $this->normalizeType($walletType);
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $type],
                ['balance' => 0.00, 'currency_code' => app(CurrencyWriteGuard::class)->systemCurrency()]
            );

            $wallet->balance = (float) $wallet->balance + $amount;
            $wallet->save();

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_type' => WalletTransactionTypeEnum::DEPOSIT(),
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'payment_method' => PaymentTypeEnum::WALLET(),
                'currency_code' => app(CurrencyWriteGuard::class)->walletCurrency($wallet),
                'transaction_reference' => $referenceId ?? 'REF-'.Str::random(10),
                'description' => $description,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Wallet credited successfully',
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error crediting wallet: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }
}
