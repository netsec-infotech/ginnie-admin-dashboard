<?php

namespace App\Services;

use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\PromoModeEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Exceptions\PosCartChangedException;
use App\Exceptions\PosPromoValidationException;
use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderPromoLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates seller-initiated POS (Point of Sale) orders.
 *
 * What sets this apart from the standard customer checkout:
 *  - Bypasses zone/cart/customer-checkout validation entirely.
 *  - created_by = SELLER, so all the POS-2 guards keep these orders out of
 *    the rider workflow / wallet refund / customer notification stack.
 *  - Status goes straight to DELIVERED (in-store handover, no delivery).
 *  - Stock decrements immediately and atomically inside a DB transaction.
 *  - Customer step has three branches:
 *      1. customer_id refers to a real user (existing or just quick-registered)
 *      2. customer_id is null -> walk-in placeholder, walkin_customer_name/mobile
 *         optionally captured for receipt purposes only.
 *
 * @phpstan-type CreateInput array{
 *     store_id:int,
 *     payment_method:string,
 *     customer_id?:int|null,
 *     walkin_customer_name?:string|null,
 *     walkin_customer_mobile?:string|null,
 *     items: array<int,array{store_product_variant_id:int, quantity:int}>,
 *     order_note?:string|null,
 * }
 */
class PosOrderService
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    /**
     * @param  Seller  $seller  The authenticated seller (resolved upstream).
     * @param  array  $data  Validated input (see CreateInput shape above).
     * @return array ['success'=>bool, 'message'=>string, 'data'=>['order'=>Order]|['error'=>string]]
     */
    public function create(Seller $seller, array $data): array
    {
        $store = Store::where('id', $data['store_id'])
            ->where('seller_id', $seller->id)
            ->first();
        if (! $store) {
            return $this->fail(__('labels.pos_store_not_found'));
        }

        [$user, $isWalkin] = $this->resolveBuyer($data);
        if (! $user) {
            return $this->fail(__('labels.pos_customer_not_found'));
        }

        $paymentMethod = $data['payment_method'];
        $allowed = [
            PaymentTypeEnum::COD(),
            PaymentTypeEnum::POS_UPI(),
            PaymentTypeEnum::POS_CUSTOM(),
            PaymentTypeEnum::RAZORPAY(),
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            'cash',
            'upi',
            'custom',
            'razorpay',
            'stripe',
            'paystack',
            'flutterwave',
        ];
        if (! in_array($paymentMethod, $allowed, true)) {
            return $this->fail(__('labels.pos_unsupported_payment_method'));
        }
        if (in_array($paymentMethod, ['upi', PaymentTypeEnum::POS_UPI()], true) && empty($store->pos_upi_vpa)) {
            return $this->fail(__('labels.pos_upi_not_configured'));
        }

        try {
            $result = DB::transaction(function () use ($store, $user, $isWalkin, $data, $paymentMethod) {
                $lines = $this->lockAndPriceLines($store, $data['items']);

                $subtotal = 0.0;
                $taxTotal = 0.0;
                $savingsTotal = 0.0;
                foreach ($lines as $line) {
                    $subtotal += $line['line_subtotal'];
                    $taxTotal += $line['tax_amount_total'];
                    $savingsTotal += $line['savings_total'];
                }

                $cashierDiscount = $this->resolveDiscount($data, $subtotal);

                [$promo, $promoCode, $promoDiscount] = $this->resolvePromo($data, $subtotal - $cashierDiscount, $user);
                $isCashbackPromo = $promo?->promo_mode === PromoModeEnum::CASHBACK();
                if ($isCashbackPromo) {
                    $this->ensureCashbackPromoCanBeApplied($lines, $promoCode, $isWalkin);
                }

                $instantPromoDiscount = $isCashbackPromo ? 0.0 : $promoDiscount;
                $cashbackAmount = $isCashbackPromo ? $promoDiscount : 0.0;

                $walletApplied = $this->resolveWallet($data, $user, $isWalkin, max(0.0, $subtotal - $cashierDiscount - $instantPromoDiscount));

                $totalDiscount = $cashierDiscount + $instantPromoDiscount;
                $finalTotal = max(0.0, $subtotal - $totalDiscount - $walletApplied);

                $splitCash = max(0.0, (float) ($data['split_cash_portion'] ?? 0));
                if ($splitCash > $finalTotal) {
                    throw new \RuntimeException(__('labels.pos_split_cash_exceeds_total'));
                }

                $order = $this->createOrderRow($store, $user, $isWalkin, $data, $paymentMethod, $subtotal, $finalTotal, $totalDiscount, $savingsTotal, $promoCode, $walletApplied, $splitCash);

                if ($promo && $promoDiscount > 0) {
                    Promo::where('id', $promo->id)->increment('usage_count');
                    $cashbackAwarded = false;

                    if ($isCashbackPromo && $cashbackAmount > 0) {
                        $this->creditCashbackToWallet($user, $order, $cashbackAmount);
                        $cashbackAwarded = true;
                    }

                    OrderPromoLine::create([
                        'order_id' => $order->id,
                        'promo_id' => $promo->id,
                        'promo_code' => $promo->code,
                        'discount_amount' => $promoDiscount,
                        'cashback_flag' => $isCashbackPromo,
                        'is_awarded' => $isCashbackPromo ? $cashbackAwarded : true,
                    ]);
                }

                if ($walletApplied > 0 && $user) {
                    $this->debitWallet($user, $walletApplied, $order);
                }

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $store->seller_id,
                    'total_price' => $finalTotal,
                ]);

                foreach ($lines as $line) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'store_id' => $store->id,
                        'title' => $line['title'],
                        'variant_title' => $line['variant_title'],
                        'gift_card_discount' => 0,
                        'admin_commission_amount' => 0,
                        'seller_commission_amount' => 0,
                        'commission_settled' => '0',
                        'return_eligible' => '0',
                        'returnable_days' => 0,
                        'is_cancelable' => false,
                        'cancelable_till' => null,
                        'discounted_price' => 0,
                        'promo_discount' => 0,
                        'discount' => 0,
                        'tax_amount' => $line['tax_amount_total'],
                        'tax_percent' => $line['tax_percent'],
                        'sku' => $line['sku'],
                        'quantity' => $line['quantity'],
                        'price' => $line['unit_price_excl_tax'],
                        'subtotal' => $line['line_subtotal'],
                        'status' => OrderItemStatusEnum::DELIVERED(),
                        'requires_otp' => false,
                        'otp' => null,
                    ]);

                    SellerOrderItem::create([
                        'seller_order_id' => $sellerOrder->id,
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'order_item_id' => $orderItem->id,
                        'quantity' => $line['quantity'],
                        'price' => $line['unit_price_excl_tax'],
                    ]);

                    $rows = StoreProductVariant::where('id', $line['store_product_variant_id'])
                        ->where('stock', '>=', $line['quantity'])
                        ->decrement('stock', $line['quantity']);
                    if ($rows === 0) {
                        throw new \RuntimeException(__('labels.pos_stock_changed_during_checkout'));
                    }

                    foreach ($line['addons'] as $addon) {
                        OrderItemAddon::create([
                            'uuid' => (string) Str::uuid(),
                            'order_item_id' => $orderItem->id,
                            'addon_group_id' => $addon['addon_group_id'],
                            'addon_item_id' => $addon['addon_item_id'],
                            'price' => $addon['snapshot_price'],
                        ]);

                        $addonRows = StoreAddonItem::where('id', $addon['store_addon_item_id'])
                            ->where('stock', '>=', $line['quantity'])
                            ->decrement('stock', $line['quantity']);
                        if ($addonRows === 0) {
                            throw new PosCartChangedException(__('labels.pos_addon_changed_refresh_cart'), [
                                'error_type' => 'addon_changed',
                                'addon_item_id' => $addon['addon_item_id'],
                            ]);
                        }
                    }
                }

                return ['order' => $order->fresh(['items'])];
            });
        } catch (PosPromoValidationException $e) {
            Log::warning('POS promo validation failed during checkout', [
                'promo_code' => $e->promoCode,
                'message' => $e->getMessage(),
            ]);

            return $this->fail(
                msg: $e->getMessage(),
                data: [
                    'error_type' => 'promo',
                    'promo_code' => $e->promoCode,
                    'promo_error' => $e->reason,
                ],
            );
        } catch (PosCartChangedException $e) {
            Log::warning('POS cart changed during checkout', [
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);

            return $this->fail($e->getMessage(), $e->context);
        } catch (\RuntimeException $e) {
            Log::warning('POS order creation failed', ['message' => $e->getMessage()]);

            return $this->fail($e->getMessage());
        } catch (QueryException $e) {
            Log::warning('POS order creation failed because an addon changed during checkout', [
                'message' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'addon')) {
                return $this->fail(__('labels.pos_addon_changed_refresh_cart'), [
                    'error_type' => 'addon_changed',
                ]);
            }

            return $this->fail(__('labels.pos_order_creation_failed'));
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return $this->fail(__('labels.pos_order_creation_failed'));
        }

        return [
            'success' => true,
            'message' => __('labels.pos_order_created'),
            'data' => $result,
        ];
    }

    /**
     * Decide which user the order attaches to, and whether the walk-in
     * placeholder semantics apply. Auto-provisions the walk-in placeholder
     * if it hasn't been configured yet.
     *
     * @return array [User, bool $isWalkin]
     */
    private function resolveBuyer(array $data): array
    {
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $walkinId = (int) (Setting::posWalkinUserId() ?? 0);

        if ($customerId === 0 || $customerId === $walkinId) {
            $placeholder = app(PosCustomerService::class)->ensureWalkinPlaceholder();

            return [$placeholder, true];
        }

        $user = User::where('id', $customerId)
            ->where('access_panel', GuardNameEnum::WEB())
            ->first();

        return [$user, false];
    }

    /**
     * Validate, snapshot, and price every cart line.
     *
     * @throws PosCartChangedException when a product, variant, or add-on changed after it was added to the cart
     * @throws \RuntimeException on invalid quantity
     */
    private function lockAndPriceLines(Store $store, array $items): array
    {
        $ids = collect($items)->pluck('store_product_variant_id')->all();
        $rows = StoreProductVariant::whereIn('id', $ids)
            ->where('store_id', $store->id)
            ->with(['productVariant.product.taxClasses.taxRates'])
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $item) {
            $svId = (int) $item['store_product_variant_id'];
            $qty = (int) $item['quantity'];
            if ($qty <= 0) {
                throw new \RuntimeException(__('labels.pos_quantity_must_be_positive'));
            }
            $sv = $rows->get($svId);
            if (! $sv) {
                throw new PosCartChangedException(__('labels.pos_product_changed_refresh_cart'), [
                    'error_type' => 'product_changed',
                    'store_product_variant_id' => $svId,
                ]);
            }
            if ($sv->stock < $qty) {
                throw new PosCartChangedException(__('labels.pos_product_changed_refresh_cart'), [
                    'error_type' => 'product_changed',
                    'store_product_variant_id' => $svId,
                ]);
            }

            $productVariant = $sv->productVariant;
            $product = $productVariant?->product;

            if (! $productVariant || ! $product || ! $this->isProductAvailableForPos($product, $productVariant)) {
                throw new PosCartChangedException(__('labels.pos_product_changed_refresh_cart'), [
                    'error_type' => 'product_changed',
                    'store_product_variant_id' => $svId,
                ]);
            }

            $taxPercent = (float) $product?->taxClasses
                ?->flatMap->taxRates
                ?->sum('rate') ?: 0.0;

            $regularStored = (float) $sv->price;
            $effectiveStored = (float) ($sv->special_price > 0 && $sv->special_price < $sv->price ? $sv->special_price : $sv->price);
            $isInclusive = (string) ($product?->is_inclusive_tax ?? '1') === '1';

            if ($isInclusive) {
                $unitInclTax = $effectiveStored;
                $unitExclTax = $taxPercent > 0 ? $unitInclTax / (1 + $taxPercent / 100) : $unitInclTax;
                $regularInclTax = $regularStored;
            } else {
                $unitExclTax = $effectiveStored;
                $unitInclTax = $taxPercent > 0 ? $unitExclTax * (1 + $taxPercent / 100) : $unitExclTax;
                $regularInclTax = $taxPercent > 0 ? $regularStored * (1 + $taxPercent / 100) : $regularStored;
            }
            $unitTax = $unitInclTax - $unitExclTax;

            $unitSavings = max(0.0, $regularInclTax - $unitInclTax);

            $resolvedAddons = $this->resolveAddons(
                store: $store,
                productVariantId: (int) $productVariant->id,
                quantity: $qty,
                addons: $item['addons'] ?? [],
            );

            $addonInclTotal = 0.0;
            $addonExclTotal = 0.0;
            foreach ($resolvedAddons as $addon) {
                $stored = (float) $addon['snapshot_price'];
                if ($isInclusive) {
                    $addonInclTotal += $stored;
                    $addonExclTotal += $taxPercent > 0 ? $stored / (1 + $taxPercent / 100) : $stored;
                } else {
                    $addonExclTotal += $stored;
                    $addonInclTotal += $taxPercent > 0 ? $stored * (1 + $taxPercent / 100) : $stored;
                }
            }
            $addonTax = $addonInclTotal - $addonExclTotal;

            $lineSubtotal = ($unitInclTax * $qty) + ($addonInclTotal * $qty);
            $lineTax = ($unitTax * $qty) + ($addonTax * $qty);
            $lineSavings = $unitSavings * $qty;

            $lines[] = [
                'store_product_variant_id' => $svId,
                'product_id' => $product?->id,
                'product_variant_id' => $productVariant->id,
                'title' => $product?->title ?? __('labels.item'),
                'variant_title' => $productVariant->title ?? __('labels.pos_variant_default'),
                'sku' => $sv->sku ?? "POS-{$svId}",
                'quantity' => $qty,
                'unit_price_excl_tax' => $unitExclTax,
                'unit_price_incl_tax' => $unitInclTax,
                'unit_regular_incl_tax' => $regularInclTax,
                'tax_percent' => $taxPercent,
                'tax_amount_total' => $lineTax,
                'line_subtotal' => $lineSubtotal,
                'savings_total' => $lineSavings,
                'is_returnable' => (string) ($product->is_returnable ?? '0') === '1',
                'is_cancelable' => (string) ($product->is_cancelable ?? '0') === '1',
                'addons' => $resolvedAddons,
            ];
        }

        return $lines;
    }

    private function isProductAvailableForPos(Product $product, ProductVariant $productVariant): bool
    {
        return $product->status === ProductStatusEnum::ACTIVE()
            && $product->verification_status === ProductVarificationStatusEnum::APPROVED()
            && (bool) $productVariant->availability
            && $productVariant->visibility === 'published';
    }

    /**
     * Re-validate promo code against the post-cashier-discount cart total.
     *
     * @return array{0: Promo|null, 1: string|null, 2: float}
     */
    private function resolvePromo(array $data, float $cartAfterCashier, User $user): array
    {
        $code = trim((string) ($data['promo_code'] ?? ''));
        if ($code === '' || $cartAfterCashier <= 0) {
            return [null, null, 0.0];
        }

        $result = $this->cartService->validatePromoCode(
            promoCode: $code,
            user: $user,
            cartTotal: $cartAfterCashier,
            deliveryCharge: 0.0,
        );
        if (! ($result['success'] ?? false)) {
            $reason = (string) ($result['message'] ?? __('labels.pos_promo_code_invalid'));

            throw new PosPromoValidationException(
                message: __('labels.pos_promo_no_longer_valid', [
                    'code' => $code,
                    'reason' => $reason,
                ]),
                promoCode: $code,
                reason: 'criteria',
            );
        }
        /** @var Promo|null $promo */
        $promo = $result['promo'] ?? null;
        $amount = (float) ($result['discount'] ?? 0);

        return [$promo, $code, max(0.0, min($cartAfterCashier, round($amount, 2)))];
    }

    /**
     * Resolve and clamp the wallet-pay amount.
     */
    private function resolveWallet(array $data, User $user, bool $isWalkin, float $remainingTotal): float
    {
        $requested = (float) ($data['wallet_amount'] ?? 0);
        if ($requested <= 0 || $isWalkin || $remainingTotal <= 0) {
            return 0.0;
        }

        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = (float) ($wallet?->balance ?? 0);
        if ($balance <= 0) {
            return 0.0;
        }

        return round(min($requested, $balance, $remainingTotal), 2);
    }

    /**
     * Cashback is safe for POS only when there is no return/cancel window and
     * the sale is attached to a real customer wallet.
     *
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function ensureCashbackPromoCanBeApplied(array $lines, ?string $promoCode, bool $isWalkin): void
    {
        if ($isWalkin) {
            throw new PosPromoValidationException(
                message: __('labels.pos_cashback_requires_customer', ['code' => $promoCode]),
                promoCode: (string) $promoCode,
                reason: 'cashback_customer_required',
            );
        }

        $hasReturnableOrCancelableProduct = collect($lines)->contains(
            fn (array $line): bool => (bool) ($line['is_returnable'] ?? false) || (bool) ($line['is_cancelable'] ?? false)
        );

        if ($hasReturnableOrCancelableProduct) {
            throw new PosPromoValidationException(
                message: __('labels.pos_cashback_not_allowed_for_returnable_cancelable_products', ['code' => $promoCode]),
                promoCode: (string) $promoCode,
                reason: 'cashback_returnable_cancelable_products',
            );
        }
    }

    private function creditCashbackToWallet(User $user, Order $order, float $amount): void
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'type' => WalletTypeEnum::CUSTOMER->value],
            [
                'balance' => 0.00,
                'blocked_balance' => 0.00,
                'currency_code' => $order->currency_code,
            ]
        );

        $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        $wallet->balance = (float) $wallet->balance + $amount;
        if (empty($wallet->currency_code)) {
            $wallet->currency_code = $order->currency_code;
        }
        $wallet->save();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'store_id' => $order->items()->value('store_id'),
            'transaction_type' => WalletTransactionTypeEnum::DEPOSIT(),
            'payment_method' => 'cashback',
            'amount' => $amount,
            'currency_code' => $order->currency_code,
            'status' => WalletTransactionStatusEnum::COMPLETED(),
            'transaction_reference' => 'POS-CASHBACK-'.$order->id,
            'description' => "Cashback for POS order #{$order->slug} (Promo: {$order->promo_code})",
        ]);
    }

    /**
     * Debit the customer wallet + write the corresponding transaction row.
     */
    private function debitWallet(User $user, float $amount, Order $order): void
    {
        $wallet = Wallet::where('user_id', $user->id)
            ->where('type', WalletTypeEnum::CUSTOMER->value)
            ->lockForUpdate()
            ->first();
        if (! $wallet) {
            throw new \RuntimeException(__('labels.pos_customer_no_wallet'));
        }
        if ((float) $wallet->balance < $amount) {
            throw new \RuntimeException(__('labels.pos_wallet_balance_insufficient'));
        }

        $wallet->balance = (float) $wallet->balance - $amount;
        $wallet->save();

        WalletTransaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'order_id' => $order->id,
            'amount' => $amount,
            'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
            'payment_method' => 'wallet',
            'description' => 'POS sale #'.$order->id,
            'transaction_reference' => 'POS-'.$order->id,
            'currency_code' => $order->currency_code,
            'status' => WalletTransactionStatusEnum::COMPLETED(),
        ]);
    }

    /**
     * Translate the raw discount payload into a clamped money amount.
     */
    private function resolveDiscount(array $data, float $subtotal): float
    {
        $type = $data['discount_type'] ?? null;
        $value = isset($data['discount_value']) ? (float) $data['discount_value'] : 0.0;
        if (! $type || $value <= 0 || $subtotal <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            $pct = min(100.0, max(0.0, $value));

            return round($subtotal * $pct / 100, 2);
        }
        if ($type === 'fixed') {
            return round(min($subtotal, max(0.0, $value)), 2);
        }

        return 0.0;
    }

    /**
     * Validate the cashier's addon picks against StoreProductVariantAddon.
     *
     * @return array<int,array{addon_group_id:int, addon_item_id:int, store_addon_item_id:int, snapshot_price:float}>
     */
    private function resolveAddons(Store $store, int $productVariantId, int $quantity, array $addons): array
    {
        if (empty($addons)) {
            $requiredGroupIds = StoreProductVariantAddon::where('store_id', $store->id)
                ->where('product_variant_id', $productVariantId)
                ->pluck('addon_group_id')->unique();
            if ($requiredGroupIds->isNotEmpty()) {
                $required = AddonGroup::whereIn('id', $requiredGroupIds)->where('is_required', true)->pluck('title');
                if ($required->isNotEmpty()) {
                    throw new \RuntimeException(__('labels.pos_required_addon_groups_missing'));
                }
            }

            return [];
        }

        $allowed = StoreProductVariantAddon::where('store_id', $store->id)
            ->where('product_variant_id', $productVariantId)
            ->get()
            ->keyBy(fn ($r) => $r->addon_group_id.':'.$r->addon_item_id);

        $perGroup = [];
        foreach ($addons as $addon) {
            $key = ((int) $addon['addon_group_id']).':'.((int) $addon['addon_item_id']);
            if (! $allowed->has($key)) {
                throw new PosCartChangedException(__('labels.pos_addon_changed_refresh_cart'), [
                    'error_type' => 'addon_changed',
                    'addon_item_id' => (int) $addon['addon_item_id'],
                ]);
            }
            $perGroup[(int) $addon['addon_group_id']][] = (int) $addon['addon_item_id'];
        }

        $groupIds = array_keys($perGroup);
        $allGroupIdsForVariant = $allowed->pluck('addon_group_id')->unique();
        $allGroups = AddonGroup::whereIn('id', $allGroupIdsForVariant)->get()->keyBy('id');
        foreach ($allGroups as $g) {
            $picked = $perGroup[$g->id] ?? [];
            if ($g->is_required && empty($picked)) {
                throw new \RuntimeException(__('labels.pos_required_addon_group_missing'));
            }
            $type = is_object($g->selection_type) ? $g->selection_type->value : $g->selection_type;
            if ($type === 'single' && count($picked) > 1) {
                throw new \RuntimeException(__('labels.pos_addon_group_single_selection'));
            }
        }

        $itemIds = array_unique(array_merge(...array_values($perGroup)));
        $catalogItems = AddonItem::whereIn('id', $itemIds)->get()->keyBy('id');
        foreach ($addons as $addon) {
            $catalogItem = $catalogItems->get((int) $addon['addon_item_id']);
            if (! $catalogItem || ! $catalogItem->is_available) {
                throw new PosCartChangedException(__('labels.pos_addon_changed_refresh_cart'), [
                    'error_type' => 'addon_changed',
                    'addon_item_id' => (int) $addon['addon_item_id'],
                ]);
            }
        }

        $storeItems = StoreAddonItem::where('store_id', $store->id)
            ->whereIn('addon_item_id', $itemIds)
            ->get()
            ->keyBy('addon_item_id');

        $resolved = [];
        foreach ($addons as $addon) {
            $itemId = (int) $addon['addon_item_id'];
            $sa = $storeItems->get($itemId);
            if (! $sa || ! $sa->is_available) {
                throw new PosCartChangedException(__('labels.pos_addon_changed_refresh_cart'), [
                    'error_type' => 'addon_changed',
                    'addon_item_id' => $itemId,
                ]);
            }
            if ($sa->stock < $quantity) {
                throw new PosCartChangedException(__('labels.pos_addon_changed_refresh_cart'), [
                    'error_type' => 'addon_changed',
                    'addon_item_id' => $itemId,
                ]);
            }
            $resolved[] = [
                'addon_group_id' => (int) $addon['addon_group_id'],
                'addon_item_id' => $itemId,
                'store_addon_item_id' => (int) $sa->id,
                'snapshot_price' => (float) $sa->price,
            ];
        }

        return $resolved;
    }

    /**
     * Insert the parent Order row with POS defaults.
     */
    private function createOrderRow(
        Store $store,
        User $user,
        bool $isWalkin,
        array $data,
        string $paymentMethod,
        float $subtotal,
        float $finalTotal,
        float $discountAmount = 0.0,
        float $savingsTotal = 0.0,
        ?string $promoCode = null,
        float $walletApplied = 0.0,
        float $splitCash = 0.0
    ): Order {
        $deliveryZoneId = (int) DB::table('store_zone')
            ->where('store_id', $store->id)
            ->value('zone_id');
        if ($deliveryZoneId <= 0) {
            throw new \RuntimeException(__('labels.pos_store_no_delivery_zone'));
        }

        $address = [
            'name' => $isWalkin ? ($data['walkin_customer_name'] ?? __('labels.pos_walkin_customer')) : $user->name,
            'address_1' => $store->address,
            'landmark' => $store->landmark,
            'zip' => $store->zipcode,
            'phone' => $isWalkin
                ? ($data['walkin_customer_mobile'] ?? '')
                : (string) ($user->mobile ?? ''),
            'address_type' => 'other',
            'latitude' => (float) ($store->latitude ?? 0),
            'longitude' => (float) ($store->longitude ?? 0),
            'city' => $store->city,
            'state' => $store->state,
            'country' => $store->country,
            'country_code' => substr((string) $store->country_code, 0, 3) ?: 'IN',
        ];

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'created_by' => OrderCreatedByEnum::SELLER(),
            'walkin_customer_name' => $isWalkin ? ($data['walkin_customer_name'] ?? null) : null,
            'walkin_customer_mobile' => $isWalkin ? ($data['walkin_customer_mobile'] ?? null) : null,
            'slug' => 'pos-'.Str::lower(Str::random(10)),
            'email' => $user->email ?? 'pos@system.local',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'currency_code' => $store->currency_code ?? 'INR',
            'currency_rate' => 1.0,
            'delivery_zone_id' => $deliveryZoneId,
            'payment_method' => match ($paymentMethod) {
                'razorpay' => PaymentTypeEnum::RAZORPAY(),
                'stripe' => PaymentTypeEnum::STRIPE(),
                'paystack' => PaymentTypeEnum::PAYSTACK(),
                'flutterwave' => PaymentTypeEnum::FLUTTERWAVE(),
                'upi' => PaymentTypeEnum::POS_UPI(),
                'custom' => PaymentTypeEnum::POS_CUSTOM(),
                default => PaymentTypeEnum::POS_CASH(),
            },
            'payment_status' => PaymentStatusEnum::COMPLETED(),
            'fulfillment_type' => 'hyperlocal',
            'wallet_balance' => $walletApplied,
            'promo_code' => $promoCode,
            'promo_discount' => $discountAmount,
            'pos_savings' => $savingsTotal,
            'pos_split_cash' => $splitCash,
            'gift_card_discount' => 0,
            'delivery_charge' => 0,
            'subtotal' => $subtotal,
            'total_payable' => $finalTotal,
            'final_total' => $finalTotal,
            'status' => OrderStatusEnum::DELIVERED(),

            'billing_name' => $address['name'],
            'billing_address_1' => $address['address_1'],
            'billing_landmark' => $address['landmark'],
            'billing_zip' => $address['zip'],
            'billing_phone' => $address['phone'],
            'billing_address_type' => $address['address_type'],
            'billing_latitude' => $address['latitude'],
            'billing_longitude' => $address['longitude'],
            'billing_city' => $address['city'],
            'billing_state' => $address['state'],
            'billing_country' => $address['country'],
            'billing_country_code' => $address['country_code'],

            'shipping_name' => $address['name'],
            'shipping_address_1' => $address['address_1'],
            'shipping_landmark' => $address['landmark'],
            'shipping_zip' => $address['zip'],
            'shipping_phone' => $address['phone'],
            'shipping_address_type' => $address['address_type'],
            'shipping_latitude' => $address['latitude'],
            'shipping_longitude' => $address['longitude'],
            'shipping_city' => $address['city'],
            'shipping_state' => $address['state'],
            'shipping_country' => $address['country'],
            'shipping_country_code' => $address['country_code'],

            'order_note' => $this->buildOrderNote($data, $paymentMethod),
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function buildOrderNote(array $data, string $paymentMethod): ?string
    {
        $note = $data['order_note'] ?? null;
        if (in_array($paymentMethod, ['custom', PaymentTypeEnum::POS_CUSTOM()], true)) {
            $cmName = $data['custom_payment_method_name'] ?? 'Custom';
            $prefix = "[Paid via: {$cmName}]";
            $note = $note ? $prefix.' '.$note : $prefix;
        }

        return $note;
    }

    private function fail(string $msg, array $data = []): array
    {
        return ['success' => false, 'message' => $msg, 'data' => ['error' => $msg, ...$data]];
    }
}
