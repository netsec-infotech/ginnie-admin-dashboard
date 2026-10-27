<?php

namespace App\Services;

use App\Enums\DateRangeFilterEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\EarningPaymentStatusEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use App\Enums\Order\DeliveryOtpModeEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PromoModeEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusUpdated;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Controllers\Payments\XenditController;
use App\Http\Resources\User\OrderItemReturnResource;
use App\Http\Resources\User\OrderResource;
use App\Http\Resources\User\ReviewResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemAddon;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyCashTransaction;
use App\Models\DeliveryTimeSlot;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemReturn;
use App\Models\OrderPaymentTransaction;
use App\Models\OrderPromoLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\ReturnReason;
use App\Models\Review;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\SellerStatement;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\DeliveryBoy\CashCollectionLimitReachedNotification;
use App\Notifications\ScheduledOrderAutoCancelled;
use App\Support\Currency\CurrencyWriteGuard;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

class OrderService
{
    private const DEFAULT_REGULAR_ACCEPTANCE_TIMEOUT_MINUTES = 10;

    private const MAX_ACCEPTANCE_TIMEOUT_MINUTES = 1440;

    private const ACCEPTANCE_TIMEOUT_SNAPSHOT_VERSION = 1;

    protected StockService $stockService;

    protected DeliveryBoyService $deliveryBoyService;

    protected SettingService $settingService;

    protected PaymentService $paymentService;

    protected SellerStatementService $sellerStatementService;

    protected WalletService $walletService;

    public function __construct(
        StockService $stockService,
        DeliveryBoyService $deliveryBoyService,
        SettingService $settingService,
        PaymentService $paymentService,
        SellerStatementService $sellerStatementService,
        WalletService $walletService
    ) {
        $this->settingService = $settingService;
        $this->stockService = $stockService;
        $this->deliveryBoyService = $deliveryBoyService;
        $this->paymentService = $paymentService;
        $this->sellerStatementService = $sellerStatementService;
        $this->walletService = $walletService;
    }

    /**
     * Admin-configured seller acceptance SLA values, always returned in minutes.
     *
     * @return array{regular: int, scheduled: int}
     */
    public function orderAcceptanceTimeouts(): array
    {
        $settings = $this->currentAcceptanceTimeoutSettings();

        return [
            'regular' => $settings['regular']['minutes'],
            'scheduled' => $settings['scheduled']['minutes'],
        ];
    }

    /**
     * Snapshot the enabled/time values onto the order so future admin changes
     * do not change the SLA that applied when the order was placed.
     *
     * @return array{version:int,captured_at:string,regular:array{enabled:bool,minutes:int},scheduled:array{enabled:bool,minutes:int}}
     */
    public function orderAcceptanceTimeoutSnapshot(): array
    {
        return array_merge(
            [
                'version' => self::ACCEPTANCE_TIMEOUT_SNAPSHOT_VERSION,
                'captured_at' => now()->toIso8601String(),
            ],
            $this->currentAcceptanceTimeoutSettings()
        );
    }

    /**
     * Current regular timeout setting. Scheduled orders do not use this feature.
     *
     * @return array{regular: array{enabled: bool, minutes: int}, scheduled: array{enabled: bool, minutes: int}}
     */
    private function currentAcceptanceTimeoutSettings(): array
    {
        $sellerSettings = $this->settingService->getSettingValues(SettingTypeEnum::SELLER());

        return [
            'regular' => [
                'enabled' => $this->normalizeAcceptanceTimeoutEnabled($sellerSettings['regularOrderAcceptanceTimeoutEnabled'] ?? true),
                'minutes' => $this->normalizeAcceptanceTimeout(
                    $sellerSettings['regularOrderAcceptanceTimeoutMinutes'] ?? null,
                    self::DEFAULT_REGULAR_ACCEPTANCE_TIMEOUT_MINUTES,
                    minimum: 1
                ),
            ],
            'scheduled' => [
                'enabled' => false,
                'minutes' => 0,
            ],
        ];
    }

    /**
     * Resolve the exact seller acceptance deadline for UI hints and cron enforcement.
     */
    public function acceptanceDeadlineForOrderItem(OrderItem $orderItem, ?Order $order = null): ?Carbon
    {
        $status = $orderItem->status->value ?? $orderItem->status;
        if (! in_array($status, [OrderItemStatusEnum::PENDING(), OrderItemStatusEnum::AWAITING_STORE_RESPONSE()], true)) {
            return null;
        }

        $order ??= $orderItem->order;
        if (! $order) {
            return null;
        }

        $timeouts = $this->acceptanceTimeoutSettingsForOrder($order);
        if ($order->isScheduledDelivery()) {
            return null;
        }

        if ($status !== OrderItemStatusEnum::AWAITING_STORE_RESPONSE()) {
            return null;
        }

        if (! $timeouts['regular']['enabled']) {
            return null;
        }

        return $orderItem->updated_at?->copy()->addMinutes($timeouts['regular']['minutes']);
    }

    /**
     * API payload for seller apps to render the acceptance timer using the
     * order snapshot that was captured when the order was placed.
     *
     * @return array{enabled: bool, minutes: int, type: string, deadline: ?string, deadline_formatted: ?string}
     */
    public function acceptanceTimeoutPayloadForOrder(Order $order, ?OrderItem $orderItem = null): array
    {
        $timeouts = $this->acceptanceTimeoutSettingsForOrder($order);
        $type = $order->isScheduledDelivery() ? 'scheduled' : 'regular';
        $settings = $timeouts[$type];
        $deadline = $orderItem ? $this->acceptanceDeadlineForOrderItem($orderItem, $order) : null;

        return [
            'enabled' => (bool) $settings['enabled'],
            'minutes' => (int) $settings['minutes'],
            'type' => $type,
            'deadline' => $deadline?->format('M d, Y h:i:s A'),
        ];
    }

    private function rejectSellerAcceptAfterAcceptanceDeadline(OrderItem $orderItem, ?Order $order = null): ?array
    {
        $order ??= $orderItem->order;
        $deadline = $this->acceptanceDeadlineForOrderItem($orderItem, $order);

        if (! $order || ! $deadline || Carbon::now($deadline->timezone)->lt($deadline)) {
            return null;
        }

        // Deadline enforcement also runs in the seller action path so an expired
        // item cannot be accepted while waiting for the server scheduler.
        $this->autoRejectExpiredItemsForOrder($order);

        return [
            'success' => false,
            'message' => __('labels.order_acceptance_window_expired'),
            'data' => [
                'acceptance_deadline' => $deadline->toIso8601String(),
            ],
        ];
    }

    /**
     * Reject seller-unaccepted items whose acceptance deadline has passed.
     *
     * @return array{scanned: int, rejected: int, failed: int}
     */
    public function autoRejectExpiredUnacceptedOrders(): array
    {
        $result = ['scanned' => 0, 'rejected' => 0, 'failed' => 0];

        Order::whereNotIn('status', [
            OrderStatusEnum::REJECTED_BY_SELLER(),
            OrderStatusEnum::CANCELLED(),
            OrderStatusEnum::FAILED(),
            OrderStatusEnum::DELIVERED(),
        ])
            ->where(function ($query) {
                $query->whereNull('reschedule_status')
                    ->orWhere('reschedule_status', '!=', 'needs_reschedule');
            })
            ->whereHas('items', function ($query) {
                $query->whereIn('status', [
                    OrderItemStatusEnum::PENDING(),
                    OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
                ]);
            })
            ->with(['items.store', 'deliveryTimeSlot'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$result) {
                foreach ($orders as $order) {
                    $result['scanned']++;

                    try {
                        $result['rejected'] += $this->autoRejectExpiredItemsForOrder($order);
                    } catch (\Throwable $e) {
                        $result['failed']++;
                        Log::error('Auto-reject expired order acceptance failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $result;
    }

    private function normalizeAcceptanceTimeout(mixed $value, int $default, int $minimum): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $minutes = (int) $value;
        if ($minutes < $minimum || $minutes > self::MAX_ACCEPTANCE_TIMEOUT_MINUTES) {
            return $default;
        }

        return $minutes;
    }

    private function normalizeAcceptanceTimeoutEnabled(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Prefer the order snapshot, falling back to current settings for legacy rows.
     *
     * @return array{regular: array{enabled: bool, minutes: int}, scheduled: array{enabled: bool, minutes: int}}
     */
    private function acceptanceTimeoutSettingsForOrder(Order $order): array
    {
        $snapshot = $order->acceptance_timeout_snapshot;
        if (! is_array($snapshot)) {
            return $this->currentAcceptanceTimeoutSettings();
        }

        return [
            'regular' => [
                'enabled' => $this->normalizeAcceptanceTimeoutEnabled($snapshot['regular']['enabled'] ?? true),
                'minutes' => $this->normalizeAcceptanceTimeout(
                    $snapshot['regular']['minutes'] ?? null,
                    self::DEFAULT_REGULAR_ACCEPTANCE_TIMEOUT_MINUTES,
                    minimum: 1
                ),
            ],
            'scheduled' => [
                'enabled' => false,
                'minutes' => 0,
            ],
        ];
    }

    private function autoRejectExpiredItemsForOrder(Order $candidate): int
    {
        return DB::transaction(function () use ($candidate) {
            $order = Order::whereKey($candidate->id)
                ->lockForUpdate()
                ->with(['items.store', 'deliveryTimeSlot'])
                ->first();

            if (! $order) {
                return 0;
            }

            $rejectedCount = 0;
            foreach ($order->items as $item) {
                $deadline = $this->acceptanceDeadlineForOrderItem($item, $order);
                if (! $deadline || Carbon::now($deadline->timezone)->lt($deadline)) {
                    continue;
                }

                $oldStatus = $item->status->value ?? $item->status;
                $item->update(['status' => OrderItemStatusEnum::REJECTED()]);

                // Recalculate/refund with the same item-level semantics as seller rejection.
                $this->recalculateOrderAmounts($order->id, $item);
                $this->processOrderItemRefund($item, OrderItemStatusEnum::REJECTED());

                event(new OrderStatusUpdated(
                    orderItem: $item,
                    oldStatus: $oldStatus,
                    newStatus: OrderItemStatusEnum::REJECTED(),
                    source: 'system',
                    acceptanceDeadline: $deadline,
                ));

                $this->writeAutoRejectAudit($item, $oldStatus, $deadline);
                $rejectedCount++;
            }

            if ($rejectedCount > 0) {
                $statuses = $this->getOrderItemsStatuses($order->id);
                $order->update(['status' => $this->determineAutoRejectOrderStatus($statuses)]);
            }

            return $rejectedCount;
        });
    }

    private function determineAutoRejectOrderStatus(array $orderItemsStatuses): string
    {
        $totalCount = count($orderItemsStatuses);
        $rejectedCount = count(array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::REJECTED()));

        if ($totalCount > 0 && $rejectedCount === $totalCount) {
            return OrderStatusEnum::REJECTED_BY_SELLER();
        }

        if ($rejectedCount > 0) {
            return OrderStatusEnum::PARTIALLY_REJECTED();
        }

        return $this->determineSellerOrderStatus($orderItemsStatuses);
    }

    private function writeAutoRejectAudit(OrderItem $item, string $oldStatus, Carbon $deadline): void
    {
        OrderAuditLog::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'admin_id' => null,
            'action' => 'auto_rejected_unaccepted_order',
            'old_value' => ['status' => $oldStatus],
            'new_value' => [
                'status' => OrderItemStatusEnum::REJECTED(),
                'deadline' => $deadline->toIso8601String(),
            ],
            'reason' => __('labels.auto_rejected_acceptance_timeout') ?? 'Auto-rejected because the seller acceptance timeout expired.',
        ]);
    }

    /**
     * Create a new order from the cart
     *
     * @param  User  $user  The user placing the order
     * @param  array  $data  Order data including payment and address information
     * @return array Result containing success status, message, and order data
     */
    public function createOrder(User $user, array $data): array
    {
        $userLock = Cache::lock("user_order_placement_{$user->id}", 15);
        if (! $userLock->get()) {
            if (! empty($data['transaction_id'])) {
                $this->handleOrderCreationFailure($data);

                return [
                    'success' => false,
                    'message' => __('messages.order_duplicate_payment_refunded'),
                    'data' => ['code' => 'ORDER_DUPLICATE_REFUNDED'],
                ];
            }

            return [
                'success' => false,
                'message' => __('messages.order_already_in_progress'),
                'data' => ['code' => 'ORDER_IN_PROGRESS'],
            ];
        }

        try {
            DB::beginTransaction();
            // Step 1: Validate cart and system settings
            $cartValidation = $this->validateCartAndSettings($user);
            if (! $cartValidation['success']) {
                $this->handleOrderCreationFailure($data);

                return $cartValidation;
            }
            $cart = $cartValidation['cart'];

            $regularDeliveryValidation = $this->validateStoresOpenForRegularDelivery($cart, $data);
            if (! $regularDeliveryValidation['success']) {
                $this->handleOrderCreationFailure($data);
                DB::rollBack();

                return $regularDeliveryValidation;
            }

            if (($data['delivery_type'] ?? 'delivery') === 'pickup') {
                if (! Setting::isPickupEnabled()) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('messages.pickup_not_enabled_globally'),
                        'data' => [],
                    ];
                }

                $unsupportedStores = [];
                foreach ($cart->items as $item) {
                    if (! $item->store || ! $item->store->allows_pickup) {
                        $unsupportedStores[$item->store->id] = $item->store->name;
                    }
                }

                if (! empty($unsupportedStores)) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();
                    $storeNames = implode(', ', $unsupportedStores);

                    return [
                        'success' => false,
                        'message' => __('messages.stores_do_not_support_pickup', ['stores' => $storeNames]),
                        'data' => [
                            'unsupported_store_ids' => array_keys($unsupportedStores),
                            'unsupported_store_names' => array_values($unsupportedStores),
                        ],
                    ];
                }
            }

            // Step 2: Validate payment method
            $paymentValidation = $this->validatePaymentMethod($data, $user, $cart);
            if (! $paymentValidation['success']) {
                $this->handleOrderCreationFailure($data);
                DB::rollBack();

                return $paymentValidation;
            }

            $currencyValidation = $this->validateCartCurrencyForCheckout($cart, $data['payment_type'] ?? null);
            if (! $currencyValidation['success']) {
                $this->handleOrderCreationFailure($data);
                DB::rollBack();

                return $currencyValidation;
            }

            // Step 3: Validate address and delivery zone
            if (($data['delivery_type'] ?? 'delivery') === 'pickup') {
                $firstStore = $cart->items->first()?->store;
                if (! $firstStore) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('messages.cart_is_empty'),
                        'data' => [],
                    ];
                }

                $address = new Address;
                $address->latitude = $firstStore->latitude;
                $address->longitude = $firstStore->longitude;
                $address->id = 0;
                $address->address_line1 = $firstStore->address;
                $address->address_line2 = '';
                $address->city = $firstStore->city;
                $address->landmark = $firstStore->landmark ?? '';
                $address->state = $firstStore->state;
                $address->zipcode = $firstStore->zipcode;
                $address->country = $firstStore->country;
                $address->country_code = $firstStore->country_code;
                $address->address_type = 'other';
                $address->mobile = $user->mobile ?? $firstStore->contact_number;

                $data['address'] = $address;

                $zone = DeliveryZoneService::getZonesAtPoint($firstStore->latitude, $firstStore->longitude);
                $data['zone_id'] = $zone['exists'] ? $zone['zone_id'] : null;
            } else {
                $addressValidation = $this->validateAddressAndDeliveryZone($user, $data);
                if (! $addressValidation['success']) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return $addressValidation;
                }
                $data = $addressValidation['data'];
            }

            // Step 4: Validate stock and delivery availability
            if (($data['delivery_type'] ?? 'delivery') === 'pickup') {
                $stockVerification = $this->verifyCartItemsStock($cart);
                if ($stockVerification['success'] === false) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return $stockVerification;
                }
                $addonValidation = app(CartService::class)->validateCartAddonsForCheckout($cart);
                if ($addonValidation !== null) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return $addonValidation;
                }
            } else {
                $stockAndDeliveryValidation = $this->validateStockAndDeliveryAvailability($cart, $data['address']);
                if (! $stockAndDeliveryValidation['success']) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return $stockAndDeliveryValidation;
                }
            }
            // Step 4.5: Concurrency lock and capacity check for batch scheduled delivery slot
            $lock = null;
            $hasDynamicSlot = ! empty($data['scheduled_slot_start_time']) && ! empty($data['scheduled_slot_end_time']);

            if ($hasDynamicSlot) {
                $storeId = $cart->items->first()->store_id;
                $targetDate = (string) ($data['scheduled_date'] ?? today()->format('Y-m-d'));
                $startTime = $data['scheduled_slot_start_time'];
                $endTime = $data['scheduled_slot_end_time'];

                $lockKey = "store_{$storeId}_booking_{$targetDate}_{$startTime}_{$endTime}";
                $lock = Cache::lock($lockKey, 10);

                if (!$lock->get()) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => 'This slot is currently being booked by another customer. Please try again.',
                        'data' => [],
                    ];
                }

                $slotValidation = app(DeliveryScheduleService::class)->validateDynamicSlotForCart(
                    $cart,
                    $targetDate,
                    $startTime,
                    $endTime
                );
                if (!$slotValidation['is_valid']) {
                    $lock->release();
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => $slotValidation['message'],
                        'data' => [],
                    ];
                }

                if (empty($data['scheduled_time_slot_label'])) {
                    $data['scheduled_time_slot_label'] = Carbon::parse($startTime)->format('h:i A') . ' - ' . Carbon::parse($endTime)->format('h:i A');
                }
            } elseif (!empty($data['delivery_time_slot_id'])) {
                $slot = DeliveryTimeSlot::where('id', $data['delivery_time_slot_id'])->lockForUpdate()->first();
                if (!$slot || !$slot->is_active) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('messages.slot_not_found') ?? 'Delivery time slot not found or inactive.',
                        'data' => [],
                    ];
                }
                $targetDate = (string) ($data['scheduled_date'] ?? today()->format('Y-m-d'));
                if ($slot->getRemainingCapacityForDate($targetDate) <= 0) {
                    $this->handleOrderCreationFailure($data);
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('messages.slot_capacity_full') ?? 'Batch slot capacity is full for this date.',
                        'data' => [],
                    ];
                }
                if (empty($data['scheduled_time_slot_label'])) {
                    $data['scheduled_time_slot_label'] = ! empty($slot->label)
                        ? $slot->label
                        : (Carbon::parse($slot->start_time)->format('h:i A') . ' - ' . (Carbon::parse($slot->end_time)->format('h:i A')));
                }
            }

            if ($this->isHostedGatewayPayment($data['payment_type'])) {
                $hostedPaymentResult = $this->initiateHostedGatewayCheckout($user, $cart, $data);
                if ($lock) {
                    $lock->release();
                }
                DB::commit();

                return $hostedPaymentResult;
            }

            // Step 5: Create the order
            $order = $this->processOrderCreation($user, $cart, $data);

            // Step 6: Create order items and finalize
            $finalizationResult = $this->finalizeOrderCreation($order, $cart, $user, $data);
            if (!$finalizationResult['success']) {
                $this->handleOrderCreationFailure($data);
                DB::rollBack();
                if ($lock) {
                    $lock->release();
                }

                return $finalizationResult;
            }

            // Calculate staggered preparation schedule for the slot if order is scheduled
            if ($order->isScheduledDelivery() && $order->scheduled_date) {
                $store = $order->items->first()?->store;
                if ($store) {
                    app(PreparationSchedulerService::class)->schedulePrepForSlot(
                        $store,
                        $order->scheduled_date->format('Y-m-d'),
                        $order->scheduled_slot_start_time
                    );
                }
            }

            DB::commit();
            if ($lock) {
                $lock->release();
            }

            return [
                'success' => true,
                'message' => __('messages.order_created_successfully'),
                'data' => $finalizationResult['order'],
            ];
        } catch (ModelNotFoundException $e) {
            $this->handleOrderCreationFailure($data);
            DB::rollBack();
            if (isset($lock) && $lock) {
                $lock->release();
            }

            return [
                'success' => false,
                'message' => __('labels.model_not_found'),
                'data' => ['code' => 'MODEL_NOT_FOUND'],
            ];
        } catch (ValidationException $e) {
            $this->handleOrderCreationFailure($data);
            DB::rollBack();
            if (isset($lock) && $lock) {
                $lock->release();
            }

            return [
                'success' => false,
                'message' => $e->validator->errors()->first() ?: __('labels.validation_error'),
                'data' => ['errors' => $e->errors()],
            ];
        } catch (QueryException | PDOException $e) {
            $this->handleOrderCreationFailure($data);
            DB::rollBack();
            if (isset($lock) && $lock) {
                $lock->release();
            }
            Log::error('Database error creating order: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null,
            ]);

            $message = !empty($data['transaction_id'])
                ? __('messages.order_failed_payment_refunded')
                : __('messages.unable_to_process_order');

            return [
                'success' => false,
                'message' => $message,
                'data' => ['code' => 'ORDER_DATABASE_ERROR'],
            ];
        } catch (Throwable $e) {
            $this->handleOrderCreationFailure($data);
            DB::rollBack();
            if (isset($lock) && $lock) {
                $lock->release();
            }
            Log::error('Error creating order: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $message = ! empty($data['transaction_id'])
                ? __('messages.order_failed_payment_refunded')
                : __('messages.something_went_wrong');

            return [
                'success' => false,
                'message' => $message,
                'data' => ['code' => 'ORDER_CREATION_FAILED'],
            ];
        } finally {
            $userLock->release();
        }
    }

    /**
     * Validate cart and system settings
     */
    private function validateCartAndSettings(User $user): array
    {
        $settingService = app(SettingService::class);
        $settings = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());

        // Get user's cart
        $cart = CartService::getUserCart($user);
        $storeCount = CartService::cartStoreCount($user);

        if ($settings->value['checkoutType'] === 'single_store' && $storeCount > 1) {
            return [
                'success' => false,
                'message' => __('labels.checkout_type_single_store_error'),
                'data' => [],
            ];
        }

        if (! $cart || $cart->items->isEmpty()) {
            return [
                'success' => false,
                'message' => __('messages.cart_is_empty'),
                'data' => [],
            ];
        }

        $itemMaxAllowValidate = CartService::validateCartMaxItems($cart);
        if (! $itemMaxAllowValidate) {
            return [
                'success' => false,
                'message' => __('labels.cart_max_items_exceeded'),
                'data' => [],
            ];
        }

        // Ensure all stores in the cart are online before proceeding
        $offlineStores = $cart->items->filter(function ($item) {
            return $item->store && method_exists($item->store, 'isOffline') && $item->store->isOffline();
        })->map(function ($item) {
            return $item->store?->name;
        })->unique()->values()->all();

        if (! empty($offlineStores)) {
            return [
                'success' => false,
                'message' => __('messages.store_offline_cannot_place_order', ['stores' => implode(', ', $offlineStores)]),
                'data' => ['stores' => $offlineStores],
            ];
        }

        return [
            'success' => true,
            'cart' => $cart,
        ];
    }

    private function validateCartCurrencyForCheckout(Cart $cart, mixed $paymentType = null): array
    {
        $currencies = $this->cartCurrencyCodes($cart);
        if ($currencies->count() > 1) {
            return [
                'success' => false,
                'message' => 'Your cart contains stores with different currencies. Please checkout one currency at a time.',
                'data' => ['currencies' => $currencies->values()->all()],
            ];
        }

        if ($currencies->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Store currency is missing for one or more cart items.',
                'data' => [],
            ];
        }

        $currencyCode = $currencies->first();
        $walletCurrency = Wallet::where('user_id', $cart->user_id)
            ->ofType(WalletTypeEnum::CUSTOMER())
            ->value('currency_code');
        $availability = app(PaymentCurrencyAvailabilityService::class)
            ->availabilityForMethod((string) $paymentType, $currencyCode, $walletCurrency);

        if (! $availability['is_available']) {
            return [
                'success' => false,
                'message' => $availability['message'],
                'data' => [
                    'currency_code' => $currencyCode,
                    'available_payment_methods' => app(PaymentCurrencyAvailabilityService::class)
                        ->availabilityForCurrency($currencyCode, [], $walletCurrency),
                    'reason_code' => $availability['reason_code'],
                ],
            ];
        }

        return ['success' => true];
    }

    private function cartOrderCurrency(Cart $cart): string
    {
        $currencyCode = $this->cartCurrencyCodes($cart)->first();

        return app(CurrencyWriteGuard::class)->requireValid($currencyCode, 'Order');
    }

    private function cartCurrencyCodes(Cart $cart)
    {
        $cart->loadMissing('items.store');

        return $cart->items
            ->map(function ($item) {
                try {
                    return app(CurrencyWriteGuard::class)->requireValid($item->store?->currency_code, 'Cart store');
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function validateStoresOpenForRegularDelivery(Cart $cart, array $data): array
    {
        $deliveryType = $data['delivery_type'] ?? 'delivery';
        $isScheduledDelivery = ! empty($data['scheduled_date'])
            || ! empty($data['delivery_time_slot_id'])
            || (! empty($data['scheduled_slot_start_time']) && ! empty($data['scheduled_slot_end_time']));

        if ($deliveryType !== OrderDeliveryTypeEnum::DELIVERY() || $isScheduledDelivery) {
            return ['success' => true];
        }

        $closedStores = [];

        foreach ($cart->items as $item) {
            $store = $item->store;
            if (! $store || isset($closedStores[$store->id])) {
                continue;
            }

            $closedReason = $this->getRegularDeliveryStoreClosedReason($store);
            if ($closedReason !== null) {
                $closedStores[$store->id] = [
                    'id' => $store->id,
                    'name' => $store->name,
                    'status' => 'offline',
                    'day' => Carbon::now($store->timezone ?? config('app.timezone', 'UTC'))->format('l'),
                    'reason' => $closedReason,
                ];
            }
        }

        if (! empty($closedStores)) {
            return [
                'success' => false,
                'message' => __('messages.store_closed_cannot_place_order', [
                    'stores' => implode(', ', array_column($closedStores, 'name')),
                ]),
                'data' => ['stores' => array_values($closedStores)],
            ];
        }

        return ['success' => true];
    }

    private function getRegularDeliveryStoreClosedReason(Store $store): ?string
    {
        if ($store->is_temporarily_closed) {
            return $store->temporary_closure_reason ?: __('messages.store_temporarily_closed');
        }

        $operatingHours = $store->metadata['operating_hours'] ?? null;
        if (! is_array($operatingHours) || empty($operatingHours)) {
            return null;
        }

        $timezone = $store->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);
        $dayName = strtolower($now->format('l'));
        $dayConfig = $operatingHours[$dayName] ?? null;

        if (! is_array($dayConfig)) {
            return null;
        }

        if (isset($dayConfig['is_open']) && ! filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN)) {
            return __('messages.store_closed_on_selected_day');
        }

        if (empty($dayConfig['open_time']) || empty($dayConfig['close_time'])) {
            return __('messages.invalid_operating_hours');
        }

        $openTime = Carbon::parse($now->format('Y-m-d') . ' ' . $dayConfig['open_time'], $timezone);
        $closeTime = Carbon::parse($now->format('Y-m-d') . ' ' . $dayConfig['close_time'], $timezone);

        if ($closeTime->lte($openTime)) {
            $closeTime->addDay();
        }

        if ($now->lt($openTime) || $now->gte($closeTime)) {
            return __('messages.store_not_open_now');
        }

        $hasBreak = ! empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
        if ($hasBreak && ! empty($dayConfig['break_start_time']) && ! empty($dayConfig['break_end_time'])) {
            $breakStart = Carbon::parse($now->format('Y-m-d') . ' ' . $dayConfig['break_start_time'], $timezone);
            $breakEnd = Carbon::parse($now->format('Y-m-d') . ' ' . $dayConfig['break_end_time'], $timezone);

            if ($breakEnd->lte($breakStart)) {
                $breakEnd->addDay();
            }

            if ($now->gte($breakStart) && $now->lt($breakEnd)) {
                return __('messages.store_not_open_now');
            }
        }

        return null;
    }

    /**
     * Validate payment method
     */
    private function validatePaymentMethod(array $data, User $user, ?Cart $cart = null): array
    {
        if (($data['delivery_type'] ?? 'delivery') === 'pickup' && $data['payment_type'] === PaymentTypeEnum::COD()) {
            $allowed = false;
            if ($user) {
                $cart = $cart ?? CartService::getUserCart($user);
                if ($cart && $cart->items->isNotEmpty()) {
                    $storeId = $cart->items->first()->store_id;
                    $store = Store::find($storeId);
                    if ($store && $store->is_pickup_cod_enabled) {
                        $allowed = true;
                    }
                }
            }
            if (! $allowed) {
                return [
                    'success' => false,
                    'message' => __('messages.pickup_no_cod') ?: 'Pickup orders cannot use Cash on Delivery (COD) payment method.',
                    'data' => [],
                ];
            }
        }

        $transformedSetting = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
        $paymentSettings = $transformedSetting->toArray(request())['value'] ?? [];
        $paymentMethodEnabled = $paymentSettings[$data['payment_type']] ?? false;

        if ($paymentSettings['wallet'] === false && filter_var($data['use_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => false,
                'message' => __('labels.wallet_not_enabled'),
                'data' => [],
            ];
        }

        if ($paymentMethodEnabled === false) {
            return [
                'success' => false,
                'message' => __('labels.payment_method_not_enabled', ['payment_method' => ucfirst($data['payment_type'])]),
                'data' => [],
            ];
        }

        $paymentStatus = $this->paymentService->verifyOnlinePayment($data);
        if ($paymentStatus['success'] === false) {
            return [
                'success' => false,
                'message' => $paymentStatus['message'],
                'data' => [],
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate address and delivery zone
     */
    private function validateAddressAndDeliveryZone(User $user, array $data): array
    {
        $address = Address::where(['id' => $data['address_id'], 'user_id' => $user->id])->get()->first();
        if (! $address) {
            return [
                'success' => false,
                'message' => __('labels.address_not_found'),
                'data' => [],
            ];
        }
        $data['address'] = $address;

        $zone = DeliveryZoneService::getZonesAtPoint($data['address']['latitude'], $data['address']['longitude']);
        if ($zone['exists'] === false) {
            return [
                'success' => false,
                'message' => __('messages.delivery_zone_not_found'),
                'data' => [],
            ];
        }
        $data['zone_id'] = $zone['zone_id'];

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Validate stock and delivery availability
     */
    private function validateStockAndDeliveryAvailability(Cart $cart, Address $address): array
    {
        // Verify stock availability for all cart items before creating order
        $stockVerification = $this->verifyCartItemsStock($cart);
        if ($stockVerification['success'] === false) {
            return $stockVerification;
        }

        $addonValidation = app(CartService::class)->validateCartAddonsForCheckout($cart);
        if ($addonValidation !== null) {
            return $addonValidation;
        }

        // Check if all cart items are deliverable to the address location
        $deliveryCheck = DeliveryZoneService::checkDeliveryAvailability($cart, $address->latitude, $address->longitude);
        if (! empty($deliveryCheck['removed_items']) || ! empty($deliveryCheck['reassigned_items'])) {
            app(CartActivityService::class)->record($cart);
        }

        if (! empty($deliveryCheck['removed_items'])) {
            $undeliverableItems = array_map(function ($item) {
                return $item['product_name'] . ' from ' . $item['store_name'];
            }, $deliveryCheck['removed_items']);

            return [
                'success' => false,
                'message' => __('messages.items_not_deliverable_to_address'),
                'data' => [
                    'undeliverable_items' => $undeliverableItems,
                    'details' => $deliveryCheck['removed_items'],
                ],
            ];
        }

        return ['success' => true];
    }

    /**
     * Process order creation
     */
    private function processOrderCreation(User $user, Cart $cart, array $data): Order
    {
        $setting = $this->settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
        $data['minimumCartAmount'] = $setting->value['minimumCartAmount'] ?? 1;

        return $this->createOrderFromCart($user, $cart, $data);
    }

    /**
     * Process payment transactions
     */
    private function processPaymentTransactions(User $user, Order $order, array $data): array
    {
        // Create payment transaction for online payments
        if (filter_var($data['use_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $walletPaymentData = [
                'order_id' => $order->id,
                'amount' => $order->wallet_balance,
                'description' => "Wallet balance of $order->currency_code $order->wallet_balance was used for Order #$order->id.",
            ];
            $walletTransaction = WalletService::deductBalance($user->id, $walletPaymentData);
            if ($walletTransaction['success'] === false) {
                Log::error('Error deducting wallet balance: ' . $walletTransaction['message']);

                return ['success' => false, 'message' => $walletTransaction['message']];
            }
        }
        if ($data['payment_type'] == PaymentTypeEnum::WALLET() && ($walletTransaction['success'] ?? false)) {
            $paymentInfo = [
                'transaction_id' => 'wallet_' . time(),
                'amount' => $order->wallet_balance,
                'currency' => $order->currency_code,
                'payment_method' => $data['payment_type'],
                'message' => 'Payment verification done',
                'payment_status' => PaymentStatusEnum::COMPLETED(),
            ];
            $this->paymentService->makeOrderPaymentTransaction($order, $paymentInfo, PaymentStatusEnum::COMPLETED());
            Order::capturePaymentFromOrder($order);
            OrderItem::capturePayment($order->id);

            return ['success' => true, 'message' => 'Wallet payment & order capture done'];
        }

        return ['success' => true, 'message' => 'Payment verification done'];
    }

    /**
     * Finalize order creation
     */
    private function finalizeOrderCreation(Order $order, Cart $cart, $user, array $data): array
    {
        // Create order items and seller orders
        $orderItemResponse = $this->createOrderItemsAndSellerOrders($order, $cart, $data);
        if ($orderItemResponse['success'] === false) {
            return [
                'success' => false,
                'message' => $orderItemResponse['message'],
                'data' => [],
            ];
        }
        //  Handle payment processing
        $paymentResult = $this->processPaymentTransactions($user, $order, $data);
        if (! $paymentResult['success']) {
            return [
                'success' => false,
                'message' => $paymentResult['message'],
                'data' => [],
            ];
        }

        // pre-payment webhook verification
        $this->paymentService->prePaymentOrderVerification(transactionId: $data['transaction_id'] ?? '', order: $order);

        // post-payment initialization
        $postPayment = $this->paymentService->postPaymentInitialtion(order: $order, redirectUrl: $data['redirect_url'] ?? null);

        // Load order relationships for response
        $order->refresh();
        $order->load(['items.product', 'items.variant', 'items.store', 'items.addons', 'user', 'sellerOrders.seller.user']);
        $order->payment_response = $postPayment['data'] ?? null;
        // Hosted gateways create orders before payment confirmation, so keep the cart until payment is actually captured.
        if ($this->shouldClearCartAfterOrderPlacement($order)) {
            $cart->items()->delete();
            Cart::where('id', $cart->id)->update(['rider_tip_amount' => 0.00, 'use_loyalty_points' => false]);
            app(CartActivityService::class)->invalidate($cart);
        }
        event(new OrderPlaced($order));

        return [
            'success' => true,
            'order' => $order,
        ];
    }

    private function shouldClearCartAfterOrderPlacement(Order $order): bool
    {
        if ($order->payment_status === PaymentStatusEnum::COMPLETED()) {
            return true;
        }

        return $order->payment_method === PaymentTypeEnum::COD();
    }

    public function clearCartAfterCapturedOrder(Order|int $order): void
    {
        $orderModel = $order instanceof Order ? $order : Order::find($order);
        if (! $orderModel) {
            return;
        }

        if (! $orderModel->user_id) {
            return;
        }

        Cart::where('user_id', $orderModel->user_id)
            ->get()
            ->each(function (Cart $cart): void {
                $cart->items()->where('save_for_later', '0')->delete();
                $cart->update(['rider_tip_amount' => 0.00, 'use_loyalty_points' => false]);
                app(CartActivityService::class)->invalidate($cart);
            });
    }

    private function isHostedGatewayPayment(string $paymentMethod): bool
    {
        return in_array($paymentMethod, [
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
        ], true);
    }

    private function initiateHostedGatewayCheckout(User $user, Cart $cart, array $data): array
    {
        $cartService = app(CartService::class);
        $paymentSummary = $cartService->getPaymentSummary(
            cart: $cart,
            latitude: $data['address']['latitude'] ?? null,
            longitude: $data['address']['longitude'] ?? null,
            isRushDelivery: $data['rush_delivery'] ?? false,
            useWallet: $data['use_wallet'] ?? false,
            promoCode: $data['promo_code'] ?? null,
            useLoyaltyPoints: array_key_exists('use_loyalty_points', $data) ? (bool) $data['use_loyalty_points'] : null
        );
        $paymentSummary = $this->normalizeHostedGatewayPaymentSummary($paymentSummary, $data);
        if ((float) ($paymentSummary['payable_amount'] ?? 0) < 1) {
            return [
                'success' => false,
                'message' => 'Online payment amount must be at least 1.',
                'data' => [
                    'payable_amount' => (float) ($paymentSummary['payable_amount'] ?? 0),
                ],
            ];
        }

        $snapshot = $this->buildHostedGatewayCheckoutSnapshot($cart, $data, $paymentSummary);
        $orderCurrency = $this->cartOrderCurrency($cart);
        app(PaymentCurrencyEnforcementService::class)->ensureAvailable((string) $data['payment_type'], $orderCurrency);

        $transaction = OrderPaymentTransaction::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => null,
            'user_id' => $user->id,
            'transaction_id' => 'PENDING-' . Str::upper(Str::random(16)),
            'amount' => $paymentSummary['payable_amount'],
            'currency' => $orderCurrency,
            // Phase 2 snapshot: pending hosted checkouts have no order yet,
            // so store the intended order currency separately from gateway data.
            'order_currency_code' => $orderCurrency,
            'gateway_currency_code' => $orderCurrency,
            'gateway_amount' => $paymentSummary['payable_amount'],
            'payment_method' => $data['payment_type'],
            'payment_status' => PaymentStatusEnum::PENDING(),
            'message' => 'Hosted checkout pending payment confirmation',
            'payment_details' => [
                'checkout_snapshot' => $snapshot,
            ],
        ]);

        $paymentResponse = match ($data['payment_type']) {
            PaymentTypeEnum::FLUTTERWAVE() => app(FlutterwaveController::class)->createPendingOrderPayment($transaction, $snapshot),
            PaymentTypeEnum::XENDIT() => app(XenditController::class)->createPendingOrderPayment($transaction, $snapshot),
            default => app(MercadoPagoController::class)->createPendingOrderPayment($transaction, $snapshot),
        };

        if (! ($paymentResponse['success'] ?? false)) {
            $transaction->update([
                'payment_status' => PaymentStatusEnum::FAILED(),
                'message' => $paymentResponse['message'] ?? 'Unable to initialize hosted checkout',
            ]);

            return [
                'success' => false,
                'message' => $paymentResponse['message'] ?? __('labels.something_went_wrong'),
                'data' => $paymentResponse['data'] ?? [],
            ];
        }

        $responseData = array_merge($paymentResponse['data'] ?? [], [
            'status' => 'payment_pending',
            'payment_method' => $data['payment_type'],
            'transaction_uuid' => $transaction->uuid,
            'order' => null,
        ]);

        return [
            'success' => true,
            'message' => __('labels.payment_intent_created_successfully'),
            'data' => $responseData,
        ];
    }

    private function normalizeHostedGatewayPaymentSummary(array $paymentSummary, array $data): array
    {
        if (($data['delivery_type'] ?? 'delivery') !== 'pickup') {
            return $paymentSummary;
        }

        $pickupOrderTotal = (float) ($paymentSummary['items_total'] ?? 0);
        if (($paymentSummary['promo_applied']['promo_mode'] ?? null) === PromoModeEnum::INSTANT()) {
            $pickupOrderTotal = max(0, $pickupOrderTotal - (float) ($paymentSummary['promo_discount'] ?? 0));
        }

        $pickupPayableAmount = $pickupOrderTotal;
        $walletAmountUsed = 0;
        if (filter_var($data['use_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $walletAmountUsed = min((float) ($paymentSummary['wallet_balance'] ?? 0), $pickupPayableAmount);
            $pickupPayableAmount -= $walletAmountUsed;
        }

        $paymentSummary['per_store_drop_off_fee'] = 0;
        $paymentSummary['delivery_charges'] = 0;
        $paymentSummary['regular_delivery_charge'] = 0;
        $paymentSummary['rush_delivery_charge'] = 0;
        $paymentSummary['handling_charges'] = 0;
        $paymentSummary['delivery_distance_charges'] = 0;
        $paymentSummary['delivery_distance_km'] = 0;
        $paymentSummary['total_delivery_charges'] = 0;
        $paymentSummary['estimated_delivery_time'] = 0;
        $paymentSummary['wallet_amount_used'] = $walletAmountUsed;
        $paymentSummary['payable_amount'] = $pickupPayableAmount;
        $paymentSummary['order_total'] = $pickupOrderTotal;

        return $paymentSummary;
    }

    private function buildHostedGatewayCheckoutSnapshot(Cart $cart, array $data, array $paymentSummary): array
    {
        return [
            'checkout_data' => $this->normalizeCheckoutDataForSnapshot($data),
            'cart_signature' => $this->cartSignature($cart),
            'cart_items' => $this->hostedGatewayCartItemsSnapshot($cart),
            'payment_summary' => $paymentSummary,
            'redirect_url' => $data['redirect_url'] ?? null,
            'created_at' => now()->toISOString(),
        ];
    }

    private function normalizeCheckoutDataForSnapshot(array $data): array
    {
        if (($data['address'] ?? null) instanceof Address) {
            $data['address'] = $data['address']->only([
                'id',
                'address_line1',
                'address_line2',
                'city',
                'landmark',
                'state',
                'zipcode',
                'country',
                'country_code',
                'address_type',
                'mobile',
                'latitude',
                'longitude',
            ]);
        }

        return $data;
    }

    private function cartSignature(Cart $cart): array
    {
        $cart->loadMissing(['items.addons']);

        return $cart->items
            ->where('save_for_later', '0')
            ->map(function ($item) {
                return [
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'store_id' => $item->store_id,
                    'quantity' => (float) $item->quantity,
                    'addons' => $item->addons
                        ->map(fn($addon) => [
                            'addon_group_id' => $addon->addon_group_id,
                            'addon_item_id' => $addon->addon_item_id,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function hostedGatewayCartItemsSnapshot(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'items.variant', 'items.store', 'items.addons']);

        return $cart->items
            ->where('save_for_later', '0')
            ->map(fn($item): array => [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'store_id' => $item->store_id,
                'quantity' => (float) $item->quantity,
                'addons' => $item->addons
                    ->map(fn($addon): array => [
                        'addon_group_id' => $addon->addon_group_id,
                        'addon_item_id' => $addon->addon_item_id,
                        'price' => (float) $addon->price,
                        'metadata' => $addon->metadata,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function cartMatchesSnapshot(Cart $cart, array $snapshot): bool
    {
        return $this->comparableCartSignature($this->cartSignature($cart)) === $this->comparableCartSignature($snapshot['cart_signature'] ?? []);
    }

    private function buildCartFromHostedSnapshot(User $user, array $snapshot): ?Cart
    {
        $snapshotItems = $snapshot['cart_items'] ?? $snapshot['cart_signature'] ?? [];
        if (empty($snapshotItems)) {
            return null;
        }

        $cart = new Cart;
        $cart->forceFill([
            'user_id' => $user->id,
            'rider_tip_amount' => (float) ($snapshot['payment_summary']['rider_tip_amount'] ?? 0),
            'use_loyalty_points' => (bool) ($snapshot['checkout_data']['use_loyalty_points'] ?? false),
        ]);

        $items = collect($snapshotItems)
            ->map(function (array $snapshotItem): ?CartItem {
                $product = Product::find($snapshotItem['product_id'] ?? null);
                $variant = ProductVariant::with(['storeProductVariants.productVariant.product.taxClasses.taxRates'])
                    ->find($snapshotItem['product_variant_id'] ?? null);
                $store = Store::find($snapshotItem['store_id'] ?? null);

                if (! $product || ! $variant || ! $store) {
                    return null;
                }

                $cartItem = new CartItem;
                $cartItem->forceFill([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'store_id' => $store->id,
                    'quantity' => (float) ($snapshotItem['quantity'] ?? 0),
                    'save_for_later' => '0',
                ]);
                $cartItem->setRelation('product', $product);
                $cartItem->setRelation('variant', $variant);
                $cartItem->setRelation('store', $store);
                $cartItem->setRelation('addons', collect($snapshotItem['addons'] ?? [])->map(function (array $addon): CartItemAddon {
                    $cartItemAddon = new CartItemAddon;
                    $cartItemAddon->forceFill([
                        'addon_group_id' => $addon['addon_group_id'] ?? null,
                        'addon_item_id' => $addon['addon_item_id'] ?? null,
                        'price' => (float) ($addon['price'] ?? 0),
                        'metadata' => $addon['metadata'] ?? null,
                    ]);

                    return $cartItemAddon;
                }));

                return $cartItem;
            })
            ->filter()
            ->values();

        if ($items->isEmpty() || $items->count() !== count($snapshotItems)) {
            return null;
        }

        $cart->setRelation('items', $items);

        return $cart;
    }

    private function comparableCartSignature(array $signature): array
    {
        return collect($signature)
            ->map(function (array $item): array {
                $addons = collect($item['addons'] ?? [])
                    ->map(fn(array $addon): array => [
                        'addon_group_id' => $addon['addon_group_id'] ?? null,
                        'addon_item_id' => $addon['addon_item_id'] ?? null,
                    ])
                    ->sortBy(fn(array $addon): string => ($addon['addon_group_id'] ?? '') . ':' . ($addon['addon_item_id'] ?? ''))
                    ->values()
                    ->all();

                return [
                    'product_id' => $item['product_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'store_id' => $item['store_id'] ?? null,
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'addons' => $addons,
                ];
            })
            ->sortBy(fn(array $item): string => implode(':', [
                $item['store_id'] ?? '',
                $item['product_id'] ?? '',
                $item['product_variant_id'] ?? '',
                $item['quantity'],
                md5(json_encode($item['addons'])),
            ]))
            ->values()
            ->all();
    }

    public function createConfirmedHostedGatewayOrder(OrderPaymentTransaction $transaction): ?Order
    {
        if ($transaction->order_id) {
            return $transaction->order;
        }

        $snapshot = $transaction->payment_details['checkout_snapshot'] ?? null;
        if (! is_array($snapshot)) {
            Log::error('Hosted checkout payment confirmed without checkout snapshot', ['transaction_id' => $transaction->id]);
            $this->paymentService->processOrderRefund($transaction->payment_method, $transaction->transaction_id);
            $transaction->update([
                'payment_status' => PaymentStatusEnum::REFUNDED(),
                'message' => 'Payment confirmed without checkout snapshot; automatically refunded',
            ]);

            return null;
        }

        $user = User::find($transaction->user_id);
        $cart = $user ? CartService::getUserCart($user) : null;
        if (! $user) {
            Log::error('Hosted checkout payment confirmed without a valid user', ['transaction_id' => $transaction->id]);
            $this->paymentService->processOrderRefund($transaction->payment_method, $transaction->transaction_id);
            $transaction->update([
                'payment_status' => PaymentStatusEnum::REFUNDED(),
                'message' => 'Payment confirmed but user was not found before order creation; automatically refunded',
            ]);

            return null;
        }

        if (! $cart || ! $this->cartMatchesSnapshot($cart, $snapshot)) {
            $cart = $this->buildCartFromHostedSnapshot($user, $snapshot);
        }

        if (! $cart) {
            Log::error('Hosted checkout payment confirmed but checkout snapshot could not rebuild cart', ['transaction_id' => $transaction->id]);
            $this->paymentService->processOrderRefund($transaction->payment_method, $transaction->transaction_id);
            $transaction->update([
                'payment_status' => PaymentStatusEnum::REFUNDED(),
                'message' => 'Payment confirmed but checkout snapshot could not create order; automatically refunded',
            ]);

            return null;
        }

        $data = $snapshot['checkout_data'] ?? [];
        if (isset($data['address']) && is_array($data['address'])) {
            $address = new Address;
            $address->forceFill($data['address']);
            $data['address'] = $address;
        }

        try {
            return DB::transaction(function () use ($user, $cart, $data, $transaction) {
                $stockVerification = ($data['delivery_type'] ?? 'delivery') === 'pickup'
                    ? $this->verifyCartItemsStock($cart)
                    : $this->validateStockAndDeliveryAvailability($cart, $data['address']);

                if (! ($stockVerification['success'] ?? false)) {
                    $this->paymentService->processOrderRefund($transaction->payment_method, $transaction->transaction_id);
                    $transaction->update([
                        'payment_status' => PaymentStatusEnum::REFUNDED(),
                        'message' => ($stockVerification['message'] ?? 'Checkout is no longer valid') . ' after payment confirmation; automatically refunded',
                    ]);

                    return null;
                }

                $order = $this->processOrderCreation($user, $cart, $data);
                $orderItemResponse = $this->createOrderItemsAndSellerOrders($order, $cart, $data);
                if (! ($orderItemResponse['success'] ?? false)) {
                    $this->paymentService->processOrderRefund($transaction->payment_method, $transaction->transaction_id);
                    $transaction->update([
                        'payment_status' => PaymentStatusEnum::REFUNDED(),
                        'message' => ($orderItemResponse['message'] ?? 'Order creation failed after payment confirmation') . '; automatically refunded',
                    ]);

                    return null;
                }

                $transaction->update([
                    'order_id' => $order->id,
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                    'message' => 'Payment captured and order created',
                ]);
                Order::capturePaymentFromOrder($order);
                OrderItem::capturePayment($order->id);

                Cart::where('user_id', $user->id)
                    ->get()
                    ->each(function (Cart $userCart): void {
                        $userCart->items()->where('save_for_later', '0')->delete();
                        $userCart->update(['rider_tip_amount' => 0.00, 'use_loyalty_points' => false]);
                        app(CartActivityService::class)->invalidate($userCart);
                    });

                $order->load(['items.product', 'items.variant', 'items.store', 'items.addons', 'user', 'sellerOrders.seller.user']);
                event(new OrderPlaced($order));

                return $order->fresh();
            });
        } catch (\Throwable $e) {
            $this->paymentService->processOrderRefund($transaction->payment_method, $transaction->transaction_id);
            $transaction->update([
                'payment_status' => PaymentStatusEnum::REFUNDED(),
                'message' => 'Hosted checkout order creation failed after payment confirmation; automatically refunded',
            ]);
            Log::error('Hosted checkout order creation failed after payment confirmation', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Handle order creation failure
     */
    private function handleOrderCreationFailure(array $data): ?array
    {
        $paymentType = is_object($data['payment_type'] ?? null) && property_exists($data['payment_type'], 'value')
            ? $data['payment_type']->value
            : (string) ($data['payment_type'] ?? '');

        if (
            ! empty($data['transaction_id']) &&
            !in_array(strtolower($paymentType), ['cod', 'wallet'], true)
        ) {
            return $this->paymentService->processOrderRefund(
                paymentMethod: $data['payment_type'],
                transactionId: $data['transaction_id']
            );
        }

        return null;
    }

    /**
     * Create an order from cart data
     *
     * @param  User  $user  The user placing the order
     * @param  Cart  $cart  The user's cart
     * @param  array  $data  Order data
     * @return array The created order
     *
     * @throws Exception
     */
    private function createOrderFromCart(
        User $user,
        Cart $cart,
        array $data
    ): mixed {
        // Calculate cart totals
        $cartService = app(CartService::class);
        $useLoyaltyPoints = array_key_exists('use_loyalty_points', $data)
            ? (bool) $data['use_loyalty_points']
            : null;

        $paymentSummary = $cartService->getPaymentSummary(
            cart: $cart,
            latitude: $data['address']['latitude'] ?? null,
            longitude: $data['address']['longitude'] ?? null,
            isRushDelivery: $data['rush_delivery'] ?? false,
            useWallet: $data['use_wallet'] ?? false,
            promoCode: $data['promo_code'] ?? null,
            useLoyaltyPoints: $useLoyaltyPoints
        );

        $isPickup = ($data['delivery_type'] ?? 'delivery') === 'pickup';
        if ($isPickup) {
            $pickupOrderTotal = (float) ($paymentSummary['items_total'] ?? 0);
            if (($paymentSummary['promo_applied']['promo_mode'] ?? null) === PromoModeEnum::INSTANT()) {
                $pickupOrderTotal = max(0, $pickupOrderTotal - (float) ($paymentSummary['promo_discount'] ?? 0));
            }

            $pickupPayableAmount = $pickupOrderTotal;
            $walletAmountUsed = 0;
            if (filter_var($data['use_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $walletAmountUsed = min((float) ($paymentSummary['wallet_balance'] ?? 0), $pickupPayableAmount);
                $pickupPayableAmount -= $walletAmountUsed;
            }

            $paymentSummary['per_store_drop_off_fee'] = 0;
            $paymentSummary['delivery_charges'] = 0;
            $paymentSummary['regular_delivery_charge'] = 0;
            $paymentSummary['rush_delivery_charge'] = 0;
            $paymentSummary['handling_charges'] = 0;
            $paymentSummary['delivery_distance_charges'] = 0;
            $paymentSummary['delivery_distance_km'] = 0;
            $paymentSummary['total_delivery_charges'] = 0;
            $paymentSummary['estimated_delivery_time'] = 0;
            $paymentSummary['wallet_amount_used'] = $walletAmountUsed;
            $paymentSummary['payable_amount'] = $pickupPayableAmount;
            $paymentSummary['order_total'] = $pickupOrderTotal;
        }

        if ((float) ($paymentSummary['items_total'] ?? 0) < (float) $data['minimumCartAmount']) {
            throw new Exception(__('labels.minimum_cart_amount_not_met', ['amount' => $data['minimumCartAmount']]));
        }

        if ($data['payment_type'] === PaymentTypeEnum::COD() || $data['payment_type'] === PaymentTypeEnum::COD->value || $data['payment_type'] === 'cod') {
            $codCharges = (float) ($paymentSummary['cod_charges'] ?? 0);
            if ($codCharges > 0) {
                $paymentSummary['payable_amount'] += $codCharges;
                $paymentSummary['order_total'] += $codCharges;
            }
        }

        $deliveryOtpMode = $this->currentDeliveryOtpMode();
        $deliveryOtpRequired = $this->shouldCreateOrderWiseDeliveryOtp($cart, $data, $deliveryOtpMode);
        $isScheduledDelivery = ! empty($data['scheduled_date'])
            || ! empty($data['delivery_time_slot_id'])
            || (! empty($data['scheduled_slot_start_time']) && ! empty($data['scheduled_slot_end_time']));

        $loyaltySummary = $paymentSummary['loyalty_summary'] ?? [];

        // Create order
        $orderCurrency = $this->cartOrderCurrency($cart);

        $order = Order::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'slug' => Str::slug('order-' . now()->format('YmdHis') . '-' . $user->id . '-' . Str::lower(Str::random(6))),
            'email' => $user->email,
            'ip_address' => request()->ip(),
            'currency_code' => $orderCurrency,
            // Phase 2 snapshot: this is the accounting/base currency for new
            // orders only. Existing orders are not backfilled here.
            'base_currency_code' => $orderCurrency,
            'currency_rate' => 1, // This should be dynamic based on settings
            'payment_method' => $data['payment_type'],
            'payment_status' => PaymentStatusEnum::PENDING(),
            'fulfillment_type' => 'hyperlocal',
            'is_rush_order' => (bool) ($data['rush_delivery'] ?? false),
            'estimated_delivery_time' => $isScheduledDelivery ? null : ($paymentSummary['estimated_delivery_time'] ?? null),
            'delivery_time_slot_id' => $data['delivery_time_slot_id'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'scheduled_time_slot_label' => $data['scheduled_time_slot_label'] ?? ($data['scheduled_date'] ?? null ? 'Scheduled for Day' : null),
            'scheduled_slot_start_time' => $data['scheduled_slot_start_time'] ?? null,
            'scheduled_slot_end_time' => $data['scheduled_slot_end_time'] ?? null,
            'delivery_zone_id' => $data['zone_id'] ?? null,
            'delivery_boy_id' => null, // Will be assigned later
            // Snapshot the delivery OTP mode so future admin setting changes do not affect this order.
            'delivery_otp_mode' => $deliveryOtpMode,
            'delivery_otp' => $deliveryOtpRequired ? (string) mt_rand(100000, 999999) : null,
            'delivery_otp_verified' => false,
            'acceptance_timeout_snapshot' => $this->orderAcceptanceTimeoutSnapshot(),
            'wallet_balance' => $paymentSummary['wallet_amount_used'] ?? 0,
            'promo_code' => $paymentSummary['promo_code'] ?? null,
            'promo_discount' => $paymentSummary['promo_discount'] ?? 0,
            'gift_card' => $data['gift_card'] ?? null,
            'gift_card_discount' => $data['gift_card_discount'] ?? 0,
            'delivery_charge' => $paymentSummary['total_delivery_charges'] ?? 0,
            'handling_charges' => $paymentSummary['handling_charges'] ?? 0,
            'per_store_drop_off_fee' => $paymentSummary['per_store_drop_off_fee'] ?? 0,
            'rider_tip_amount' => $paymentSummary['rider_tip_amount'] ?? 0,
            'loyalty_points_redeemed' => (int) ($loyaltySummary['loyalty_points_redeemed'] ?? 0),
            'loyalty_points_discount_amount' => (float) ($loyaltySummary['loyalty_points_discount_amount'] ?? 0.00),
            'loyalty_points_earned' => (int) ($loyaltySummary['potential_points_to_earn'] ?? 0),
            'subtotal' => $paymentSummary['items_total'],
            'total_payable' => $paymentSummary['payable_amount'],
            'final_total' => $paymentSummary['order_total'],
            'status' => ($data['payment_type'] === PaymentTypeEnum::COD()) ? OrderStatusEnum::AWAITING_STORE_RESPONSE() : OrderStatusEnum::PENDING(),
            // Billing info
            'billing_name' => $user->name,
            'billing_address_1' => $isPickup ? null : $data['address']['address_line1'],
            'billing_address_2' => $isPickup ? null : $data['address']['address_line2'],
            'billing_landmark' => $isPickup ? null : ($data['address']['landmark'] ?? ''),
            'billing_zip' => $isPickup ? null : $data['address']['zipcode'],
            'billing_phone' => $isPickup ? null : $data['address']['mobile'],
            'billing_address_type' => $isPickup ? 'other' : $data['address']['address_type'],
            'billing_latitude' => $isPickup ? null : $data['address']['latitude'],
            'billing_longitude' => $isPickup ? null : $data['address']['longitude'],
            'billing_city' => $isPickup ? null : $data['address']['city'],
            'billing_state' => $isPickup ? null : $data['address']['state'],
            'billing_country' => $isPickup ? null : $data['address']['country'],
            'billing_country_code' => $isPickup ? null : $data['address']['country_code'],

            // Shipping info (same as billing)
            'shipping_name' => $user->name,
            'shipping_address_1' => $isPickup ? null : $data['address']['address_line1'],
            'shipping_address_2' => $isPickup ? null : $data['address']['address_line2'],
            'shipping_landmark' => $isPickup ? null : ($data['address']['landmark'] ?? ''),
            'shipping_zip' => $isPickup ? null : $data['address']['zipcode'],
            'shipping_phone' => $isPickup ? null : $data['address']['mobile'],
            'shipping_address_type' => $isPickup ? 'other' : $data['address']['address_type'],
            'shipping_latitude' => $isPickup ? null : $data['address']['latitude'],
            'shipping_longitude' => $isPickup ? null : $data['address']['longitude'],
            'shipping_city' => $isPickup ? null : $data['address']['city'],
            'shipping_state' => $isPickup ? null : $data['address']['state'],
            'shipping_country' => $isPickup ? null : $data['address']['country'],
            'shipping_country_code' => $isPickup ? null : $data['address']['country_code'],

            // order note
            'order_note' => $data['order_note'] ?? '',
        ]);

        if ($order->loyalty_points_redeemed > 0) {
            app(LoyaltyPointService::class)->adjustBalance(
                userId: $user->id,
                points: -$order->loyalty_points_redeemed,
                type: LoyaltyPointTransactionTypeEnum::REDEEMED(),
                orderId: $order->id,
                description: "Redeemed {$order->loyalty_points_redeemed} points on order #{$order->id}"
            );
        }

        if ($order->loyalty_points_earned > 0) {
            app(LoyaltyPointService::class)->adjustBalance(
                userId: $user->id,
                points: $order->loyalty_points_earned,
                type: LoyaltyPointTransactionTypeEnum::EARNED(),
                orderId: $order->id,
                description: "Pending points earned on order #{$order->id}",
                status: LoyaltyPointTransactionStatusEnum::PENDING()
            );
        }

        if (! empty($paymentSummary['promo_applied'])) {
            Promo::where('id', $paymentSummary['promo_applied']['id'])
                ->increment('usage_count');

            OrderPromoLine::create([
                'order_id' => $order->id,
                'promo_id' => $paymentSummary['promo_applied']['id'],
                'promo_code' => $paymentSummary['promo_applied']['code'],
                'discount_amount' => $paymentSummary['promo_discount'],
                'cashback_flag' => ! ($paymentSummary['promo_applied']['promo_mode'] === PromoModeEnum::INSTANT()),
                'is_awarded' => $paymentSummary['promo_applied']['promo_mode'] === PromoModeEnum::INSTANT(),
            ]);
        }

        return $order;
    }

    /**
     * Create order items and seller orders from cart items
     *
     * @param  Order  $order  The order to create items for
     * @param  Cart  $cart  The cart containing items
     */
    private function createOrderItemsAndSellerOrders(
        Order $order,
        Cart $cart,
        array $data
    ): array {
        try {
            // Group cart items by store
            $itemsByStore = $cart->items->groupBy('store_id');
            $deliveryType = $data['delivery_type'] ?? 'delivery';

            foreach ($itemsByStore as $storeItems) {
                $this->processStoreItems($order, $storeItems, $deliveryType);
            }

            return [
                'success' => true,
                'message' => __('labels.success'),
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error creating order items and seller orders', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.order_creation_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function currentDeliveryOtpMode(): string
    {
        $settings = $this->settingService->getSettingValues(SettingTypeEnum::SYSTEM());
        $mode = $settings['deliveryOtpMode'] ?? DeliveryOtpModeEnum::ITEM_WISE();

        return in_array($mode, DeliveryOtpModeEnum::values(), true)
            ? $mode
            : DeliveryOtpModeEnum::ITEM_WISE();
    }

    private function deliveryOtpModeForOrder(Order $order): string
    {
        $mode = $order->delivery_otp_mode ?? DeliveryOtpModeEnum::ITEM_WISE();

        return in_array($mode, DeliveryOtpModeEnum::values(), true)
            ? $mode
            : DeliveryOtpModeEnum::ITEM_WISE();
    }

    private function shouldCreateOrderWiseDeliveryOtp(Cart $cart, array $data, string $deliveryOtpMode): bool
    {
        if (($data['delivery_type'] ?? 'delivery') === OrderDeliveryTypeEnum::PICKUP()) {
            return false;
        }

        if ($deliveryOtpMode !== DeliveryOtpModeEnum::ORDER_WISE()) {
            return false;
        }

        return $cart->items->contains(fn($item) => (bool) ($item->product?->requires_otp ?? false));
    }

    private function orderRequiresDeliveryOtp(Order $order): bool
    {
        return $order->items->contains(function ($item) {
            $itemRequiresOtp = $item->requires_otp ?? $item->product?->requires_otp ?? false;

            return $itemRequiresOtp && ! ($item->otp_verified ?? false);
        });
    }

    private function verifyOrderWiseDeliveryOtp(Order $order, ?string $otp): array
    {
        if (! $this->orderRequiresDeliveryOtp($order)) {
            return ['success' => true];
        }

        if (! $otp) {
            return [
                'success' => false,
                'message' => __('labels.otp_required'),
                'data' => [],
            ];
        }

        if ((string) $order->delivery_otp !== (string) $otp) {
            return [
                'success' => false,
                'message' => __('labels.invalid_otp'),
                'data' => [],
            ];
        }

        // One valid order OTP verifies every OTP-protected delivery item on this order.
        foreach ($order->items as $item) {
            $itemRequiresOtp = $item->requires_otp ?? $item->product?->requires_otp ?? false;
            if ($itemRequiresOtp && ! ($item->otp_verified ?? false)) {
                $item->otp_verified = true;
                $item->save();
            }
        }

        $order->delivery_otp_verified = true;
        $order->save();

        return ['success' => true];
    }

    /**
     * @throws Exception
     */
    private function processStoreItems(
        Order $order,
        $storeItems,
        string $deliveryType
    ): void {
        $storeTotalPrice = 0;
        $pickupOtp = $deliveryType === 'pickup' ? mt_rand(100000, 999999) : null;

        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $storeItems->first()->store->seller_id,
            'total_price' => 0, // update later
            'delivery_type' => $deliveryType,
        ]);

        foreach ($storeItems as $cartItem) {
            $result = $this->processCartItem($order, $cartItem, $sellerOrder, $storeTotalPrice, $deliveryType, $pickupOtp);
            if ($result !== true) {
                throw new Exception($result['message']);
            }
        }

        $sellerOrder->update(['total_price' => $storeTotalPrice]);
    }

    private function processCartItem(
        Order $order,
        $cartItem,
        SellerOrder $sellerOrder,
        &$storeTotalPrice,
        string $deliveryType = 'delivery',
        ?int $pickupOtp = null
    ): true|array {
        $storeVariant = $this->getStoreVariant($cartItem);

        if ($storeVariant->stock < $cartItem->quantity) {
            return [
                'success' => false,
                'message' => ucfirst($storeVariant->productVariant->title ?? '')
                    . ' ' . __('messages.product_variant_not_available_in_store'),
                'data' => [],
            ];
        }

        [$subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent] =
            $this->calculatePricing($order, $cartItem, $storeVariant);

        $storeTotalPrice += $subtotal;

        $orderItem = $this->createOrderItem($order, $cartItem, $storeVariant, $subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent, $deliveryType, $pickupOtp);

        // fulfilment) already see the full line composition.
        $this->snapshotOrderItemAddons($orderItem, $cartItem);

        // Attach required attachments to order item using Spatie Media Library
        $this->attachRequiredOrderItemAttachments($orderItem, $cartItem);
        $this->createSellerOrderItem($sellerOrder, $cartItem, $orderItem, $storeVariant);

        return true;
    }

    private function snapshotOrderItemAddons(OrderItem $orderItem, $cartItem): void
    {
        $addons = $cartItem->relationLoaded('addons')
            ? $cartItem->getRelation('addons')
            : $cartItem->addons()->get();

        if (! $addons || $addons->isEmpty()) {
            return;
        }

        foreach ($addons as $addon) {
            /** @var CartItemAddon $addon */
            OrderItemAddon::create([
                'order_item_id' => $orderItem->id,
                'addon_group_id' => (int) $addon->addon_group_id,
                'addon_item_id' => (int) $addon->addon_item_id,
                'price' => (float) $addon->price,
                'metadata' => $addon->metadata,
            ]);
        }
    }

    /**
     * Attach uploaded files to order item when the product requires attachments.
     */
    private function attachRequiredOrderItemAttachments(OrderItem $orderItem, $cartItem): void
    {
        try {
            $product = $cartItem->product;
            if (! $product) {
                return;
            }
            $requires = (string) $product->is_attachment_required === '1' || $product->is_attachment_required === 1 || $product->is_attachment_required === true;
            if (! $requires) {
                return; // Only attach when required
            }

            $productId = (string) $product->id;
            $request = request();
            // Support both attachments and attchment keys
            $files = $request->file("attachments.$productId");
            if (! $files) {
                $files = $request->file("attchment.$productId");
            }
            if (! $files) {
                return; // Validation should have caught missing files, but be safe
            }
            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if ($file) {
                    $orderItem->addMedia($file)
                        ->toMediaCollection(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to attach order item attachments', [
                'order_item_id' => $orderItem->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getStoreVariant(
        $cartItem
    ) {
        return $cartItem->variant->storeProductVariants
            ->where('store_id', $cartItem->store->id)
            ->first();
    }

    private function calculatePricing(
        Order $order,
        $cartItem,
        $storeVariant
    ): array {
        $commission = $storeVariant->category_commission->commission ?? 0;
        $specialPrice = $storeVariant->special_price_exclude_tax;
        $specialPriceWithTax = $storeVariant->special_price;

        // Per-line addon total — matches CartService::calculateCartTotals():
        // each `cart_item_addons` row is one unit per product unit, so the
        // line-level addon amount is quantity × sum(addon.price). Adding this
        // to the subtotal keeps `order.subtotal` (= cart items_total) in sync
        // with the sum of `order_items.subtotal`.
        $addonsPerUnit = $this->sumCartAddonPrices($cartItem);
        $addonsLineTotal = (float) $cartItem->quantity * $addonsPerUnit;

        $productSubtotal = $cartItem->quantity * $specialPriceWithTax;
        $subtotal = $productSubtotal + $addonsLineTotal;

        $adminCommissionAmount = $subtotal * $commission / 100;

        $taxPercent = StoreProductVariant::scopeTaxPercentage($specialPrice, $specialPriceWithTax);

        $promoDiscount = 0;
        if (! empty($order['promo_code']) && (float) $order->subtotal > 0) {
            $promoDiscount = ((float) $subtotal / (float) $order->subtotal) * $order->promo_discount;
        }

        return [$subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent];
    }

    /**
     * Sum the per-unit price snapshot of every addon attached to a cart line.
     */
    private function sumCartAddonPrices($cartItem): float
    {
        $addons = $cartItem->relationLoaded('addons')
            ? $cartItem->getRelation('addons')
            : $cartItem->addons()->get();

        if (! $addons || $addons->isEmpty()) {
            return 0.0;
        }

        return (float) $addons->sum(fn($a) => (float) $a->price);
    }

    private function createOrderItem(
        Order $order,
        $cartItem,
        $storeVariant,
        $subtotal,
        $adminCommissionAmount,
        $promoDiscount,
        $taxPercent,
        string $deliveryType = 'delivery',
        ?int $pickupOtp = null
    ): OrderItem {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $cartItem->product_id,
            'product_variant_id' => $cartItem->product_variant_id,
            'store_id' => $cartItem->store_id,
            'title' => $cartItem->product->getTranslation('title', 'en', false),
            'variant_title' => $cartItem->variant->title,
            'gift_card_discount' => 0,
            'admin_commission_amount' => $adminCommissionAmount,
            'seller_commission_amount' => 0,
            'commission_settled' => '0',
            'return_eligible' => $cartItem->product->is_returnable == '1' ? '1' : '0',
            'returnable_days' => $cartItem->product->returnable_days ?? 0,
            'return_deadline' => $this->getReturnDeadline($cartItem->product->returnable_days ?? 0),
            // Snapshot the product cancellation policy at order time — later
            // edits to the product must not retroactively enable cancel on
            // historical orders.
            'is_cancelable' => $cartItem->product->is_cancelable == '1',
            'cancelable_till' => $cartItem->product->is_cancelable == '1'
                ? ($cartItem->product->cancelable_till ?? null)
                : null,
            'discounted_price' => 0,
            'promo_discount' => $promoDiscount,
            'discount' => 0,
            'tax_amount' => (float) $storeVariant->special_price - $storeVariant->special_price_exclude_tax,
            'tax_percent' => (float) $taxPercent,
            'sku' => $storeVariant->sku ?? 'N/A',
            'quantity' => (float) $cartItem->quantity,
            'price' => (float) $storeVariant->special_price_exclude_tax,
            'subtotal' => (float) $subtotal,
            'status' => $this->determineOrderItemStatus($order),
            'requires_otp' => (bool) $cartItem->product->requires_otp,
            // Delivery OTP is item-specific only when the order snapshot is item-wise.
            'otp' => $deliveryType === 'pickup'
                ? $pickupOtp
                : ($this->deliveryOtpModeForOrder($order) === DeliveryOtpModeEnum::ITEM_WISE() && $cartItem->product->requires_otp ? mt_rand(100000, 999999) : null),
            // Delivery OTP is item-specific only when the order snapshot is item-wise.
            'otp' => $deliveryType === 'pickup'
                ? $pickupOtp
                : ($this->deliveryOtpModeForOrder($order) === DeliveryOtpModeEnum::ITEM_WISE() && $cartItem->product->requires_otp ? mt_rand(100000, 999999) : null),
        ]);
    }

    private function createSellerOrderItem(
        SellerOrder $sellerOrder,
        $cartItem,
        OrderItem $orderItem,
        $storeVariant
    ): void {
        SellerOrderItem::create([
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $cartItem->product_id,
            'product_variant_id' => $cartItem->product_variant_id,
            'order_item_id' => $orderItem->id,
            'quantity' => (float) $cartItem->quantity,
            'price' => (float) $storeVariant->special_price_exclude_tax,
        ]);
    }

    private function determineOrderItemStatus(
        Order $order
    ): string {
        return in_array($order->payment_method, [PaymentTypeEnum::WALLET(), PaymentTypeEnum::COD()])
            ? OrderItemStatusEnum::AWAITING_STORE_RESPONSE()
            : OrderItemStatusEnum::PENDING();
    }

    /**
     * Verify stock availability for all cart items before creating order
     *
     * @param  Cart  $cart  The cart to verify stock for
     * @return array Result containing success status, message, and data
     */
    private function verifyCartItemsStock(
        Cart $cart
    ): array {
        try {
            $outOfStockItems = [];

            foreach ($cart->items as $cartItem) {
                $storeVariant = $this->getStoreVariant($cartItem);

                if (! $storeVariant) {
                    $outOfStockItems[] = [
                        'product' => $cartItem->product?->getTranslation('title', 'en', false) ?? 'Unknown Product',
                        'variant' => $cartItem->variant->title ?? 'Unknown Variant',
                        'store' => $cartItem->store->name ?? 'Unknown Store',
                        'requested_quantity' => $cartItem->quantity,
                        'available_stock' => 0,
                        'message' => 'Product variant not available in store',
                    ];

                    continue;
                }

                if ($storeVariant->stock < $cartItem->quantity) {
                    $outOfStockItems[] = [
                        'product' => $cartItem->product?->getTranslation('title', 'en', false) ?? 'Unknown Product',
                        'variant' => $cartItem->variant->title ?? 'Unknown Variant',
                        'store' => $cartItem->store->name ?? 'Unknown Store',
                        'requested_quantity' => $cartItem->quantity,
                        'available_stock' => $storeVariant->stock,
                        'message' => 'Insufficient stock available',
                    ];
                }
            }

            if (! empty($outOfStockItems)) {
                // Create a user-friendly message
                $itemNames = array_map(function ($item) {
                    return $item['product'] . ($item['variant'] ? ' (' . $item['variant'] . ')' : '');
                }, $outOfStockItems);

                $message = count($outOfStockItems) === 1
                    ? $itemNames[0] . ' is not available in sufficient quantity.'
                    : 'The following items are not available in sufficient quantity: ' . implode(', ', $itemNames);

                return [
                    'success' => false,
                    'message' => $message,
                    'data' => [
                        'out_of_stock_items' => $outOfStockItems,
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => 'All items have sufficient stock',
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error verifying cart items stock', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get order details
     *
     * @param  User  $user  The user requesting the order
     * @param  string  $orderSlug  The order slug
     * @return array Result containing success status, message, and order data
     */
    public function getOrder(
        User $user,
        string $orderSlug
    ): array {
        try {
            $order = Order::where('user_id', $user->id)
                ->where(function ($q) use ($orderSlug) {
                    $q->where('slug', $orderSlug)
                        ->orWhere('uuid', $orderSlug)
                        ->orWhere('id', $orderSlug);
                })
                ->with(['items.product', 'items.variant', 'items.store', 'sellerFeedbacks', 'sellerOrders', 'items.returns', 'promoLine'])
                ->first();

            if (! $order) {
                return [
                    'success' => false,
                    'message' => __('messages.order_not_found'),
                    'data' => [],
                ];
            }

            return [
                'success' => true,
                'message' => __('messages.order_retrieved_successfully'),
                'data' => $order,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving order: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get user's orders
     *
     * @param  User  $user  The user requesting their orders
     * @return array Result containing success status, message, and orders data
     */
    public function getUserOrders(User $user, $perPage = 15, $filters = null): array
    {
        try {
            $query = Order::where('user_id', $user->id)
                ->with([
                    'deliveryBoy.user',
                    'items.product',
                    'items.variant',
                    'items.store',
                    'items.store.seller',
                    'sellerFeedbacks',
                    'sellerOrders',
                    'items.returns',
                    'promoLine',
                ]);

            // Apply Date Range Filter
            if (!empty($filters['date_range']) || !empty($filters['range'])) {
                $rangeValue = $filters['date_range'] ?? $filters['range'];
                $fromDate = DateRangeFilterEnum::fromDate($rangeValue);
                if ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                }
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (! empty($filters['is_scheduled'])) {
                $query->where(fn($q) => $q->whereNotNull('scheduled_date')->orWhereNotNull('delivery_time_slot_id'))
                    ->where('scheduled_date', '>=', now()->toDateString());
            }

            if (! empty($filters['order_type'])) {
                if ($filters['order_type'] === 'pos') {
                    $query->where('created_by', OrderCreatedByEnum::SELLER());
                } elseif ($filters['order_type'] === 'pickup') {
                    $query->whereHas('sellerOrders', fn($q) => $q->where('delivery_type', OrderDeliveryTypeEnum::PICKUP()));
                } elseif ($filters['order_type'] === 'regular') {
                    $query->where(fn($q) => $q->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER()))
                        ->whereHas('sellerOrders', fn($q) => $q->where('delivery_type', '!=', OrderDeliveryTypeEnum::PICKUP()));
                }
            }

            $orders = $query
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return [
                'success' => true,
                'message' => __('messages.orders_retrieved_successfully'),
                'data' => $orders,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving user orders: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Validate status transition for an order item
     *
     * @param  string  $currentStatus  The current status of the order item
     * @param  string  $newStatus  The new status to set
     * @param  string  $userType  The type of user making the update ('seller' or 'delivery_boy')
     * @return array Result containing success status and message
     */
    private function validateStatusTransition(
        string $currentStatus,
        string $newStatus,
        string $userType
    ): array {
        // Check if the current status is already the same as the update status
        if ($currentStatus === $newStatus) {
            return [
                'success' => false,
                'message' => __('messages.status_already_set'),
            ];
        }

        if ($userType === 'seller') {
            // Seller-specific validations.
            // Accept and Reject are only meaningful while the item is still awaiting
            // the store's first response. Catching it here gives the seller a clean,
            // translation-keyed error before the model layer raises a generic
            // isValidStatusTransition exception (BUG-4 in the lifecycle plan).
            if ($newStatus === OrderItemStatusEnum::ACCEPTED() && $currentStatus !== OrderItemStatusEnum::AWAITING_STORE_RESPONSE()) {
                return [
                    'success' => false,
                    'message' => __('labels.order_item_can_only_be_accepted_while_awaiting_response'),
                ];
            }
            if ($newStatus === OrderItemStatusEnum::REJECTED() && $currentStatus !== OrderItemStatusEnum::AWAITING_STORE_RESPONSE()) {
                return [
                    'success' => false,
                    'message' => __('labels.order_item_can_only_be_rejected_while_awaiting_response'),
                ];
            }
            if ($newStatus === OrderItemStatusEnum::PREPARING() && $currentStatus !== OrderItemStatusEnum::ACCEPTED()) {
                return [
                    'success' => false,
                    'message' => __('labels.order_must_be_accepted_first'),
                ];
            }
        } elseif ($userType === 'delivery_boy') {
            // Delivery boy-specific validations
            if ($newStatus === OrderItemStatusEnum::COLLECTED() && $currentStatus !== OrderItemStatusEnum::PREPARING()) {
                return [
                    'success' => false,
                    'message' => __('labels.order_item_must_be_preparing_first'),
                ];
            }

            if ($newStatus === OrderItemStatusEnum::DELIVERED() && $currentStatus !== OrderItemStatusEnum::COLLECTED()) {
                return [
                    'success' => false,
                    'message' => __('labels.order_item_must_be_collected_first'),
                ];
            }
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    /**
     * Map input status to OrderItemStatusEnum
     *
     * @param  string  $status  The input status
     * @param  string  $userType  The type of user making the update ('seller' or 'delivery_boy')
     * @return string|null The mapped OrderItemStatusEnum value or null if invalid
     */
    private function mapStatusToEnum(
        string $status,
        string $userType
    ): ?string {
        return match ($userType) {
            'seller' => match ($status) {
                'accept' => OrderItemStatusEnum::ACCEPTED(),
                'reject' => OrderItemStatusEnum::REJECTED(),
                'preparing' => OrderItemStatusEnum::PREPARING(),
                default => null,
            },
            'delivery_boy' => match ($status) {
                'collected' => OrderItemStatusEnum::COLLECTED(),
                'delivered' => OrderItemStatusEnum::DELIVERED(),
                default => null,
            },
            default => null,
        };
    }

    /**
     * Update order status and related entities
     *
     * @param  OrderItem  $orderItem  The order item to update
     * @param  string  $newStatus  The new status to set
     * @param  string  $oldStatus  The old status
     * @param  string  $userType  The type of user making the update ('seller' or 'delivery_boy')
     * @param  int|null  $userId  The ID of the user making the update (seller ID or delivery boy ID)
     * @return array Result containing success status, message, and data
     */
    private function updateOrderStatusAndRelatedEntities(
        OrderItem $orderItem,
        string $newStatus,
        string $oldStatus,
        string $userType,
        ?int $userId = null
    ): array {
        try {
            if ($userType === 'seller' && $newStatus === OrderItemStatusEnum::ACCEPTED()) {
                $expiredAcceptance = $this->rejectSellerAcceptAfterAcceptanceDeadline($orderItem);
                if ($expiredAcceptance) {
                    return $expiredAcceptance;
                }
            }

            DB::beginTransaction();

            $returnDeadline = null;
            if ($orderItem->return_eligible && $orderItem->returnable_days > 0) {
                $returnDeadline = $this->getReturnDeadline($orderItem->returnable_days);
            }
            $orderItem->update([
                'status' => $newStatus,
                'return_deadline' => $returnDeadline,
            ]);

            // Create seller settlement credit on item delivered
            $isDeliveryBoyDelivery = ($userType === 'delivery_boy' && $newStatus === OrderItemStatusEnum::DELIVERED());
            $sellerOrder = $orderItem->sellerOrderItem?->sellerOrder ?? SellerOrder::where([
                'order_id' => $orderItem->order_id,
                'seller_id' => $orderItem->store->seller_id,
            ])->first();
            $isPickup = $sellerOrder && ($sellerOrder->delivery_type?->value ?? $sellerOrder->delivery_type) === 'pickup';
            $isOnlinePayment = $orderItem->order && ! in_array($orderItem->order->payment_method, [
                PaymentTypeEnum::COD(),
                PaymentTypeEnum::POS_CASH(),
                PaymentTypeEnum::POS_UPI(),
                PaymentTypeEnum::POS_CUSTOM(),
            ]);
            $isPickupOnlineDelivery = ($userType === 'seller' && $newStatus === OrderItemStatusEnum::DELIVERED() && $isPickup && $isOnlinePayment);
            $isPickupCodDelivery = ($userType === 'seller' && $newStatus === OrderItemStatusEnum::DELIVERED() && $isPickup && ! $isOnlinePayment);

            if ($isDeliveryBoyDelivery || $isPickupOnlineDelivery) {
                try {
                    // avoid duplicate credits for the same item
                    $exists = SellerStatement::where('order_item_id', $orderItem->id)
                        ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
                        ->where(function ($q) {
                            $q->where('reference_type', 'order_item_delivery')
                                ->orWhereNull('reference_type');
                        })
                        ->exists();
                    if (! $exists) {
                        $this->sellerStatementService->addEntry(data: [
                            'seller_id' => $orderItem->store->seller_id,
                            'entry_type' => SellerSettlementTypeEnum::CREDIT(),
                            'amount' => ($orderItem->subtotal - $orderItem->admin_commission_amount),
                            'currency_code' => app(CurrencyWriteGuard::class)->orderCurrency($orderItem->order),
                            'order_id' => $orderItem->order_id,
                            'order_item_id' => $orderItem->id,
                            'reference_type' => 'order_item_delivery',
                            'reference_id' => $orderItem->id,
                            'description' => 'Seller earning for delivered Order Item #' . $orderItem->id,
                            'meta' => [
                                'product_id' => $orderItem->product_id,
                                'quantity' => $orderItem->quantity,
                            ],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to create seller settlement credit on delivery', [
                        'order_item_id' => $orderItem->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($isPickupCodDelivery) {
                try {
                    // avoid duplicate debits for the same item
                    $exists = SellerStatement::where('order_item_id', $orderItem->id)
                        ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
                        ->where('reference_type', 'order_item_commission')
                        ->exists();
                    if (! $exists && $orderItem->admin_commission_amount > 0) {
                        $this->sellerStatementService->addEntry(data: [
                            'seller_id' => $orderItem->store->seller_id,
                            'entry_type' => SellerSettlementTypeEnum::DEBIT(),
                            'amount' => $orderItem->admin_commission_amount,
                            'currency_code' => app(CurrencyWriteGuard::class)->orderCurrency($orderItem->order),
                            'order_id' => $orderItem->order_id,
                            'order_item_id' => $orderItem->id,
                            'reference_type' => 'order_item_commission',
                            'reference_id' => $orderItem->id,
                            'description' => 'Commission debit for Self-Pickup Cash Order Item #' . $orderItem->id,
                            'meta' => [
                                'product_id' => $orderItem->product_id,
                                'quantity' => $orderItem->quantity,
                            ],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to create seller settlement debit on self-pickup COD delivery', [
                        'order_item_id' => $orderItem->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $orderItemsStatuses = $this->getOrderItemsStatuses($orderItem->order_id);

            $result = ($userType === 'seller')
                ? $this->handleSellerFlow($orderItem, $newStatus, $oldStatus, $userId, $orderItemsStatuses)
                : $this->handleDeliveryBoyFlow(orderItem: $orderItem, orderItemsStatuses: $orderItemsStatuses, userId: $userId);

            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $oldStatus,
                newStatus: $newStatus,
                source: $userType === 'seller' ? 'seller' : 'rider',
            ));

            DB::commit();

            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating order status', [
                'order_item_id' => $orderItem->id,
                'new_status' => $newStatus,
                'user_type' => $userType,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.order_status_update_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function getOrderItemsStatuses(
        int $orderId
    ): array {
        return OrderItem::where('order_id', $orderId)->pluck('status')->toArray();
    }

    private function handleSellerFlow(
        OrderItem $orderItem,
        string $newStatus,
        string $oldStatus,
        ?int $userId,
        array $orderItemsStatuses
    ): array {
        // Recompute order status whenever the order is still inside the pre-pickup seller
        // flow. The previous guard only covered AWAITING/PARTIALLY/PENDING — under race
        // conditions (e.g. seller accept + customer cancel landing close together) the
        // order could end up in ACCEPTED_BY_SELLER or READY_FOR_PICKUP and never get
        // re-derived, leaving its status stale (BUG-3).
        if (
            in_array($orderItem->order->status, [
                OrderStatusEnum::PENDING(),
                OrderStatusEnum::AWAITING_STORE_RESPONSE(),
                OrderStatusEnum::PARTIALLY_ACCEPTED(),
                OrderStatusEnum::PARTIALLY_REJECTED(),
                OrderStatusEnum::ACCEPTED_BY_SELLER(),
                OrderStatusEnum::READY_FOR_PICKUP(),
            ])
        ) {
            $orderStatus = $this->determineSellerOrderStatus($orderItemsStatuses);
            // Admin may have pre-assigned a rider while the order was still in
            // a pre-pickup state. When the seller flow finally lands the order
            // at READY_FOR_PICKUP, promote it straight to ASSIGNED so the
            // pre-assignment carries through and the rider broadcast is skipped.
            if ($orderStatus === OrderStatusEnum::READY_FOR_PICKUP() && $orderItem->order->delivery_boy_id) {
                $orderStatus = OrderStatusEnum::ASSIGNED();
            }
            if ($orderStatus !== $orderItem->order->status) {
                $orderItem->order->update(['status' => $orderStatus]);
            }
            if (in_array($orderItem->order->fresh()->status, [OrderStatusEnum::CANCELLED(), OrderStatusEnum::REJECTED_BY_SELLER()])) {
                $this->refundRiderTipToCustomerWallet($orderItem->order);
            }
        }

        $sellerOrderItem = SellerOrderItem::where('order_item_id', $orderItem->id)
            ->with('sellerOrder')
            ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $userId))
            ->first();

        if ($sellerOrderItem) {
            if ($newStatus === OrderItemStatusEnum::REJECTED()) {
                $this->recalculateOrderAmounts($orderItem->order->id, $orderItem);
                $this->processOrderItemRefund(orderItem: $orderItem, type: OrderItemStatusEnum::REJECTED());
                if (in_array($orderItem->order->fresh()->status, [OrderStatusEnum::CANCELLED(), OrderStatusEnum::REJECTED_BY_SELLER()])) {
                    $this->refundRiderTipToCustomerWallet($orderItem->order);
                }
            }
        }

        return [
            'success' => true,
            'message' => __('messages.order_status_updated_successfully'),
            'data' => $sellerOrderItem,
        ];
    }

    private function handleDeliveryBoyFlow(
        OrderItem $orderItem,
        array $orderItemsStatuses,
        ?int $userId
    ): array {
        $orderStatus = $this->determineDeliveryOrderStatus($orderItemsStatuses);

        if ($orderStatus !== $orderItem->order->status) {
            $this->updateDeliveryOrderAndAssignments($orderItem, $orderStatus, $userId);
        }

        return [
            'success' => true,
            'message' => __('messages.order_status_updated_successfully'),
            'data' => [
                'order_item' => $orderItem->fresh(),
                'order' => $orderItem->order->fresh(),
            ],
        ];
    }

    private function updateDeliveryOrderAndAssignments(
        OrderItem $orderItem,
        string $orderStatus,
        ?int $userId
    ): void {
        $orderData = ['status' => $orderStatus];

        if (! $userId || ! in_array($orderStatus, [OrderStatusEnum::OUT_FOR_DELIVERY(), OrderStatusEnum::DELIVERED()])) {
            return;
        }

        $assignmentStatus = $orderStatus === OrderStatusEnum::OUT_FOR_DELIVERY()
            ? DeliveryBoyAssignmentStatusEnum::IN_PROGRESS()
            : DeliveryBoyAssignmentStatusEnum::COMPLETED();

        if ($orderStatus === OrderStatusEnum::DELIVERED()) {
            if ($orderItem->order->payment_method === PaymentTypeEnum::COD()) {
                $orderData['payment_status'] = PaymentStatusEnum::COMPLETED();
                event(new OrderDelivered($orderItem->order));
                $this->handleCashCollection($orderItem, $userId);
            }
            $this->creditRiderTipOnDelivery($orderItem->order);
        }

        $orderItem->order->update($orderData);

        DeliveryBoyAssignment::where('order_id', $orderItem->order_id)
            ->where('delivery_boy_id', $userId)
            ->whereNotIn('status', [
                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                DeliveryBoyAssignmentStatusEnum::DROPPED(),
                DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
            ])
            ->update(['status' => $assignmentStatus]);
    }

    private function handleCashCollection(
        OrderItem $orderItem,
        int $userId
    ): void {
        if ($orderItem->order->payment_method !== PaymentTypeEnum::COD()) {
            return;
        }

        // If COD order was paid online via doorstep QR/link, skip physical cash collection against delivery boy
        if ($orderItem->order->is_cod_paid_online || (string) $orderItem->order->payment_status === (string) PaymentStatusEnum::COMPLETED()) {
            return;
        }

        $assignmentId = DeliveryBoyAssignment::where('order_id', $orderItem->order_id)
            ->where('delivery_boy_id', $userId)
            ->whereNotIn('status', [
                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                DeliveryBoyAssignmentStatusEnum::DROPPED(),
                DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
            ])
            ->value('id');
        if (! $assignmentId) {
            return;
        }

        DeliveryBoyAssignment::where('id', $assignmentId)->update([
            'cod_cash_collected' => $orderItem->order->total_payable,
            'cod_submission_status' => 'pending',
        ]);

        DeliveryBoyCashTransaction::create([
            'delivery_boy_assignment_id' => $assignmentId,
            'order_id' => $orderItem->order_id,
            'delivery_boy_id' => $userId,
            'amount' => $orderItem->order->total_payable,
            'transaction_type' => 'collected',
            'transaction_date' => now(),
        ]);

        // [CHANGE] Check cash collection limit and notify rider if reached
        $deliveryBoy = DeliveryBoy::with('user')->find($userId);
        if ($deliveryBoy && $deliveryBoy->isCashLimitReached() && $deliveryBoy->user) {
            $deliveryBoy->user->notify(new CashCollectionLimitReachedNotification($deliveryBoy));
        }
    }

    /**
     * Update the status of an order item
     *
     * @param  int  $orderItemId  The ID of the order item to update
     * @param  string  $status  The new status to set ('accept', 'reject', 'preparing')
     * @param  int  $sellerId  The ID of the seller making the update
     * @return array Result containing success status, message, and order data
     */
    public function updateOrderStatusBySeller(
        int $orderItemId,
        string $status,
        int $sellerId
    ): array {
        try {
            // Validate status parameter
            if (! in_array($status, ['accept', 'reject', 'preparing'])) {
                return [
                    'success' => false,
                    'message' => __('labels.invalid_status'),
                    'data' => [],
                ];
            }

            $sellerOrderItem = SellerOrderItem::where('order_item_id', $orderItemId)
                ->with(['sellerOrder.order.deliveryTimeSlot', 'orderItem', 'orderItem.product', 'orderItem.store.seller.user'])
                ->whereHas('sellerOrder', function ($q) use ($sellerId) {
                    $q->where('seller_id', $sellerId);
                })
                ->first();

            if (! $sellerOrderItem) {
                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_found'),
                    'data' => [],
                ];
            }

            $orderItem = $sellerOrderItem->orderItem;
            if ($this->isPendingCustomerReschedule($sellerOrderItem->sellerOrder->order ?? null)) {
                return [
                    'success' => false,
                    'message' => __('messages.scheduled_order_pending_reschedule'),
                    'data' => [],
                ];
            }

            $currentStatus = $orderItem->status;
            if ($currentStatus === OrderItemStatusEnum::PENDING()) {
                return [
                    'success' => false,
                    'message' => __('labels.order_payment_pending_cannot_update_status'),
                    'data' => [],
                ];
            }
            if (in_array($currentStatus, [OrderItemStatusEnum::FAILED(), OrderItemStatusEnum::CANCELLED(), OrderItemStatusEnum::COLLECTED(), OrderItemStatusEnum::DELIVERED()])) {
                return [
                    'success' => false,
                    'message' => __('labels.cannot_update_status_because_status_is_already', ['status' => $currentStatus]),
                    'data' => [],
                ];
            }

            $updateStatus = $this->mapStatusToEnum($status, 'seller');

            if (! $updateStatus) {
                return [
                    'success' => false,
                    'message' => __('labels.invalid_status'),
                    'data' => [],
                ];
            }

            if ($updateStatus === OrderItemStatusEnum::ACCEPTED()) {
                $order = $sellerOrderItem->sellerOrder->order ?? $orderItem->order;
                $expiredAcceptance = $this->rejectSellerAcceptAfterAcceptanceDeadline($orderItem, $order);
                if ($expiredAcceptance) {
                    return $expiredAcceptance;
                }
            }

            if ($status === 'preparing') {
                $order = $sellerOrderItem->sellerOrder->order ?? null;
                if ($order && ($order->scheduled_date || $order->delivery_time_slot_id)) {
                    $store = $orderItem->store ?? null;
                    $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');
                    $prepStartTime = $this->resolveScheduledPreparationStartTime($order, $store);

                    if ($prepStartTime && Carbon::now($storeTz)->lt($prepStartTime)) {
                        return [
                            'success' => false,
                            'message' => __('messages.scheduled_delivery_prep_too_early', ['time' => $prepStartTime->format('d M, h:i A')]),
                            'data' => [],
                        ];
                    }
                }
            }

            // Validate the status transition
            $validationResult = $this->validateStatusTransition($currentStatus, $updateStatus, 'seller');
            if (! $validationResult['success']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                    'data' => [],
                ];
            }

            // Update the order status and related entities
            return $this->updateOrderStatusAndRelatedEntities(
                $orderItem,
                $updateStatus,
                $currentStatus,
                'seller',
                $sellerId
            );
        } catch (Exception $e) {
            Log::error('Error in updateOrderStatusBySeller', [
                'order_item_id' => $orderItemId,
                'status' => $status,
                'seller_id' => $sellerId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.order_status_update_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function getReturnDeadline(int $days, ?string $fromDate = null): string
    {
        $baseDate = $fromDate ? Carbon::parse($fromDate) : Carbon::now();

        return $baseDate->addDays($days)->format('Y-m-d');
    }

    private function isPendingCustomerReschedule(?Order $order): bool
    {
        return $order?->reschedule_status === 'needs_reschedule';
    }

    /**
     * Determine the overall order status based on the statuses of all order items
     *
     * @param  array  $orderItemsStatuses  Array of order item statuses
     * @return string The determined order status
     */

    /**
     * Recalculate order amounts after an item is rejected
     *
     * @param  int  $orderId  The order ID
     * @param  OrderItem|null  $rejectedItem  The rejected order item (optional)
     * @return array Result containing success status, message, and data
     */
    public function recalculateOrderAmounts(
        int $orderId,
        $rejectedItem = null
    ): array {
        try {
            DB::beginTransaction();
            // Get the order
            $order = Order::findOrFail($orderId);

            // Get all active items (excluding rejected, cancelled, and the in-flight
            // return path) for this order. RETURNING_TO_STORE + DELIVERY_FAILED items
            // have physically left the customer's basket — rider is bringing them
            // back to the seller and they should not count toward subtotal / total
            // payable while in that state. They finalise to CANCELLED on seller
            // confirm-receipt. (DELIVERY_FAILED is normally transient — auto-flips
            // to RETURNING_TO_STORE inside the same flow — but we exclude it here
            // as a safety net so any consumer that reads mid-transition sees the
            // right number.)
            $query = $order->items()->whereNotIn('status', [
                OrderItemStatusEnum::REJECTED(),
                OrderItemStatusEnum::CANCELLED(),
                OrderItemStatusEnum::RETURNING_TO_STORE(),
                OrderItemStatusEnum::DELIVERY_FAILED(),
            ]);
            if ($rejectedItem) {
                $query->where('id', '!=', $rejectedItem->id);
            }
            $newPromoDiscount = $order->promo_discount;
            if (! empty($order->promo_code)) {
                $orderPromoLine = OrderPromoLine::where('order_id', $orderId)->get()->first();
                $newPromoDiscount = $order->promo_discount - $rejectedItem->promo_discount;
                $orderPromoLine->update(['discount_amount' => $newPromoDiscount]);
            }

            $activeItems = $query->get();

            // Calculate new subtotal (sum of all non-rejected items)
            $newSubtotal = $activeItems->sum('subtotal');

            $handlingCharges = (float) ($order->handling_charges ?? 0);
            $perStoreDropOffFee = (float) ($order->per_store_drop_off_fee ?? 0);
            $deliveryCharge = (float) ($order->delivery_charge ?? 0);

            $newFinalTotal = $newSubtotal
                + $deliveryCharge
                + $handlingCharges
                + $perStoreDropOffFee
                - (($newPromoDiscount ?? 0) + ($order->gift_card_discount ?? 0));

            // Calculate new total payable (final total - wallet balance)
            $newTotalPayable = max(0, $newFinalTotal - $order->wallet_balance);

            // Update the order with new amounts
            $order->update([
                'subtotal' => $newSubtotal,
                'final_total' => $newFinalTotal,
                'total_payable' => $newTotalPayable,
                'promo_discount' => $newPromoDiscount,
            ]);

            // Update seller orders if they exist
            if ($rejectedItem) {
                $sellerOrder = SellerOrder::where('order_id', $orderId)
                    ->where('seller_id', $rejectedItem->store->seller_id)
                    ->first();

                if ($sellerOrder) {
                    // Calculate new seller order total (sum of all non-rejected items for this seller)
                    $sellerItems = $activeItems->filter(function ($item) use ($sellerOrder) {
                        return $item->store->seller_id == $sellerOrder->seller_id;
                    });

                    $newSellerTotal = $sellerItems->sum('subtotal');

                    // Update seller order total
                    $sellerOrder->update([
                        'total_price' => $newSellerTotal,
                    ]);
                }
            } else {
                // Update all seller orders
                foreach ($order->sellerOrders as $sellerOrder) {
                    $sellerItems = $activeItems->filter(function ($item) use ($sellerOrder) {
                        return $item->store->seller_id == $sellerOrder->seller_id;
                    });

                    $newSellerTotal = $sellerItems->sum('subtotal');

                    // Update seller order total
                    $sellerOrder->update([
                        'total_price' => $newSellerTotal,
                    ]);
                }
            }
            DB::commit();
            Log::info('Order amounts recalculated', [
                'order_id' => $orderId,
                'rejected_item_id' => $rejectedItem ? $rejectedItem->id : null,
                'new_subtotal' => $newSubtotal,
                'new_final_total' => $newFinalTotal,
                'new_total_payable' => $newTotalPayable,
            ]);

            return [
                'success' => true,
                'message' => 'Order amounts recalculated successfully',
                'data' => [
                    'order_id' => $orderId,
                    'new_subtotal' => $newSubtotal,
                    'new_final_total' => $newFinalTotal,
                    'new_total_payable' => $newTotalPayable,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error recalculating order amounts', [
                'order_id' => $orderId,
                'rejected_item_id' => $rejectedItem ? $rejectedItem->id : null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error recalculating order amounts: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Cancel an order item
     *
     * @param  User  $user  The user requesting the cancellation
     * @param  int  $orderItemId  The order item ID to cancel
     * @return array Result containing success status, message, and data
     */
    public function cancelOrderItem(
        User $user,
        int $orderItemId
    ): array {
        try {
            DB::beginTransaction();

            // Get the order item with relationships
            $orderItem = OrderItem::with(['order', 'product'])
                ->where('id', $orderItemId)
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (! $orderItem) {
                return [
                    'success' => false,
                    'message' => __('messages.order_item_not_found'),
                    'data' => [],
                ];
            }

            // Check cancelability using the snapshot captured at order time so
            // changes to the product's cancel policy after the order was placed
            // cannot retroactively allow/deny cancellation.
            if (! $orderItem->is_cancelable) {
                return [
                    'success' => false,
                    'message' => __('messages.product_not_cancelable'),
                    'data' => [],
                ];
            }

            // Status-ladder check uses the snapshot's cancelable_till
            $statusHierarchy = OrderItem::getStatusHierarchy();
            $currentStatusLevel = $statusHierarchy[$orderItem->status] ?? 0;
            $cancelableTillLevel = $statusHierarchy[$orderItem->cancelable_till] ?? 0;

            if ($currentStatusLevel > $cancelableTillLevel || $currentStatusLevel === -1) {
                return [
                    'success' => false,
                    'message' => __('messages.order_item_cannot_be_cancelled_at_current_status'),
                    'data' => [],
                ];
            }

            // Check if order item is already cancelled or in a terminal state
            if (
                in_array($orderItem->status, [
                    OrderItemStatusEnum::CANCELLED(),
                    OrderItemStatusEnum::REJECTED(),
                    OrderItemStatusEnum::DELIVERED(),
                    OrderItemStatusEnum::RETURNED(),
                    OrderItemStatusEnum::REFUNDED(),
                    OrderItemStatusEnum::FAILED(),
                ])
            ) {
                return [
                    'success' => false,
                    'message' => __('messages.order_item_already_in_terminal_state'),
                    'data' => [],
                ];
            }

            $oldStatus = $orderItem->status;

            // Update order item status to cancelled
            $orderItem->update(['status' => OrderItemStatusEnum::CANCELLED()]);

            // Fire status updated event so listeners (e.g., stock restock) can react
            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $oldStatus,
                newStatus: OrderItemStatusEnum::CANCELLED(),
                source: 'customer',
            ));

            // Process refund if payment was made in advance
            $refundResult = $this->processOrderItemRefund($orderItem);
            if (! $refundResult['success']) {
                DB::rollBack();

                return $refundResult;
            }

            // Recalculate order pricing
            $recalculationResult = $this->recalculateOrderAmounts($orderItem->order_id, $orderItem);
            if (! $recalculationResult['success']) {
                DB::rollBack();

                return $recalculationResult;
            }

            // Update overall order status if needed
            $this->updateOrderStatusAfterCancellation($orderItem->order);

            DB::commit();

            Log::info('Order item cancelled successfully', [
                'order_item_id' => $orderItem->id,
                'order_id' => $orderItem->order_id,
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'refund_amount' => $refundResult['data']['refund_amount'] ?? 0,
            ]);

            return [
                'success' => true,
                'message' => __('messages.order_item_cancelled_successfully'),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'refund_details' => $refundResult['data'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling order item', [
                'order_item_id' => $orderItemId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Cancel an order item the seller had already accepted (or started
     * preparing). Pre-collect only — once a rider has collected the item, the
     * rider/admin flow owns it.
     *
     * Side effects: refund (when applicable), restock (via the listener),
     * order-amount recalculation, order-status recompute, rider unassignment
     * if no live items remain, increment seller.post_accept_cancel_count.
     *
     * @param  int  $orderItemId  Item id (matches OrderItem.id, not SellerOrderItem.id)
     * @param  int  $sellerId  Seller making the cancellation
     * @param  string  $reason  Free-text reason provided by the seller
     * @return array{success: bool, message: string, data: array}
     */
    public function cancelOrderItemBySeller(int $orderItemId, int $sellerId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $sellerOrderItem = SellerOrderItem::where('order_item_id', $orderItemId)
                ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $sellerId))
                ->with(['sellerOrder.seller', 'orderItem.order'])
                ->first();

            if (! $sellerOrderItem || ! $sellerOrderItem->orderItem) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_found'),
                    'data' => [],
                ];
            }

            $orderItem = $sellerOrderItem->orderItem;
            if ($this->isPendingCustomerReschedule($orderItem->order)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('messages.scheduled_order_pending_reschedule'),
                    'data' => [],
                ];
            }

            $currentStatus = $orderItem->status;

            // Pre-collect window only. The doc explicitly limits this flow to
            // ACCEPTED + PREPARING; once a rider has the item the rider/admin
            // return-to-store flow takes over.
            if (
                ! in_array($currentStatus, [
                    OrderItemStatusEnum::ACCEPTED(),
                    OrderItemStatusEnum::PREPARING(),
                ], true)
            ) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_item_cannot_be_cancelled_at_current_status'),
                    'data' => ['current_status' => $currentStatus],
                ];
            }

            $orderItem->update([
                'status' => OrderItemStatusEnum::CANCELLED(),
                'cancellation_reason' => $reason,
            ]);

            // Restock + addon stock restore land via the listener.
            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $currentStatus,
                newStatus: OrderItemStatusEnum::CANCELLED(),
                source: 'seller',
            ));

            // Wallet refund (no-op on COD-pre-delivery thanks to the BUG-1 fix).
            $refundResult = $this->processOrderItemRefund($orderItem, type: 'cancel');
            if (! $refundResult['success']) {
                DB::rollBack();

                return $refundResult;
            }

            // Recalculate the parent order's totals to reflect the removed item.
            $recalc = $this->recalculateOrderAmounts($orderItem->order_id, $orderItem);
            if (! $recalc['success']) {
                DB::rollBack();

                return $recalc;
            }

            // If a rider is on the order, unassign them only when nothing is
            // left for them to deliver. Otherwise they keep going with what's
            // still active.
            $order = $orderItem->order->fresh();
            if ($order->delivery_boy_id) {
                $remainingLive = OrderItem::where('order_id', $order->id)
                    ->whereNotIn('status', [
                        OrderItemStatusEnum::CANCELLED(),
                        OrderItemStatusEnum::REJECTED(),
                        OrderItemStatusEnum::FAILED(),
                        OrderItemStatusEnum::DELIVERY_FAILED(),
                        OrderItemStatusEnum::RETURNING_TO_STORE(),
                    ])
                    ->exists();

                if (! $remainingLive) {
                    DeliveryBoyAssignment::where('order_id', $order->id)
                        ->where('delivery_boy_id', $order->delivery_boy_id)
                        ->whereNotIn('status', [
                            DeliveryBoyAssignmentStatusEnum::COMPLETED(),
                            DeliveryBoyAssignmentStatusEnum::CANCELED(),
                        ])
                        ->update(['status' => DeliveryBoyAssignmentStatusEnum::CANCELED()]);

                    $order->update(['delivery_boy_id' => null]);
                }
            }

            // Recompute the order-level status from the refreshed item set.
            $this->updateOrderStatusAfterCancellation($order);

            // Track for the admin reliability dashboard. increment() bypasses
            // mass-assignment so we don't need post_accept_cancel_count in $fillable.
            $sellerOrderItem->sellerOrder->seller?->increment('post_accept_cancel_count');

            DB::commit();

            Log::info('Seller cancelled order item post-accept', [
                'order_item_id' => $orderItem->id,
                'seller_id' => $sellerId,
                'old_status' => $currentStatus,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => __('labels.order_item_cancelled_successfully'),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'refund_details' => $refundResult['data'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Seller post-accept cancellation failed', [
                'order_item_id' => $orderItemId,
                'seller_id' => $sellerId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Seller confirms physical receipt of items returning from a rider drop or
     * a failed delivery attempt. Item must currently be in RETURNING_TO_STORE.
     * Transitions the item to CANCELLED — refund + restock fire via the
     * listener and the parent order is recomputed.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function confirmStoreReturn(int $orderItemId, int $sellerId): array
    {
        try {
            DB::beginTransaction();

            $sellerOrderItem = SellerOrderItem::where('order_item_id', $orderItemId)
                ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $sellerId))
                ->with(['orderItem.order'])
                ->first();

            if (! $sellerOrderItem || ! $sellerOrderItem->orderItem) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_found'),
                    'data' => [],
                ];
            }

            $orderItem = $sellerOrderItem->orderItem;
            if ($this->isPendingCustomerReschedule($orderItem->order)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('messages.scheduled_order_pending_reschedule'),
                    'data' => [],
                ];
            }

            if ($orderItem->status !== OrderItemStatusEnum::RETURNING_TO_STORE()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_returning_to_store'),
                    'data' => ['current_status' => $orderItem->status],
                ];
            }

            // Carry forward whatever upstream reason exists; otherwise we tag it
            // explicitly so the row's audit trail isn't silent. The listener
            // chain handles restock + refund.
            $derivedReason = $orderItem->delivery_fail_reason
                ? __('labels.cancellation_reason_delivery_failed')
                : ($orderItem->cancellation_reason ?? __('labels.cancellation_reason_rider_dropped'));

            $orderItem->update([
                'status' => OrderItemStatusEnum::CANCELLED(),
                'cancellation_reason' => $derivedReason,
            ]);

            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: OrderItemStatusEnum::RETURNING_TO_STORE(),
                newStatus: OrderItemStatusEnum::CANCELLED(),
                source: 'seller',
            ));

            // The customer was already refunded when the rider failure / drop
            // fired (Phase 1C will own that). Defensive call — processOrderItemRefund
            // is idempotent for COD-no-money cases and safe for prepaid because
            // the wallet service has its own dedup on transaction_reference.
            $refundResult = $this->processOrderItemRefund($orderItem, type: 'cancel');
            if (! $refundResult['success']) {
                DB::rollBack();

                return $refundResult;
            }

            $recalc = $this->recalculateOrderAmounts($orderItem->order_id, $orderItem);
            if (! $recalc['success']) {
                DB::rollBack();

                return $recalc;
            }

            $this->updateOrderStatusAfterCancellation($orderItem->order->fresh());

            // If this confirm-return finalised an order that admin force-cancelled,
            // the extras refund (delivery + handling) settles up here. Fully
            // idempotent — won't double-refund or create duplicate audit rows.
            $this->refundOrderExtrasIfAdminInitiated($orderItem->order->fresh());

            DB::commit();

            Log::info('Seller confirmed store return', [
                'order_item_id' => $orderItem->id,
                'seller_id' => $sellerId,
                'reason' => $derivedReason,
            ]);

            return [
                'success' => true,
                'message' => __('labels.store_return_confirmed_successfully'),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'refund_details' => $refundResult['data'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Seller confirm-return failed', [
                'order_item_id' => $orderItemId,
                'seller_id' => $sellerId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    // =====================================================================
    // Phase 3 — admin override service methods.
    //
    // Each writes an order_audit_logs row capturing the actor + reason.
    // Notifications go out via the existing OrderStatusUpdated event with
    // source='admin'.
    // =====================================================================

    /**
     * Force any item status transition. Bypasses the normal isValidStatusTransition
     * guard. Use sparingly — admin escape hatch for stuck orders.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function updateOrderStatusByAdmin(int $orderItemId, string $newStatus, int $adminId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $orderItem = OrderItem::with(['order'])->find($orderItemId);
            if (! $orderItem) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.order_item_not_found'), 'data' => []];
            }

            $targetStatus = match ($newStatus) {
                'reject', 'rejected' => OrderItemStatusEnum::REJECTED(),
                'cancel', 'cancelled' => OrderItemStatusEnum::CANCELLED(),
                default => $newStatus,
            };

            $oldStatus = $orderItem->status;
            $orderItem->status = $targetStatus;
            if (in_array($targetStatus, [OrderItemStatusEnum::REJECTED(), OrderItemStatusEnum::CANCELLED()], true)) {
                $orderItem->cancellation_reason = $reason;
            }
            // saveQuietly() bypasses the model's saving hook (which rejects invalid
            // transitions). Admin force-status is the explicit escape hatch.
            $orderItem->saveQuietly();

            if (in_array($targetStatus, [OrderItemStatusEnum::REJECTED(), OrderItemStatusEnum::CANCELLED()])) {
                $this->recalculateOrderAmounts($orderItem->order_id, $orderItem);
                $this->processOrderItemRefund($orderItem, type: $targetStatus === OrderItemStatusEnum::REJECTED() ? OrderItemStatusEnum::REJECTED() : 'cancel');
                $this->updateOrderStatusAfterCancellation($orderItem->order->fresh());

                $freshOrder = $orderItem->order->fresh();
                if ($freshOrder && in_array($freshOrder->status, [OrderStatusEnum::CANCELLED(), OrderStatusEnum::REJECTED_BY_SELLER()])) {
                    $this->refundRiderTipToCustomerWallet($freshOrder);
                }
            }

            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $oldStatus,
                newStatus: $targetStatus,
                source: 'admin',
            ));

            $this->writeAuditLog(
                orderId: $orderItem->order_id,
                orderItemId: $orderItem->id,
                adminId: $adminId,
                action: 'force_status',
                oldValue: ['status' => $oldStatus],
                newValue: ['status' => $newStatus],
                reason: $reason,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.order_status_force_updated'),
                'data' => ['order_item' => $orderItem->fresh()],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin force-status failed', [
                'order_item_id' => $orderItemId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Force-cancel any item from any state. Two paths:
     *
     *  - Pre-collect items go directly to CANCELLED (refund + restock fire
     *    immediately via the listener).
     *  - COLLECTED items route through RETURNING_TO_STORE — the rider must
     *    physically return the goods to the seller. The seller's existing
     *    confirmStoreReturn flow finalises the item to CANCELLED later;
     *    refund + restock fire then.
     *
     * Rider compensation is NO LONGER decided at force-cancel time. The
     * assignment is left at its current earnings amount; when the order
     * ultimately finalises to CANCELLED, closeOutRiderAssignmentForCancelledOrder
     * flips it to CANCELLED_BY_ADMIN + payment_status PENDING and admin
     * reviews via the Settle Earnings panel.
     */
    public function cancelOrderItemByAdmin(int $orderItemId, int $adminId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $orderItem = OrderItem::with(['order'])->find($orderItemId);
            if (! $orderItem) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.order_item_not_found'), 'data' => []];
            }

            $oldStatus = $orderItem->status;
            if ($oldStatus === OrderItemStatusEnum::CANCELLED()) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.order_item_already_cancelled'), 'data' => []];
            }

            // Once an item is delivered it has left the system — admin can't
            // unwind it. Goodwill credits should go through Force Refund.
            if (
                in_array($oldStatus, [
                    OrderItemStatusEnum::DELIVERED(),
                    OrderItemStatusEnum::REFUNDED(),
                ], true)
            ) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.cancel_blocked_item_delivered'), 'data' => []];
            }

            $isCollected = $oldStatus === OrderItemStatusEnum::COLLECTED();
            $refundResult = ['success' => true, 'data' => []];

            if ($isCollected) {
                $orderItem->cancellation_reason = $reason;
                $orderItem->saveQuietly();
                // returnItemToStore() now owns the recalculateOrderAmounts call,
                // so admin force-cancel, rider delivery-failed and rider drop
                // all deduct totals through the same path.
                $this->returnItemToStore($orderItem);

                $newStatus = OrderItemStatusEnum::RETURNING_TO_STORE();
                $messageKey = 'labels.force_cancel_routed_to_return';
            } else {
                // Pre-collect — direct cancel, immediate refund.
                $orderItem->status = OrderItemStatusEnum::CANCELLED();
                $orderItem->cancellation_reason = $reason;
                $orderItem->saveQuietly();

                event(new OrderStatusUpdated(
                    orderItem: $orderItem,
                    oldStatus: $oldStatus,
                    newStatus: OrderItemStatusEnum::CANCELLED(),
                    source: 'admin',
                ));

                $refundResult = $this->processOrderItemRefund($orderItem, type: 'cancel');
                if (! $refundResult['success']) {
                    DB::rollBack();

                    return $refundResult;
                }

                $recalc = $this->recalculateOrderAmounts($orderItem->order_id, $orderItem);
                if (! $recalc['success']) {
                    DB::rollBack();

                    return $recalc;
                }

                $this->updateOrderStatusAfterCancellation($orderItem->order->fresh());

                $newStatus = OrderItemStatusEnum::CANCELLED();
                $messageKey = 'labels.order_item_cancelled_successfully';
            }

            // pay_rider toggle removed — rider compensation decision moved
            // to the Settle Earnings panel, triggered when the order fully
            // cancels and closeOutRiderAssignmentForCancelledOrder runs.

            if (! $isCollected) {
                $this->refundOrderExtrasIfFullyCancelledByAdmin($orderItem->order->fresh(), $adminId, $reason);
            }

            $this->writeAuditLog(
                orderId: $orderItem->order_id,
                orderItemId: $orderItem->id,
                adminId: $adminId,
                action: 'force_cancel',
                oldValue: ['status' => $oldStatus],
                newValue: [
                    'status' => $newStatus,
                    'routed_via_return' => $isCollected,
                ],
                reason: $reason,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __($messageKey),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'routed_via_return' => $isCollected,
                    'refund_details' => $refundResult['data'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin force-cancel failed', [
                'order_item_id' => $orderItemId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Update an order item status on behalf of the seller or rider.
     * Delegates to the existing seller/rider service methods so all
     * side effects (refunds, restocks, notifications, statements) fire
     * naturally. Hierarchy is fully enforced.
     *
     * @param  string  $targetStatus  One of: accept, reject, preparing, collected, delivered, delivery_failed, confirm_return
     * @param  string|null  $remark  Optional admin remark
     * @param  string|null  $deliveryFailReason  Required when $targetStatus is 'delivery_failed'
     * @return array{success: bool, message: string, data: array}
     */
    public function updateOrderItemStatusByAdmin(
        int $orderItemId,
        string $targetStatus,
        int $adminId,
        ?string $remark = null,
        ?string $deliveryFailReason = null,
    ): array {
        try {
            $orderItem = OrderItem::with(['order', 'store'])->find($orderItemId);
            if (! $orderItem) {
                return ['success' => false, 'message' => __('labels.order_item_not_found'), 'data' => []];
            }

            $oldStatus = $orderItem->status;
            $actingAs = 'seller';

            // Seller-side transitions
            $sellerStatuses = ['accept', 'reject', 'preparing'];
            // Rider-side transitions
            $riderStatuses = ['collected', 'delivered'];

            if (in_array($targetStatus, $sellerStatuses, true)) {
                $sellerId = $orderItem->store?->seller_id;
                if (! $sellerId) {
                    return ['success' => false, 'message' => __('labels.seller_not_found'), 'data' => []];
                }

                $result = $this->updateOrderStatusBySeller($orderItemId, $targetStatus, $sellerId);
                $actingAs = 'seller';
            } elseif (in_array($targetStatus, $riderStatuses, true)) {
                $deliveryBoyId = $orderItem->order?->delivery_boy_id;
                if (! $deliveryBoyId) {
                    return ['success' => false, 'message' => __('labels.no_rider_assigned'), 'data' => []];
                }

                $result = $this->updateOrderItemStatusByDeliveryBoy(
                    $orderItemId,
                    $targetStatus,
                    $deliveryBoyId,
                    otp: null,
                    skipOtp: true,
                );
                $actingAs = 'delivery_boy';
            } elseif ($targetStatus === 'delivery_failed') {
                if (! $deliveryFailReason) {
                    return ['success' => false, 'message' => __('labels.delivery_fail_reason_required'), 'data' => []];
                }
                $deliveryBoyId = $orderItem->order?->delivery_boy_id;
                if (! $deliveryBoyId) {
                    return ['success' => false, 'message' => __('labels.no_rider_assigned'), 'data' => []];
                }

                $result = $this->markDeliveryFailed($orderItemId, $deliveryBoyId, $deliveryFailReason);
                $actingAs = 'delivery_boy';
            } elseif ($targetStatus === 'confirm_return') {
                $sellerId = $orderItem->store?->seller_id;
                if (! $sellerId) {
                    return ['success' => false, 'message' => __('labels.seller_not_found'), 'data' => []];
                }

                $result = $this->confirmStoreReturn($orderItemId, $sellerId);
                $actingAs = 'seller';
            } else {
                return ['success' => false, 'message' => __('labels.invalid_status'), 'data' => []];
            }

            // Write audit log on success
            if ($result['success']) {
                $newStatus = $orderItem->fresh()->status;
                $autoRemark = __('labels.admin_status_update_remark', [
                    'old_status' => formatOrderStatusLabel($oldStatus),
                    'new_status' => formatOrderStatusLabel($newStatus),
                    'acting_as' => $actingAs === 'delivery_boy' ? __('labels.rider') : __('labels.seller'),
                ]);

                $this->writeAuditLog(
                    orderId: $orderItem->order_id,
                    orderItemId: $orderItem->id,
                    adminId: $adminId,
                    action: 'admin_status_update',
                    oldValue: ['status' => $oldStatus],
                    newValue: ['status' => $newStatus, 'acting_as' => $actingAs],
                    reason: $remark ?: $autoRemark,
                );
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Admin on-behalf status update failed', [
                'order_item_id' => $orderItemId,
                'target_status' => $targetStatus,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Bulk apply the same admin-on-behalf transition to many items.
     *
     * Each item is processed independently via updateOrderItemStatusByAdmin
     * — a failure on one item does not roll back successful items. We collect
     * per-item outcomes so the UI can surface a concise summary.
     *
     * @param  int[]  $orderItemIds
     * @return array{success: bool, message: string, data: array{
     *     success_count: int,
     *     fail_count: int,
     *     results: array<int, array{order_item_id: int, success: bool, message: string}>
     * }}
     */
    public function bulkUpdateOrderItemStatusByAdmin(
        array $orderItemIds,
        string $targetStatus,
        int $adminId,
        ?string $remark = null,
        ?string $deliveryFailReason = null,
    ): array {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($orderItemIds as $itemId) {
            $itemId = (int) $itemId;
            $single = $this->updateOrderItemStatusByAdmin(
                orderItemId: $itemId,
                targetStatus: $targetStatus,
                adminId: $adminId,
                remark: $remark,
                deliveryFailReason: $deliveryFailReason,
            );

            $results[] = [
                'order_item_id' => $itemId,
                'success' => (bool) ($single['success'] ?? false),
                'message' => (string) ($single['message'] ?? ''),
            ];

            if (! empty($single['success'])) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $allOk = $failCount === 0;
        $messageKey = $allOk
            ? 'labels.bulk_update_all_succeeded'
            : ($successCount === 0 ? 'labels.bulk_update_all_failed' : 'labels.bulk_update_partial');

        return [
            'success' => $allOk,
            'message' => __($messageKey, ['success' => $successCount, 'fail' => $failCount]),
            'data' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ],
        ];
    }

    /**
     * Seller-side bulk update. Iterates the per-item flow and aggregates per-item
     * outcomes — matches the admin bulk shape so the same UI pattern works for
     * both panels. `accept|reject|preparing` route through updateOrderStatusBySeller;
     * `confirm_return` routes through confirmStoreReturn. Anything else fails the row.
     *
     * @param  int[]  $orderItemIds
     * @return array{success: bool, message: string, data: array{
     *     success_count: int,
     *     fail_count: int,
     *     results: array<int, array{order_item_id: int, success: bool, message: string}>
     * }}
     */
    public function bulkUpdateOrderItemStatusBySeller(
        array $orderItemIds,
        string $targetStatus,
        int $sellerId,
    ): array {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($orderItemIds as $itemId) {
            $itemId = (int) $itemId;

            if (in_array($targetStatus, ['accept', 'reject', 'preparing'], true)) {
                $single = $this->updateOrderStatusBySeller($itemId, $targetStatus, $sellerId);
            } elseif ($targetStatus === 'confirm_return') {
                $single = $this->confirmStoreReturn($itemId, $sellerId);
            } else {
                $single = [
                    'success' => false,
                    'message' => __('labels.invalid_status'),
                    'data' => [],
                ];
            }

            $results[] = [
                'order_item_id' => $itemId,
                'success' => (bool) ($single['success'] ?? false),
                'message' => (string) ($single['message'] ?? ''),
            ];

            if (! empty($single['success'])) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $allOk = $failCount === 0;
        $messageKey = $allOk
            ? 'labels.bulk_update_all_succeeded'
            : ($successCount === 0 ? 'labels.bulk_update_all_failed' : 'labels.bulk_update_partial');

        return [
            'success' => $allOk,
            'message' => __($messageKey, ['success' => $successCount, 'fail' => $failCount]),
            'data' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ],
        ];
    }

    /**
     * Mark an order's payment as received. Moves the order and all its
     * PENDING items to AWAITING_STORE_RESPONSE. Used when payment was
     * made but not reflected in the system.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function markPaymentReceivedByAdmin(int $orderId, int $adminId, ?string $remark = null): array
    {
        try {
            DB::beginTransaction();

            $order = Order::with('items')->find($orderId);
            if (! $order) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }

            if ($order->status !== OrderStatusEnum::PENDING()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_must_be_pending_to_mark_payment'),
                    'data' => ['current_status' => $order->status],
                ];
            }

            $oldOrderStatus = $order->status;
            $oldPaymentStatus = $order->payment_status;

            // Move all PENDING items to AWAITING_STORE_RESPONSE
            $pendingItems = $order->items->filter(
                fn($item) => $item->status === OrderItemStatusEnum::PENDING()
            );

            if ($pendingItems->isEmpty()) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.no_pending_items_found'), 'data' => []];
            }

            foreach ($pendingItems as $item) {
                $item->status = OrderItemStatusEnum::AWAITING_STORE_RESPONSE();
                $item->saveQuietly();

                event(new OrderStatusUpdated(
                    orderItem: $item,
                    oldStatus: OrderItemStatusEnum::PENDING(),
                    newStatus: OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
                    source: 'admin',
                ));
            }

            // Update order status and payment status
            $order->status = OrderStatusEnum::AWAITING_STORE_RESPONSE();
            $order->payment_status = PaymentStatusEnum::COMPLETED();
            $order->save();

            // Update existing payment transaction(s) to completed
            $adminMessage = __('labels.payment_updated_by_admin');
            OrderPaymentTransaction::where('order_id', $order->id)
                ->where('payment_status', PaymentStatusEnum::PENDING())
                ->update([
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                    'message' => $adminMessage,
                ]);

            $autoRemark = __('labels.admin_mark_payment_received_remark');

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'admin_mark_payment_received',
                oldValue: ['status' => $oldOrderStatus, 'payment_status' => $oldPaymentStatus],
                newValue: [
                    'status' => OrderStatusEnum::AWAITING_STORE_RESPONSE(),
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                    'items_updated' => $pendingItems->count(),
                ],
                reason: $remark ?: $autoRemark,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.payment_marked_received_successfully'),
                'data' => [
                    'order' => $order->fresh(),
                    'items_updated' => $pendingItems->count(),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin mark-payment-received failed', [
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Issue an arbitrary wallet credit on an order. For goodwill credits,
     * partial refunds, or anything outside the normal refund logic.
     */
    public function forceRefundByAdmin(int $orderId, float $amount, int $adminId, string $reason): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => __('labels.refund_amount_must_be_positive'), 'data' => []];
        }

        try {
            DB::beginTransaction();

            $order = Order::find($orderId);
            if (! $order) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }

            $walletData = [
                'amount' => $amount,
                'payment_method' => 'refund',
                'transaction_reference' => 'admin_force_refund_order_' . $order->id . '_' . now()->timestamp,
                'description' => "Admin goodwill refund for Order #{$order->id}: {$reason}",
            ];
            $walletResult = $this->walletService->addBalance($order->user_id, $walletData);
            if (! $walletResult['success']) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.refund_processing_failed'), 'data' => $walletResult];
            }

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'force_refund',
                oldValue: null,
                newValue: ['amount' => $amount, 'wallet_credit' => true],
                reason: $reason,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.refund_processed_successfully'),
                'data' => ['amount' => $amount, 'order_id' => $order->id],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin force-refund failed', [
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Unassign the current rider and either reassign to a specific rider or
     * push the order back to READY_FOR_PICKUP for the rider pool to pick up.
     */
    public function reassignRiderByAdmin(int $orderId, ?int $newDeliveryBoyId, int $adminId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $order = Order::find($orderId);
            if (! $order) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }

            $allowedStatuses = [
                OrderStatusEnum::READY_FOR_PICKUP(),
                OrderStatusEnum::ASSIGNED(),
            ];

            if (! in_array($order->status, $allowedStatuses, true)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.reassign_only_ready_or_assigned'),
                    'data' => ['order_status' => $order->status],
                ];
            }

            $currentRiderId = $order->delivery_boy_id;

            if ($currentRiderId) {
                DeliveryBoyAssignment::where('order_id', $order->id)
                    ->where('delivery_boy_id', $currentRiderId)
                    ->whereNotIn('status', [
                        DeliveryBoyAssignmentStatusEnum::COMPLETED(),
                        DeliveryBoyAssignmentStatusEnum::CANCELED(),
                        DeliveryBoyAssignmentStatusEnum::DROPPED(),
                    ])
                    ->update([
                        'status' => DeliveryBoyAssignmentStatusEnum::CANCELED(),
                        'total_earnings' => 0,
                    ]);
            }
            if ($newDeliveryBoyId) {
                $payload = ['delivery_boy_id' => $newDeliveryBoyId];
                if (
                    in_array($order->status, [
                        OrderStatusEnum::READY_FOR_PICKUP(),
                        OrderStatusEnum::ASSIGNED(),
                    ], true)
                ) {
                    $payload['status'] = OrderStatusEnum::ASSIGNED();
                }
                $order->update($payload);

                // Calculate earnings the same way the rider API does
                $order->load(['deliveryZone', 'items']);
                $storeIds = $order->items->pluck('store_id')->unique()->toArray();
                $order->delivery_route = DeliveryZoneService::calculateDeliveryRoute(
                    $order->shipping_latitude,
                    $order->shipping_longitude,
                    $storeIds,
                    $order
                );
                $earnings = $order->delivery_boy_earnings;

                $existingAssignment = DeliveryBoyAssignment::where('order_id', $order->id)
                    ->where('delivery_boy_id', $newDeliveryBoyId)
                    ->whereNotIn('status', [
                        DeliveryBoyAssignmentStatusEnum::CANCELED(),
                        DeliveryBoyAssignmentStatusEnum::DROPPED(),
                    ])
                    ->first();

                if (! $existingAssignment) {
                    DeliveryBoyAssignment::create([
                        'order_id' => $order->id,
                        'delivery_boy_id' => $newDeliveryBoyId,
                        'assigned_at' => now(),
                        'status' => DeliveryBoyAssignmentStatusEnum::ASSIGNED(),
                        'base_fee' => $earnings['breakdown']['base_fee'] ?? 0,
                        'per_store_pickup_fee' => $earnings['breakdown']['per_store_pickup_fee'] ?? 0,
                        'distance_based_fee' => $earnings['breakdown']['distance_based_fee'] ?? 0,
                        'per_order_incentive' => $earnings['breakdown']['per_order_incentive'] ?? 0,
                        'total_earnings' => (float) (($earnings['total'] ?? 0) + ($order->rider_tip_amount ?? 0)),
                    ]);
                }
            } else {
                $payload = ['delivery_boy_id' => null];
                if ($order->status === OrderStatusEnum::ASSIGNED()) {
                    $payload['status'] = OrderStatusEnum::READY_FOR_PICKUP();
                }
                $order->update($payload);
            }

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'reassign_rider',
                oldValue: ['delivery_boy_id' => $currentRiderId],
                newValue: ['delivery_boy_id' => $newDeliveryBoyId],
                reason: $reason,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.rider_reassigned_successfully'),
                'data' => ['order' => $order->fresh()],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin reassign-rider failed', [
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Add a free-text admin note against an order. Lives in the audit log so
     * the timeline stays unified.
     */
    public function addOrderNoteByAdmin(int $orderId, int $adminId, string $note): array
    {
        try {
            $this->writeAuditLog(
                orderId: $orderId,
                orderItemId: null,
                adminId: $adminId,
                action: 'add_note',
                oldValue: null,
                newValue: ['note' => $note],
                reason: $note,
            );

            return [
                'success' => true,
                'message' => __('labels.note_added_successfully'),
                'data' => ['order_id' => $orderId],
            ];
        } catch (Exception $e) {
            Log::error('Admin add-note failed', [
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Settle the rider's earnings on a CANCELLED_BY_ADMIN assignment.
     *
     * The admin sees the auto-recalculated "fair number" on the order detail
     * Delivery card and picks Approve (pay the rider) or Reject (don't pay).
     * Either path requires a remark for accountability — captured in the
     * order_audit_logs.
     *
     * Approve: flips payment_status to PAID, fires a wallet credit for the
     * stored total_earnings. The credit is idempotent (wallet service uses
     * transaction_reference) so a duplicate Approve doesn't double-pay.
     *
     * Reject: flips payment_status to REJECTED, zeroes earnings on the row,
     * no wallet movement.
     *
     * Once payment_status hits PAID with a wallet transaction recorded, the
     * row is locked — admins can't flip it back.
     *
     * @param  int  $assignmentId  DeliveryBoyAssignment id
     * @param  string  $decision  'approve' | 'reject'
     * @param  string  $reason  Required remark
     * @param  int  $adminId  Initiating admin user id
     * @return array{success: bool, message: string, data: array}
     */
    public function settleRiderEarnings(int $assignmentId, string $decision, string $reason, int $adminId): array
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            return ['success' => false, 'message' => __('labels.invalid_decision'), 'data' => []];
        }

        try {
            DB::beginTransaction();

            $assignment = DeliveryBoyAssignment::find($assignmentId);
            if (! $assignment) {
                DB::rollBack();

                return ['success' => false, 'message' => __('labels.assignment_not_found'), 'data' => []];
            }

            // Only assignments in the CANCELLED_BY_ADMIN + PENDING state are
            // eligible. Anything already PAID or REJECTED is locked.
            if ($assignment->status !== DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.settle_only_for_cancelled_by_admin'),
                    'data' => ['current_status' => $assignment->status],
                ];
            }
            if ($assignment->payment_status !== EarningPaymentStatusEnum::PENDING()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.settle_already_decided'),
                    'data' => ['current_payment_status' => $assignment->payment_status],
                ];
            }

            $oldPaymentStatus = $assignment->payment_status;
            $payoutAmount = (float) $assignment->total_earnings;

            if ($decision === 'approve') {
                if ($payoutAmount > 0) {
                    // Wallet credit lands on the rider's User wallet of type
                    // DELIVERY_BOY. transaction_reference makes the credit
                    // idempotent — a duplicate Approve cannot double-pay.
                    $riderUserId = $assignment->deliveryBoy?->user_id;
                    if (! $riderUserId) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'message' => __('labels.rider_user_not_found'),
                            'data' => [],
                        ];
                    }

                    $walletResult = $this->walletService->addBalance(
                        $riderUserId,
                        [
                            'amount' => $payoutAmount,
                            'payment_method' => 'rider_earnings_settlement',
                            'transaction_reference' => 'rider_settlement_assignment_' . $assignment->id,
                            'description' => "Rider earnings settled for cancelled order #{$assignment->order_id}",
                            'order_id' => $assignment->order_id,
                        ],
                        type: WalletTypeEnum::DELIVERY_BOY(),
                    );

                    if (empty($walletResult['success'])) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'message' => __('labels.wallet_credit_failed'),
                            'data' => ['error' => $walletResult['message'] ?? 'unknown'],
                        ];
                    }
                }

                $assignment->update([
                    'payment_status' => EarningPaymentStatusEnum::PAID(),
                    'paid_at' => now(),
                ]);

                $messageKey = 'labels.settle_approved';
            } else {
                // Reject — no wallet movement, zero out earnings for clarity.
                $assignment->update([
                    'payment_status' => EarningPaymentStatusEnum::REJECTED(),
                    'base_fee' => 0,
                    'per_store_pickup_fee' => 0,
                    'distance_based_fee' => 0,
                    'per_order_incentive' => 0,
                    'total_earnings' => 0,
                ]);

                $messageKey = 'labels.settle_rejected';
            }

            $this->writeAuditLog(
                orderId: $assignment->order_id,
                orderItemId: null,
                adminId: $adminId,
                action: 'settle_rider_earnings',
                oldValue: [
                    'payment_status' => $oldPaymentStatus,
                    'total_earnings' => $payoutAmount,
                ],
                newValue: [
                    'decision' => $decision,
                    'payment_status' => $assignment->fresh()->payment_status,
                    'paid_amount' => $decision === 'approve' ? $payoutAmount : 0,
                ],
                reason: $reason,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __($messageKey),
                'data' => [
                    'assignment' => $assignment->fresh(),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Settle rider earnings failed', [
                'assignment_id' => $assignmentId,
                'decision' => $decision,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Phase 3 policy — when an admin force-cancel finalises an order (every
     * item terminal), the customer gets their delivery/handling/per-store
     * pickup fees refunded too, on top of the per-item subtotals refunded
     * during each cancel. Customer-initiated cancellations do NOT refund
     * extras (current behavior, kept).
     *
     * Idempotent: the wallet's transaction_reference dedup means a re-run
     * never double-credits.
     */
    protected function refundOrderExtrasIfFullyCancelledByAdmin(?Order $order, int $adminId, string $reason): void
    {
        if (! $order) {
            return;
        }

        // Only refund when money was actually collected from the customer.
        if ($order->payment_status !== PaymentStatusEnum::COMPLETED()) {
            return;
        }

        // Only when every item is in a terminal state.
        $statuses = OrderItem::where('order_id', $order->id)->pluck('status')->toArray();
        if (empty($statuses)) {
            return;
        }
        $allTerminal = collect($statuses)->every(fn($s) => in_array($s, [
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::FAILED(),
            OrderItemStatusEnum::REFUNDED(),
        ], true));
        if (! $allTerminal) {
            return;
        }

        // Compute the order-level extras the customer paid that haven't been
        // refunded yet. Per-item flow already refunds subtotals; this catches
        // the rest (delivery, handling, per-store pickup fee).
        $extras = (float) ($order->delivery_charge ?? 0)
            + (float) ($order->handling_charges ?? 0)
            + (float) ($order->per_store_drop_off_fee ?? 0);

        if ($extras <= 0) {
            return;
        }

        $reference = 'admin_extras_refund_order_' . $order->id;
        $walletResult = $this->walletService->addBalance($order->user_id, [
            'amount' => $extras,
            'payment_method' => 'refund',
            'transaction_reference' => $reference,
            'description' => "Admin full-cancel extras refund for Order #{$order->id}: {$reason}",
        ]);

        // Wallet service returns success=false when the transaction_reference
        // already exists — that's the idempotency guard. Don't treat that as
        // an error; just skip the audit row in that case.
        if (! empty($walletResult['success'])) {
            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'force_refund',
                oldValue: null,
                newValue: ['amount' => $extras, 'extras_refund' => true],
                reason: __('labels.extras_refund_audit_reason'),
            );
        }
    }

    /**
     * Wraps refundOrderExtrasIfFullyCancelledByAdmin for the seller-confirms
     * path: when the seller's confirm-return finalises an order that was
     * admin-force-cancelled, we still owe the customer the extras refund.
     * This helper looks up the most recent admin-attributed force_cancel
     * audit row to find the originating admin id + reason, then delegates.
     */
    protected function refundOrderExtrasIfAdminInitiated(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $adminEntry = OrderAuditLog::where('order_id', $order->id)
            ->where('action', 'force_cancel')
            ->whereNotNull('admin_id')
            ->orderByDesc('created_at')
            ->first();

        if (! $adminEntry) {
            return;
        }

        $this->refundOrderExtrasIfFullyCancelledByAdmin(
            $order,
            (int) $adminEntry->admin_id,
            (string) ($adminEntry->reason ?? __('labels.extras_refund_audit_reason')),
        );
    }

    /**
     * Internal helper — write a row to the audit log. Admin id is nullable so
     * the same helper can record system-generated entries (escalation, etc.).
     */
    protected function writeAuditLog(
        int $orderId,
        ?int $orderItemId,
        ?int $adminId,
        string $action,
        ?array $oldValue,
        array $newValue,
        ?string $reason,
    ): void {
        try {
            OrderAuditLog::create([
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'admin_id' => $adminId,
                'action' => $action,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the originating action — log and continue.
            Log::warning('Audit log write failed', [
                'order_id' => $orderId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Phase 1C — internal helper. Transition a single item to RETURNING_TO_STORE.
     * Used by both the post-collect rider drop and the delivery-failed flow.
     * Stock is intentionally NOT restocked here: the listener allowlist for
     * UpdateStockOnOrderStatusChange omits this status because the rider is
     * still physically holding the goods. Stock fires when the seller confirms
     * receipt and the item finalises to CANCELLED.
     */
    private function returnItemToStore(OrderItem $orderItem, ?string $reasonCode = null): void
    {
        $oldStatus = $orderItem->status;

        $updates = ['status' => OrderItemStatusEnum::RETURNING_TO_STORE()];
        if ($reasonCode !== null) {
            $updates['delivery_fail_reason'] = $reasonCode;
        }
        $orderItem->update($updates);

        event(new OrderStatusUpdated(
            orderItem: $orderItem,
            oldStatus: $oldStatus,
            newStatus: OrderItemStatusEnum::RETURNING_TO_STORE(),
            source: 'rider',
        ));

        // Deduct the returning item from the order's stored subtotal /
        // total_payable immediately. Centralised here so every caller
        // (admin force-cancel of a collected item, rider delivery-failed,
        // rider drop) gets the recalc for free. Refund timing is unchanged —
        // it still fires on confirmStoreReturn when the seller signs off.
        $this->recalculateOrderAmounts($orderItem->order_id, $orderItem);

        // Bug E — without this the order stayed at COLLECTED/OUT_FOR_DELIVERY
        // even though the only active item was now returning. Use the fresh
        // item set so the order-level status follows the live delivery pool
        // (RETURNING_TO_STORE / DELIVERY_FAILED items are excluded by
        // determineDeliveryOrderStatus).
        $this->recomputeOrderStatusAfterRiderTransition($orderItem);
    }

    /**
     * Bug E — small helper used by the rider drop / delivery-failed paths.
     * Recomputes the order-level status from the current item set. If the
     * live delivery pool is empty (every item is terminal/transient), we fall
     * back to keeping the existing status — better to leave the row alone
     * than to flap it through ASSIGNED in a transient state.
     */
    private function recomputeOrderStatusAfterRiderTransition(OrderItem $orderItem): void
    {
        $order = $orderItem->order?->fresh();
        if (! $order) {
            return;
        }

        $statuses = OrderItem::where('order_id', $order->id)->pluck('status')->toArray();
        $live = array_filter($statuses, fn($s) => ! in_array($s, [
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::FAILED(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
        ]));

        if (empty($live)) {
            // Nothing live left for the rider to deliver. Order is "in flight"
            // pending seller confirmation. Leave the status alone — when the
            // seller confirms the items, updateOrderStatusAfterCancellation
            // will finalize it to CANCELLED.
            return;
        }

        $newStatus = $this->determineDeliveryOrderStatus($statuses);
        if ($newStatus !== $order->status) {
            $order->update(['status' => $newStatus]);
        }
    }

    /**
     * Phase 1C — rider voluntarily abandons the order. Drop is only allowed
     * before any item has been collected. Once even a single item is COLLECTED
     * the rider is on the hook for it: they must either complete delivery or
     * use markDeliveryFailed, which routes the goods back to the seller while
     * preserving earnings. Allowing a "drop after collect" would orphan the
     * physical goods.
     *
     * On a valid (pre-collect) drop: assignment → DROPPED, the order's
     * delivery_boy_id is cleared, and the order goes back to READY_FOR_PICKUP.
     * OrderObserver re-broadcasts to the rider pool. Earnings are zeroed and
     * drop_count++ via the DeliveryBoyService helper, which auto-flags once
     * the threshold is crossed.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function dropOrderByDeliveryBoy(int $orderId, int $deliveryBoyId): array
    {
        try {
            DB::beginTransaction();

            $order = Order::with(['items', 'deliveryBoy'])
                ->where('id', $orderId)
                ->where('delivery_boy_id', $deliveryBoyId)
                ->first();

            if (! $order) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_not_found_or_not_available'),
                    'data' => [],
                ];
            }

            $assignment = DeliveryBoyAssignment::where('order_id', $order->id)
                ->where('delivery_boy_id', $deliveryBoyId)
                ->whereNotIn('status', [
                    DeliveryBoyAssignmentStatusEnum::COMPLETED(),
                    DeliveryBoyAssignmentStatusEnum::CANCELED(),
                    DeliveryBoyAssignmentStatusEnum::DROPPED(),
                ])
                ->first();

            if (! $assignment) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.no_active_assignment_to_drop'),
                    'data' => [],
                ];
            }

            // Refuse the drop the moment any item has been collected — the
            // rider is now physically holding goods and must either deliver
            // them or escalate via markDeliveryFailed. Dropping here would
            // strand the goods with no audit trail.
            $hasCollected = $order->items->contains(
                fn($i) => $i->status === OrderItemStatusEnum::COLLECTED()
            );

            if ($hasCollected) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.cannot_drop_order_after_item_collected'),
                    'data' => [],
                ];
            }

            // Zero out the assignment earnings — rider abandoned the job.
            $assignment->update([
                'status' => DeliveryBoyAssignmentStatusEnum::DROPPED(),
                'total_earnings' => 0,
            ]);

            // Pre-collect drop: rider was assigned but never collected. Push
            // the order back to the rider pool — OrderObserver re-broadcasts.
            $order->update([
                'delivery_boy_id' => null,
                'status' => OrderStatusEnum::READY_FOR_PICKUP(),
            ]);

            // Track the abandon and auto-flag if we're past the threshold.
            $deliveryBoy = $order->deliveryBoy ?? DeliveryBoy::find($deliveryBoyId);
            if ($deliveryBoy) {
                $this->deliveryBoyService->incrementDropCount($deliveryBoy);
            }

            DB::commit();

            Log::info('Rider dropped the order', [
                'order_id' => $order->id,
                'delivery_boy_id' => $deliveryBoyId,
                'phase' => 'pre_collect',
            ]);

            return [
                'success' => true,
                'message' => __('labels.order_dropped_successfully'),
                'data' => [
                    'order' => $order->fresh(),
                    'phase' => 'pre_collect',
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Rider drop failed', [
                'order_id' => $orderId,
                'delivery_boy_id' => $deliveryBoyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Phase 1C — rider attempted to deliver but couldn't. Item must be COLLECTED
     * (rider was holding it). Sets delivery_fail_reason, transitions through
     * DELIVERY_FAILED → RETURNING_TO_STORE, and PRESERVES the rider's earnings —
     * the failure isn't their fault.
     *
     * @param  string  $reasonCode  One of: customer_unavailable, customer_refused,
     *                              wrong_address, unsafe_location.
     * @return array{success: bool, message: string, data: array}
     */
    public function markDeliveryFailed(int $orderItemId, int $deliveryBoyId, string $reasonCode): array
    {
        $allowedReasons = [
            'customer_unavailable',
            'customer_refused',
            'wrong_address',
            'unsafe_location',
        ];

        if (! in_array($reasonCode, $allowedReasons, true)) {
            return [
                'success' => false,
                'message' => __('labels.invalid_delivery_fail_reason'),
                'data' => ['allowed' => $allowedReasons],
            ];
        }

        try {
            DB::beginTransaction();

            $orderItem = OrderItem::with(['order'])
                ->where('id', $orderItemId)
                ->whereHas('order', fn($q) => $q->where('delivery_boy_id', $deliveryBoyId))
                ->first();

            if (! $orderItem) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_found'),
                    'data' => [],
                ];
            }

            if ($orderItem->status !== OrderItemStatusEnum::COLLECTED()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.order_item_must_be_collected_to_mark_delivery_failed'),
                    'data' => ['current_status' => $orderItem->status],
                ];
            }

            // First land DELIVERY_FAILED (records the attempt's outcome) so
            // listeners + audit see the explicit fail event before we auto-step.
            $orderItem->update([
                'status' => OrderItemStatusEnum::DELIVERY_FAILED(),
                'delivery_fail_reason' => $reasonCode,
            ]);

            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: OrderItemStatusEnum::COLLECTED(),
                newStatus: OrderItemStatusEnum::DELIVERY_FAILED(),
                source: 'rider',
            ));

            // Then auto-transition into the return path. The seller confirms
            // receipt later via the Phase 1D confirmStoreReturn flow.
            $this->returnItemToStore($orderItem);

            DB::commit();

            Log::info('Rider marked delivery as failed', [
                'order_item_id' => $orderItem->id,
                'delivery_boy_id' => $deliveryBoyId,
                'reason' => $reasonCode,
            ]);

            return [
                'success' => true,
                'message' => __('labels.delivery_failed_waiting_seller_approval'),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'reason' => $reasonCode,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Rider mark-delivery-failed failed', [
                'order_item_id' => $orderItemId,
                'delivery_boy_id' => $deliveryBoyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function returnOrderItem(
        User $user,
        array $data
    ): array {
        try {
            $orderItem = OrderItem::with(['order', 'product', 'store'])
                ->where('id', $data['order_item_id'])
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (! $orderItem) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.order_item_not_found'),
                );
            }

            // For pickup orders, prevent standard return requests
            $sellerOrder = SellerOrder::where('order_id', $orderItem->order_id)
                ->where('seller_id', $orderItem->store->seller_id)
                ->first();
            if ($sellerOrder && ($sellerOrder->delivery_type?->value ?? $sellerOrder->delivery_type) === 'pickup') {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.pickup_return_physical_only'),
                );
            }

            // Check if the product is cancelable
            if (! $orderItem->return_eligible) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.product_not_returnable'),
                );
            }

            if ($orderItem->status !== OrderItemStatusEnum::DELIVERED() && $orderItem->order->status !== OrderStatusEnum::DELIVERED()) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.order_item_cannot_be_returned_at_current_status'),
                );
            }

            if ($orderItem->return_deadline < Carbon::now()->format('Y-m-d')) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('labels.return_deadline_expired'),
                );
            }

            $existingReturn = OrderItemReturn::where('order_item_id', $orderItem->id)
                ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED())->first();
            if ($existingReturn) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.return_already_requested'),
                );
            }

            // Resolve selected return reason, preserving customer-entered Other text when no ID is sent.
            // Return Flow Fix: Support custom "Other" return reason text when no predefined reason ID is provided.
            $returnReason = null;
            $isCustomReason = empty($data['return_reason_id']) && ! empty($data['reason']);
            if (! empty($data['return_reason_id'])) {
                $returnReason = ReturnReason::where('id', $data['return_reason_id'])->where('status', 'active')->first();
            }
            if (! $returnReason && ! $isCustomReason && $orderItem->product) {
                $availableReasons = $orderItem->product->getAvailableReturnReasons();
                $returnReason = $availableReasons->first();
            }

            // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
            $proofRequired = true;
            $otpRequired = $isCustomReason || (bool) ($returnReason?->otp_required ?? false);
            if ($proofRequired && empty($data['images'])) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('labels.return_proof_images_required') ?? 'Photo proof is required for this return reason.',
                );
            }

            $reasonTitle = $returnReason?->title ?? ($data['reason'] ?? null);
            $customComment = ! empty($data['reason']) ? $data['reason'] : $reasonTitle;

            $return = OrderItemReturn::create([
                'order_item_id' => $orderItem->id,
                'order_id' => $orderItem->order_id,
                'user_id' => $user->id,
                'seller_id' => $orderItem->store->seller_id,
                'store_id' => $orderItem->store_id,
                'reason' => $customComment,
                'return_reason_id' => $returnReason?->id,
                'return_reason_name' => $reasonTitle,
                'snapshot_otp_required' => $otpRequired,
                'snapshot_proof_required' => $proofRequired,
                'refund_amount' => $orderItem->subtotal - $orderItem->promo_discount,
                'pickup_status' => OrderItemReturnPickupStatusEnum::PENDING(),
                'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
            ]);
            if (! empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    SpatieMediaService::uploadFromRequest($return, $image, SpatieMediaCollectionName::ITEM_RETURN_IMAGES());
                }
            }

            return ApiResponseType::toArray(
                success: true,
                message: __('messages.return_request_sent'),
                data: new OrderItemReturnResource($return)
            );
        } catch (Exception $e) {
            return ApiResponseType::toArray(
                success: false,
                message: $e->getMessage(),
                data: null
            );
        }
    }

    public function cancelReturnRequest(
        User $user,
        $orderItemId
    ): array {
        try {

            $return = OrderItemReturn::where('order_item_id', $orderItemId)
                ->where('user_id', $user->id)
                ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED())->first();

            if (! $return) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.return_request_not_found')
                );
            }

            if (! in_array($return->return_status, [OrderItemReturnStatusEnum::REQUESTED(), OrderItemReturnStatusEnum::SELLER_APPROVED()])) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.return_cannot_be_cancelled_now')
                );
            }

            $return->update([
                'return_status' => OrderItemReturnStatusEnum::CANCELLED(),
            ]);

            return ApiResponseType::toArray(
                success: true,
                message: __('messages.return_request_cancelled'),
                data: $return
            );
        } catch (Exception $e) {
            return ApiResponseType::toArray(
                success: false,
                message: $e->getMessage()
            );
        }
    }

    /**
     * Process refund for cancelled order item
     *
     * @param  OrderItem  $orderItem  The cancelled order item
     * @return array Result containing success status and refund details
     */
    public function processOrderItemRefund(
        OrderItem $orderItem,
        $type = 'cancel'
    ): array {
        try {
            $order = $orderItem->order;
            $refundAmount = 0;

            // Calculate refund amount (item subtotal minus any discounts)
            $itemRefundAmount = $orderItem->subtotal - $orderItem->promo_discount;
            $walletCodRefund = $this->getRefundableWalletAmountForCodOrder($orderItem);
            $walletCodRefundAmount = $walletCodRefund['refund_amount'];

            // Wallet refunds only when money has actually been collected from the customer.
            // Pre-delivery cancel/reject: only prepaid (non-COD) orders have funds with us.
            // Post-delivery return_pickup: both prepaid and COD have collected money — both refund.
            $paymentCollected = $order->payment_status === PaymentStatusEnum::COMPLETED();
            $prepaidNonCod = $order->payment_method !== PaymentTypeEnum::COD();
            $codWalletPaid = $order->payment_method === PaymentTypeEnum::COD() && $walletCodRefundAmount > 0;
            $useCodWalletRefund = $type !== 'return_pickup' && $codWalletPaid;
            $shouldRefundToWallet = $type === 'return_pickup'
                ? $paymentCollected
                : (($prepaidNonCod && $paymentCollected) || $useCodWalletRefund);

            if ($shouldRefundToWallet) {

                $description = "Refund for cancelled order item #{$orderItem->id} from Order #{$order->id}";
                if ($type === OrderItemStatusEnum::REJECTED()) {
                    $description = "Refund issued for rejected order item #{$orderItem->id} from order #{$order->id} by the seller.";
                }
                // Add refund to wallet
                $walletData = [
                    'amount' => $useCodWalletRefund ? $walletCodRefundAmount : $itemRefundAmount,
                    'payment_method' => 'refund',
                    'transaction_reference' => "refund_order_item_{$orderItem->id}",
                    'description' => $description,
                ];

                $walletResult = $this->walletService->addBalance($order->user_id, $walletData);

                if (! $walletResult['success']) {
                    return [
                        'success' => false,
                        'message' => __('messages.refund_processing_failed'),
                        'data' => ['error' => $walletResult['message']],
                    ];
                }

                if ($useCodWalletRefund && $order->exists) {
                    $order->update([
                        'wallet_balance' => $walletCodRefund['retained_wallet_amount'],
                        'total_payable' => max(0, $walletCodRefund['active_final_total'] - $walletCodRefund['retained_wallet_amount']),
                    ]);
                }

                $refundAmount = $walletData['amount'];
            }

            return [
                'success' => true,
                'message' => __('messages.refund_processed_successfully'),
                'data' => [
                    'refund_amount' => $refundAmount,
                    'refund_method' => $refundAmount > 0 ? 'wallet' : 'none',
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error processing order item refund', [
                'order_item_id' => $orderItem->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.refund_processing_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function getRefundableWalletAmountForCodOrder(OrderItem $orderItem): array
    {
        $order = $orderItem->order;
        if (! $order || $order->payment_method !== PaymentTypeEnum::COD() || (float) $order->wallet_balance <= 0) {
            return [
                'refund_amount' => 0.0,
                'retained_wallet_amount' => 0.0,
                'active_final_total' => 0.0,
            ];
        }

        $inactiveStatuses = [
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
        ];

        $activeItems = $order->relationLoaded('items')
            ? $order->items->whereNotIn('status', $inactiveStatuses)
            : OrderItem::where('order_id', $order->id)
            ->whereNotIn('status', $inactiveStatuses)
            ->get();

        $activeSubtotal = (float) $activeItems->sum('subtotal');
        if ($activeSubtotal <= 0) {
            $activeFinalTotal = 0.0;
        } else {
            $activePromoDiscount = (float) $activeItems->sum('promo_discount');
            $activeFinalTotal = max(0, $activeSubtotal
                + (float) ($order->delivery_charge ?? 0)
                + (float) ($order->handling_charges ?? 0)
                + (float) ($order->per_store_drop_off_fee ?? 0)
                - ($activePromoDiscount + (float) ($order->gift_card_discount ?? 0)));
        }

        $retainedWalletAmount = min((float) $order->wallet_balance, $activeFinalTotal);
        $refundAmount = round((float) $order->wallet_balance - $retainedWalletAmount, 2);

        return [
            'refund_amount' => $refundAmount,
            'retained_wallet_amount' => $retainedWalletAmount,
            'active_final_total' => $activeFinalTotal,
        ];
    }

    /**
     * Update order status after item cancellation
     *
     * @param  Order  $order  The order to update
     */
    private function updateOrderStatusAfterCancellation(
        Order $order
    ): void {
        $orderItemsStatuses = $this->getOrderItemsStatuses($order->id);

        // Check if all items are cancelled
        $allCancelled = ! empty($orderItemsStatuses) &&
            array_filter($orderItemsStatuses, fn($status) => $status !== OrderItemStatusEnum::CANCELLED()) === [];

        if ($allCancelled) {
            $order->update(['status' => OrderStatusEnum::CANCELLED()]);
            // Bug B — close out the rider's assignment so the rider's "in flight"
            // queue doesn't keep this order forever. Earnings are intentionally
            // preserved on the assignment row: the rider still did partial work.
            $this->closeOutRiderAssignmentForCancelledOrder($order);

            return;
        }

        // Update to partially cancelled or keep existing status based on remaining items
        $hasActiveItems = array_filter($orderItemsStatuses, fn($status) => ! in_array($status, [
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::FAILED(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
        ]));

        if (empty($hasActiveItems) && ! empty($orderItemsStatuses)) {
            $failedCount = count(array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::FAILED()));
            $finalStatus = ($failedCount === count($orderItemsStatuses)) ? OrderStatusEnum::FAILED() : OrderStatusEnum::CANCELLED();
            if ($order->status !== $finalStatus) {
                $order->update(['status' => $finalStatus]);
            }
            if ($finalStatus === OrderStatusEnum::CANCELLED()) {
                $this->closeOutRiderAssignmentForCancelledOrder($order);
            }

            return;
        }

        // Pick the right state machine based on whether the order has crossed
        // the seller → rider handoff. With a rider attached we MUST NOT drop
        // the order back to READY_FOR_PICKUP via the seller-side machine —
        // the rider is already on the job and the order has moved into the
        // delivery phase. determineDeliveryOrderStatus returns ASSIGNED for
        // items still in PREPARING when a rider is attached, which is what
        // the admin force-cancel flow expects.
        $newStatus = $order->delivery_boy_id
            ? $this->determineDeliveryOrderStatus($orderItemsStatuses)
            : $this->determineSellerOrderStatus($orderItemsStatuses);

        if ($newStatus !== $order->status) {
            $order->update(['status' => $newStatus]);
        }
    }

    /**
     * Close out the rider's active assignment when the order finalises to
     * CANCELLED. The assignment row is flipped to CANCELLED_BY_ADMIN (distinct
     * from CANCELED which is used for reassign / supersede) so the rider keeps
     * the order in their history.
     *
     * Earnings on the row are preserved as-is — they were calculated at
     * acceptance time and represent what the rider would have earned for the
     * full trip. The admin reviews via the Settle Earnings panel on the
     * Delivery card and explicitly chooses:
     *   - Approve → wallet credit fires for the preserved amount.
     *   - Reject  → earnings zero out, no payout.
     *
     * We deliberately do NOT auto-zero based on current item statuses here.
     * At the moment this method runs, every item is already in a terminal
     * status (that's WHY the order just flipped to CANCELLED), so a
     * state-based heuristic would always conclude "rider did nothing" and
     * silently strip the row of its earnings. The admin's explicit decision
     * is the only reliable signal.
     */
    private function closeOutRiderAssignmentForCancelledOrder(Order $order): void
    {
        $this->refundRiderTipToCustomerWallet($order);

        if (! $order->delivery_boy_id) {
            return;
        }

        DeliveryBoyAssignment::where('order_id', $order->id)
            ->where('delivery_boy_id', $order->delivery_boy_id)
            ->whereNotIn('status', [
                DeliveryBoyAssignmentStatusEnum::COMPLETED(),
                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                DeliveryBoyAssignmentStatusEnum::DROPPED(),
                DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
            ])
            ->update([
                'status' => DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN(),
                'payment_status' => EarningPaymentStatusEnum::PENDING(),
            ]);

        $order->update(['delivery_boy_id' => null]);
    }

    // This Function will only work when status updated from the seller side
    public function determineSellerOrderStatus(
        array $orderItemsStatuses
    ): string {
        if (empty($orderItemsStatuses)) {
            return OrderStatusEnum::PENDING();
        }

        $totalCount = count($orderItemsStatuses);
        $awaiting = array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::AWAITING_STORE_RESPONSE());
        $rejected = array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::REJECTED());
        $accepted = array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::ACCEPTED());

        // All items are still awaiting the store's first response.
        if (count($awaiting) === $totalCount) {
            return OrderStatusEnum::AWAITING_STORE_RESPONSE();
        }

        // All items are rejected by the seller.
        if (count($rejected) === $totalCount) {
            return OrderStatusEnum::REJECTED_BY_SELLER();
        }

        if (! empty($awaiting) && ! empty($rejected)) {
            return OrderStatusEnum::PARTIALLY_REJECTED();
        }

        // Some items still awaiting + at least one already responded → partially accepted.
        if (! empty($awaiting)) {
            return OrderStatusEnum::PARTIALLY_ACCEPTED();
        }

        // From here every item has been responded to. Look at the live (non-terminal) items
        // — if any live item is still ACCEPTED (not yet PREPARING), the order is not
        // physically ready for pickup. Returning READY_FOR_PICKUP early would fire the
        // rider-notification observer prematurely, which is BUG-2 in the lifecycle plan.
        $live = array_filter($orderItemsStatuses, fn($status) => ! in_array($status, [
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::FAILED(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
        ]));

        if (empty($live)) {
            $failedCount = count(array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::FAILED()));

            return ($failedCount === count($orderItemsStatuses)) ? OrderStatusEnum::FAILED() : OrderStatusEnum::CANCELLED();
        }

        // If all live items are delivered, return DELIVERED.
        $delivered = array_filter($live, fn($status) => $status === OrderItemStatusEnum::DELIVERED());
        if (count($delivered) === count($live)) {
            return OrderStatusEnum::DELIVERED();
        }

        if (! empty($accepted)) {
            return OrderStatusEnum::ACCEPTED_BY_SELLER();
        }

        return OrderStatusEnum::READY_FOR_PICKUP();
    }

    /**
     * Update the status of an order item by delivery boy
     *
     * @param  int  $orderItemId  The ID of the order item to update
     * @param  string  $status  The new status to set ('collected', 'delivered')
     * @param  int  $deliveryBoyId  The ID of the delivery boy making the update
     * @param  string|null  $otp  The OTP provided for verification (if required)
     * @return array Result containing success status, message, and order data
     */
    public function updateOrderItemStatusByDeliveryBoy(
        int $orderItemId,
        string $status,
        int $deliveryBoyId,
        ?string $otp = null,
        bool $skipOtp = false
    ): array {
        try {
            // Validate status parameter
            if (! in_array($status, ['collected', 'delivered'])) {
                return [
                    'success' => false,
                    'message' => __('labels.invalid_status'),
                    'data' => [],
                ];
            }

            // Find the order item
            $orderItem = OrderItem::where('id', $orderItemId)
                ->whereHas('order', function ($q) use ($deliveryBoyId) {
                    $q->where('delivery_boy_id', $deliveryBoyId);
                })
                ->with(['order', 'product', 'store.seller.user'])
                ->first();

            if (! $orderItem) {
                if ($status === OrderItemStatusEnum::DELIVERED()) {
                    return $this->verifyOrderWiseOtpItemsFromOrderStatus($orderItemId, $deliveryBoyId, $otp);
                }

                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_found'),
                    'data' => [],
                ];
            }

            // Check if OTP verification is required for this item when delivering
            $requiresOtp = $orderItem->requires_otp ?? $orderItem->product?->requires_otp ?? false;
            if ($status === OrderItemStatusEnum::DELIVERED() && $requiresOtp && ! $skipOtp) {
                if ($this->deliveryOtpModeForOrder($orderItem->order) === DeliveryOtpModeEnum::ORDER_WISE()) {
                    return $this->verifyOrderWiseOtpItemsFromItemStatus($orderItem, $deliveryBoyId, $otp);
                } else {
                    // If no OTP provided
                    if (! $otp) {
                        return [
                            'success' => false,
                            'message' => __('labels.otp_required'),
                            'data' => [],
                        ];
                    }

                    // If OTP doesn't match
                    if ($orderItem->otp !== $otp) {
                        return [
                            'success' => false,
                            'message' => __('labels.invalid_otp'),
                            'data' => [],
                        ];
                    }

                    // Mark as OTP verified
                    $orderItem->otp_verified = true;
                    $orderItem->save();
                }

                // When calling order-items.update-status API, once OTP gets verified make its status as COLLECTED only.
                // It will be delivered when delivery boy calls delivered all API.
                if ($orderItem->status === OrderItemStatusEnum::COLLECTED()) {
                    return [
                        'success' => true,
                        'message' => __('messages.order_status_updated_successfully'),
                        'data' => [
                            'order_item' => $orderItem->fresh(['order', 'product', 'store.seller.user']),
                            'order' => $orderItem->order->fresh(),
                        ],
                    ];
                }

                $status = 'collected';
            }

            $currentStatus = $orderItem->status;
            $updateStatus = $this->mapStatusToEnum($status, 'delivery_boy');

            if (! $updateStatus) {
                return [
                    'success' => false,
                    'message' => __('labels.invalid_status'),
                    'data' => [],
                ];
            }

            // Validate the status transition
            $validationResult = $this->validateStatusTransition($currentStatus, $updateStatus, 'delivery_boy');
            if (! $validationResult['success']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                    'data' => [],
                ];
            }

            // Update the order status and related entities
            return $this->updateOrderStatusAndRelatedEntities(
                $orderItem,
                $updateStatus,
                $currentStatus,
                'delivery_boy',
                $deliveryBoyId
            );
        } catch (Exception $e) {
            Log::error('Error in updateOrderItemStatusByDeliveryBoy', [
                'order_item_id' => $orderItemId,
                'status' => $status,
                'delivery_boy_id' => $deliveryBoyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.order_status_update_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Bulk collect order items for a specific store in an order.
     */
    public function bulkCollectOrderItems(int $orderId, int $storeId, int $deliveryBoyId): array
    {
        $order = Order::where('id', $orderId)
            ->where('delivery_boy_id', $deliveryBoyId)
            ->with(['items.product', 'items.store.seller.user'])
            ->first();

        if (! $order) {
            return [
                'success' => false,
                'message' => __('labels.order_not_found'),
                'data' => [],
            ];
        }

        $storeItems = $order->items->where('store_id', $storeId);

        if ($storeItems->isEmpty()) {
            return [
                'success' => false,
                'message' => __('labels.order_item_not_found'),
                'data' => [],
            ];
        }

        $itemsToCollect = $storeItems->filter(fn($item) => $item->status === OrderItemStatusEnum::PREPARING());

        if ($itemsToCollect->isEmpty()) {
            $allCollectedOrDelivered = $storeItems->every(fn($item) => in_array($item->status, [
                OrderItemStatusEnum::COLLECTED(),
                OrderItemStatusEnum::DELIVERED(),
            ]));

            if ($allCollectedOrDelivered) {
                return [
                    'success' => true,
                    'message' => __('messages.order_status_updated_successfully'),
                    'data' => [
                        'order' => $order->fresh(),
                        'order_items' => $storeItems,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => __('labels.order_item_must_be_preparing_first'),
                'data' => [],
            ];
        }

        try {
            DB::beginTransaction();

            foreach ($itemsToCollect as $item) {
                $res = $this->updateOrderStatusAndRelatedEntities(
                    $item,
                    OrderItemStatusEnum::COLLECTED(),
                    $item->status,
                    'delivery_boy',
                    $deliveryBoyId
                );

                if (! $res['success']) {
                    throw new Exception($res['message']);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => __('messages.order_status_updated_successfully'),
                'data' => [
                    'order' => $order->fresh(),
                    'order_items' => $order->items()->where('store_id', $storeId)->get(),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in bulkCollectOrderItems', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'delivery_boy_id' => $deliveryBoyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage() ?: __('messages.order_status_update_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Bulk deliver all collected order items in an order.
     */
    public function bulkDeliverOrderItems(int $orderId, int $deliveryBoyId, ?string $otp = null): array
    {
        $order = Order::where('id', $orderId)
            ->where('delivery_boy_id', $deliveryBoyId)
            ->with(['items.product', 'items.store.seller.user'])
            ->first();

        if (! $order) {
            return [
                'success' => false,
                'message' => __('labels.order_not_found'),
                'data' => [],
            ];
        }

        $items = $order->items;

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'message' => __('labels.order_item_not_found'),
                'data' => [],
            ];
        }

        $itemsToDeliver = $items->filter(fn($item) => $item->status === OrderItemStatusEnum::COLLECTED());

        if ($itemsToDeliver->isEmpty()) {
            $allDelivered = $items->every(fn($item) => in_array($item->status, [
                OrderItemStatusEnum::DELIVERED(),
                OrderItemStatusEnum::REJECTED(),
                OrderItemStatusEnum::CANCELLED(),
                OrderItemStatusEnum::DELIVERY_FAILED(),
                OrderItemStatusEnum::RETURNING_TO_STORE(),
                OrderItemStatusEnum::FAILED(),
            ]));

            if ($allDelivered) {
                return [
                    'success' => true,
                    'message' => __('messages.order_status_updated_successfully'),
                    'data' => [
                        'order' => $order->fresh(),
                        'order_items' => $items,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => __('labels.order_item_must_be_collected_first'),
                'data' => [],
            ];
        }

        $requiresOtp = $this->orderRequiresDeliveryOtp($order);

        if ($requiresOtp && $this->deliveryOtpModeForOrder($order) === DeliveryOtpModeEnum::ORDER_WISE()) {
            $otpResult = $this->verifyOrderWiseDeliveryOtp($order, $otp);
            if (! $otpResult['success']) {
                return $otpResult;
            }
        } elseif ($requiresOtp) {
            return [
                'success' => false,
                'message' => __('labels.otp_required'),
                'data' => [],
            ];
        }

        try {
            DB::beginTransaction();

            foreach ($itemsToDeliver as $item) {
                $itemRequiresOtp = $item->requires_otp ?? $item->product?->requires_otp ?? false;
                if ($itemRequiresOtp && ! ($item->otp_verified ?? false)) {
                    $item->otp_verified = true;
                    $item->save();
                }

                $res = $this->updateOrderStatusAndRelatedEntities(
                    $item,
                    OrderItemStatusEnum::DELIVERED(),
                    $item->status,
                    'delivery_boy',
                    $deliveryBoyId
                );

                if (! $res['success']) {
                    throw new Exception($res['message']);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => __('messages.order_status_updated_successfully'),
                'data' => [
                    'order' => $order->fresh(),
                    'order_items' => $order->items()->get(),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in bulkDeliverOrderItems', [
                'order_id' => $orderId,
                'delivery_boy_id' => $deliveryBoyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage() ?: __('messages.order_status_update_failed'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Determine the overall order status based on the statuses of all order items for delivery
     *
     * @param  array  $orderItemsStatuses  Array of order item statuses
     * @return string The determined order status
     */
    private function determineDeliveryOrderStatus(
        array $orderItemsStatuses
    ): string {
        if (empty($orderItemsStatuses)) {
            return OrderStatusEnum::PENDING();
        }

        // Items in REJECTED/CANCELLED have left the order entirely. Items in
        // DELIVERY_FAILED and RETURNING_TO_STORE have left the active delivery
        // pipeline (they're heading back to the seller). Treat all four as "out"
        // of the live delivery pool so the order-level status reflects only the
        // items the rider is still actively responsible for.
        $exited = [
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::DELIVERY_FAILED(),
            OrderItemStatusEnum::RETURNING_TO_STORE(),
            OrderItemStatusEnum::FAILED(),
        ];
        $live = array_filter($orderItemsStatuses, fn($status) => ! in_array($status, $exited));

        if (empty($live)) {
            $failedCount = count(array_filter($orderItemsStatuses, fn($status) => $status === OrderItemStatusEnum::FAILED()));

            return ($failedCount === count($orderItemsStatuses)) ? OrderStatusEnum::FAILED() : OrderStatusEnum::CANCELLED();
        }

        $collected = array_filter($live, fn($status) => $status === OrderItemStatusEnum::COLLECTED());
        $delivered = array_filter($live, fn($status) => $status === OrderItemStatusEnum::DELIVERED());

        // If every live item has been delivered, the order is DELIVERED.
        if (count($delivered) === count($live)) {
            return OrderStatusEnum::DELIVERED();
        }

        // If at least one item is collected and others are pending delivery, mark order as COLLECTED
        if (! empty($collected) && count($collected) < count($live)) {
            return OrderStatusEnum::COLLECTED();
        }

        // If all collected or delivered items match the live count, the order is OUT_FOR_DELIVERY
        if ((count($collected) + count($delivered)) === count($live)) {
            return OrderStatusEnum::OUT_FOR_DELIVERY();
        }

        // Default to ASSIGNED if the order has a delivery boy but no collected/delivered items yet
        return OrderStatusEnum::ASSIGNED();
    }

    public function getOrderDeliveryBoyLocation(
        User $user,
        string $orderSlug
    ): array {
        try {
            // Find the order with the given ID and user
            $order = Order::where('slug', $orderSlug)
                ->where('user_id', $user->id)
                ->with(['items.product', 'items.variant', 'items.store', 'deliveryBoy'])
                ->first();

            if (! $order) {
                return [
                    'success' => false,
                    'message' => __('labels.order_not_found'),
                    'data' => [],
                ];
            }
            // Get store IDs from order items
            $storeIds = $order->items->pluck('store_id')->unique()->toArray();

            // Calculate delivery route
            $deliveryRoute = DeliveryZoneService::calculateDeliveryRoute(
                $order->shipping_latitude,
                $order->shipping_longitude,
                $storeIds,
                $order
            );
            if ($order->status === OrderStatusEnum::DELIVERED()) {
                return [
                    'success' => true,
                    'message' => __('labels.order_delivered_already'),
                    'data' => [
                        'delivery_boy' => [],
                        'route' => $deliveryRoute,
                        'order' => new OrderResource($order),
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => __('labels.order_delivery_boy_location_retrieved_successfully'),
                'data' => [
                    'delivery_boy' => ! empty($order->deliveryBoy) ? $this->deliveryBoyService->getLastLocation($order->delivery_boy_id) : [],
                    'route' => $deliveryRoute,
                    'order' => new OrderResource($order),
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function resolveScheduledPreparationStartTime(Order $order, ?Store $store): ?Carbon
    {
        $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');

        if ($store && (int) ($store->scheduled_delivery_buffer_minutes ?? 60) === 0) {
            return null;
        }

        if ($order->preparation_scheduled_at) {
            return Carbon::parse($order->preparation_scheduled_at, $storeTz);
        }

        if (! $order->scheduled_date) {
            return null;
        }

        if ($order->scheduled_slot_start_time) {
            $slotStartTime = $order->scheduled_slot_start_time;
        } elseif ($order->deliveryTimeSlot) {
            $slotStartTime = $order->deliveryTimeSlot->start_time;
        } else {
            $dayName = strtolower($order->scheduled_date->format('l'));
            $slotStartTime = $store?->metadata['operating_hours'][$dayName]['open_time'] ?? '09:00:00';
        }

        $slotStart = Carbon::parse($order->scheduled_date->format('Y-m-d') . ' ' . $slotStartTime, $storeTz);
        $order->loadMissing(['items.product', 'items.store']);
        $prepDuration = app(PreparationSchedulerService::class)->getOrderPrepTime($order);

        return $slotStart->subMinutes($prepDuration);
    }

    public function resolveScheduledAcceptanceStartTime(Order $order, ?Store $store): ?Carbon
    {
        if (! $order->scheduled_date) {
            return null;
        }

        $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');
        if ($order->scheduled_slot_start_time) {
            $slotStartTime = $order->scheduled_slot_start_time;
        } elseif ($order->deliveryTimeSlot) {
            $slotStartTime = $order->deliveryTimeSlot->start_time;
        } else {
            // Date-only scheduled orders start at the store opening time for that day.
            $dayName = strtolower($order->scheduled_date->format('l'));
            $slotStartTime = $store?->metadata['operating_hours'][$dayName]['open_time'] ?? '09:00:00';
        }

        return Carbon::parse($order->scheduled_date->format('Y-m-d') . ' ' . $slotStartTime, $storeTz);
    }

    public function deliverOrderItemByPickup(OrderItem $orderItem, int $sellerId): array
    {
        $oldStatus = $orderItem->status;

        return $this->updateOrderStatusAndRelatedEntities(
            $orderItem,
            OrderItemStatusEnum::DELIVERED(),
            $oldStatus,
            'seller',
            $sellerId
        );
    }

    public static function checkUserReviewExistByOrderItemId($id): bool
    {
        return Review::where(['order_item_id' => $id, 'user_id' => auth()->id()])->exists();
    }

    public static function getUserReviewByOrderItemId($id): ?ReviewResource
    {
        $review = Review::where(['order_item_id' => $id, 'user_id' => auth()->id()])->get()->first();
        if ($review) {
            return new ReviewResource($review);
        }

        return null;
    }

    /**
     * Cancel a scheduled order with wallet refund (system-initiated).
     * Used by the revalidation guardian when reschedule deadlines expire
     * and by the customer recovery cancel flow.
     * COD orders are excluded from wallet refunds since no payment was collected.
     */
    public function cancelScheduledOrderWithRefund(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->load(['items', 'user']);
            $totalRefunded = 0.0;

            // Cancel each non-cancelled order item and process refund (skips COD internally)
            foreach ($order->items as $item) {
                if ($item->status === OrderItemStatusEnum::CANCELLED()) {
                    continue;
                }

                $item->update(['status' => OrderItemStatusEnum::CANCELLED()]);

                // Only refund to wallet for non-COD prepaid orders
                $refundResult = $this->processOrderItemRefund($item, 'cancel');
                if ($refundResult['success'] && ($refundResult['data']['refund_amount'] ?? 0) > 0) {
                    $totalRefunded += $refundResult['data']['refund_amount'];
                }
            }

            // Recalculate order totals after all items are cancelled
            $this->recalculateOrderAmounts($order->id);

            // Mark order as cancelled
            $order->update([
                'status' => OrderStatusEnum::CANCELLED(),
                'reschedule_status' => 'cancelled',
                'reschedule_reason' => $reason,
            ]);

            // Close out rider assignment if present
            $this->closeOutRiderAssignmentForCancelledOrder($order);

            // Notify the customer about auto-cancellation
            try {
                $order->user?->notify(
                    new ScheduledOrderAutoCancelled($order, $totalRefunded)
                );
            } catch (\Throwable $e) {
                Log::warning("cancelScheduledOrderWithRefund: Failed to notify user for order #{$order->id}: {$e->getMessage()}");
            }
        });
    }

    public function creditRiderTipOnDelivery(Order $order): void
    {
        $tipAmount = (float) ($order->rider_tip_amount ?? 0);
        if ($tipAmount <= 0) {
            return;
        }

        // Avoid double crediting
        $existingLog = OrderAuditLog::where('order_id', $order->id)
            ->where('action', 'RIDER_TIP_CREDITED')
            ->first();
        if ($existingLog) {
            return;
        }

        $rider = $order->deliveryBoy;
        if (! $rider) {
            return;
        }

        // Tip is included directly in the rider's order earnings (DeliveryBoyAssignment),
        // so no separate wallet transaction is created. Record audit log for tracking.
        OrderAuditLog::create([
            'order_id' => $order->id,
            'action' => 'RIDER_TIP_CREDITED',
            'old_value' => null,
            'new_value' => ['tip_amount' => $tipAmount, 'rider_id' => $rider->id, 'integrated_in_order_earnings' => true],
        ]);
    }

    public function refundRiderTipToCustomerWallet(Order $order): void
    {
        $tipAmount = (float) ($order->rider_tip_amount ?? 0);
        if ($tipAmount <= 0) {
            return;
        }

        $existingRefund = OrderAuditLog::where('order_id', $order->id)
            ->where('action', 'ORDER_CANCELLED_TIP_REFUND')
            ->first();
        if ($existingRefund) {
            return;
        }

        $existingCredit = OrderAuditLog::where('order_id', $order->id)
            ->where('action', 'RIDER_TIP_CREDITED')
            ->first();
        if ($existingCredit) {
            return;
        }

        $this->walletService->creditWallet(
            userId: $order->user_id,
            walletType: WalletTypeEnum::CUSTOMER(),
            amount: $tipAmount,
            description: 'Refund for rider tip on cancelled Order #' . ($order->order_id ?? $order->id),
            referenceId: (string) $order->id
        );

        OrderAuditLog::create([
            'order_id' => $order->id,
            'action' => 'ORDER_CANCELLED_TIP_REFUND',
            'old_value' => ['tip_amount' => $tipAmount],
            'new_value' => ['refunded' => true],
        ]);
    }

    public function processPostOrderTip(Order $order, float $tipAmount, string $paymentMethod, ?string $transactionId = null): array
    {
        $tippingConfig = Setting::getRiderTipSettings();
        if (! ($tippingConfig['enable_rider_tips'] ?? true)) {
            return [
                'success' => false,
                'message' => 'Rider tipping is currently disabled.',
                'data' => [],
            ];
        }

        if (! empty($transactionId) && $tipAmount <= 0) {
            $existingTx = OrderPaymentTransaction::where('transaction_id', $transactionId)
                ->where('user_id', $order->user_id)
                ->first();
            if ($existingTx && (float) $existingTx->amount > 0) {
                $tipAmount = (float) $existingTx->amount;
            }
        }

        $minTip = (float) ($tippingConfig['min_tip_amount'] ?? 0);
        $maxTip = (float) ($tippingConfig['max_tip_amount'] ?? 99999);
        if ($tipAmount < $minTip || $tipAmount > $maxTip) {
            return [
                'success' => false,
                'message' => "Tip amount must be between {$minTip} and {$maxTip}.",
                'data' => [],
            ];
        }

        $disallowedStatuses = [
            OrderStatusEnum::DELIVERED(),
            OrderStatusEnum::CANCELLED(),
            OrderStatusEnum::FAILED(),
            OrderStatusEnum::REJECTED_BY_SELLER(),
        ];
        if (in_array((string) (is_callable($order->status) ? $order->status() : ($order->status->value ?? $order->status)), array_map(fn($s) => (string) (is_callable($s) ? $s() : ($s->value ?? $s)), $disallowedStatuses), true)) {
            return [
                'success' => false,
                'message' => 'Rider tipping is not allowed for delivered or cancelled orders.',
                'data' => [],
            ];
        }

        $normalizedPaymentMethod = strtolower($paymentMethod);
        if ($normalizedPaymentMethod === 'cod' || $paymentMethod === PaymentTypeEnum::COD()) {
            return [
                'success' => false,
                'message' => 'Rider tipping is only allowed with online or wallet payment methods.',
                'data' => [],
            ];
        }

        if ($normalizedPaymentMethod === 'wallet' || $paymentMethod === PaymentTypeEnum::WALLET()) {
            $orderCurrency = app(CurrencyWriteGuard::class)->orderCurrency($order);
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $order->user_id, 'type' => WalletTypeEnum::CUSTOMER()],
                ['balance' => 0.00, 'currency_code' => $orderCurrency]
            );

            if ((float) $wallet->balance < $tipAmount) {
                return [
                    'success' => false,
                    'message' => 'Insufficient wallet balance to pay rider tip.',
                    'data' => [],
                ];
            }

            $oldBalance = (float) $wallet->balance;
            $newBalance = max(0, $oldBalance - $tipAmount);
            $wallet->update(['balance' => $newBalance]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'amount' => $tipAmount,
                'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                'payment_method' => 'wallet',
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'currency_code' => app(CurrencyWriteGuard::class)->walletCurrency($wallet),
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'description' => "Rider tip for Order #{$order->id}",
            ]);

            return $this->applyCompletedTipToOrder($order, $tipAmount, 'wallet_' . Str::random(12));
        }

        // Online Gateway Payment (Stripe, Razorpay, Paystack, Flutterwave, Xendit)
        if (! empty($transactionId)) {
            $orderCurrency = app(CurrencyWriteGuard::class)->orderCurrency($order);
            $existingTx = OrderPaymentTransaction::where('user_id', $order->user_id)
                ->where(function ($q) use ($transactionId, $order) {
                    $q->where('transaction_id', $transactionId)
                        ->orWhere(function ($q2) use ($transactionId, $order) {
                            $q2->where('order_id', $order->id)
                                ->where(function ($q3) use ($transactionId) {
                                    $q3->where('payment_details->xendit_external_id', $transactionId)
                                        ->orWhere('payment_details->xendit_invoice_id', $transactionId);
                                });
                        });
                })
                ->first();

            if (! $existingTx) {
                $existingTx = OrderPaymentTransaction::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'transaction_id' => $transactionId,
                    'amount' => $tipAmount,
                    'currency' => $orderCurrency,
                    'order_currency_code' => $orderCurrency,
                    'gateway_currency_code' => $orderCurrency,
                    'gateway_amount' => $tipAmount,
                    'payment_method' => $paymentMethod,
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                ]);
            } else {
                $existingTx->update([
                    'order_id' => $order->id,
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                ]);
            }

            return $this->applyCompletedTipToOrder($order, (float) $existingTx->amount, $transactionId);
        }

        // Initiate Gateway Payment Session (Reuse existing pending transaction if present)
        $pendingTx = OrderPaymentTransaction::where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->where('payment_status', PaymentStatusEnum::PENDING())
            ->latest()
            ->first();

        if (! $pendingTx) {
            $txId = 'TIP-' . Str::upper(Str::random(12));
            $orderCurrency = app(CurrencyWriteGuard::class)->orderCurrency($order);
            $pendingTx = OrderPaymentTransaction::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'transaction_id' => $txId,
                'amount' => $tipAmount,
                'currency' => $orderCurrency,
                'order_currency_code' => $orderCurrency,
                'gateway_currency_code' => $orderCurrency,
                'gateway_amount' => $tipAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatusEnum::PENDING(),
            ]);
        } else {
            $pendingTx->update([
                'amount' => $tipAmount,
                'payment_method' => $paymentMethod,
            ]);
            $txId = $pendingTx->transaction_id;
        }

        $responseData = [
            'order' => $order->fresh(),
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'transaction_id' => $txId,
            'amount' => $tipAmount,
            'currency' => app(CurrencyWriteGuard::class)->orderCurrency($order),
        ];

        // Generate gateway payment invoice/link when a hosted tip checkout is selected.
        $normalizedMethod = strtolower($paymentMethod);
        if ($normalizedMethod === 'mercadopago' || $normalizedMethod === 'mercado_pago' || $paymentMethod === PaymentTypeEnum::MERCADO_PAGO() || $paymentMethod === 'mercadoPagoPayment') {
            try {
                $xenditController = app(XenditController::class);
                $xenditResult = $xenditController->createTipInvoice($order, $tipAmount, $txId);

                if (($xenditResult['success'] ?? false) && ! empty($xenditResult['data']['invoice_url'])) {
                    $xenditInvoiceUrl = $xenditResult['data']['invoice_url'];

                    $details = $pendingTx->payment_details ?? [];
                    $details['xendit_external_id'] = $txId;
                    $details['xendit_invoice_url'] = $xenditInvoiceUrl;
                    $details['xendit_invoice_id'] = $xenditResult['data']['invoice_id'] ?? null;
                    $details['xendit_invoice_id'] = $xenditResult['data']['invoice_id'] ?? null;
                    $pendingTx->update(['payment_details' => $details]);

                    $responseData['payment_url'] = $xenditInvoiceUrl;
                    $responseData['invoice_url'] = $xenditInvoiceUrl;
                    $responseData['payment_url'] = $xenditInvoiceUrl;
                    $responseData['invoice_url'] = $xenditInvoiceUrl;
                    $responseData['checkout_url'] = $xenditInvoiceUrl;
                    $responseData['link'] = $xenditInvoiceUrl;
                    $responseData['link'] = $xenditInvoiceUrl;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to create Xendit tip invoice: {$e->getMessage()}");
            }
        } elseif ($normalizedMethod === 'flutterwave' || $paymentMethod === PaymentTypeEnum::FLUTTERWAVE() || $paymentMethod === 'flutterwavePayment') {
            try {
                $flwController = app(FlutterwaveController::class);
                $flwController = app(FlutterwaveController::class);
                $flwResult = $flwController->createTipInitialize($order, $tipAmount, $txId);

                if (($flwResult['success'] ?? false) && ! empty($flwResult['data']['payment_url'])) {
                    $flwPaymentUrl = $flwResult['data']['payment_url'];

                    $details = $pendingTx->payment_details ?? [];
                    $details['flutterwave_tx_ref'] = $txId;
                    $details['flutterwave_payment_url'] = $flwPaymentUrl;
                    $pendingTx->update(['payment_details' => $details]);

                    $responseData['payment_url'] = $flwPaymentUrl;
                    $responseData['invoice_url'] = $flwPaymentUrl;
                    $responseData['payment_url'] = $flwPaymentUrl;
                    $responseData['invoice_url'] = $flwPaymentUrl;
                    $responseData['checkout_url'] = $flwPaymentUrl;
                    $responseData['link'] = $flwPaymentUrl;
                    $responseData['link'] = $flwPaymentUrl;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to create Flutterwave tip payment link: {$e->getMessage()}");
            }
        }

        return [
            'success' => true,
            'message' => 'Tip payment initiated. Please complete gateway payment.',
            'data' => $responseData,
        ];
    }

    /**
     * Apply completed tip amount to order and rider earnings.
     */
    public function applyCompletedTipToOrder(Order $order, float $tipAmount, string $transactionId): array
    {
        $alreadyUsed = OrderAuditLog::where('order_id', $order->id)
            ->where('action', 'POST_ORDER_TIP_ADDED')
            ->where('new_value->transaction_id', $transactionId)
            ->exists();

        if ($alreadyUsed) {
            return [
                'success' => true,
                'message' => 'Rider tip has already been applied for this order.',
                'data' => [
                    'order' => $order->fresh(),
                    'added_tip' => $tipAmount,
                    'total_tip' => (float) $order->rider_tip_amount,
                    'transaction_id' => $transactionId,
                ],
            ];
        }

        $newTipAmount = (float) $order->rider_tip_amount + $tipAmount;
        $paymentMethod = is_callable($order->payment_method) ? $order->payment_method() : ($order->payment_method->value ?? $order->payment_method);
        $isCod = strtolower((string) $paymentMethod) === 'cod' || $paymentMethod === PaymentTypeEnum::COD();

        $orderUpdates = [
            'rider_tip_amount' => $newTipAmount,
        ];

        if (!$isCod) {
            $orderUpdates['final_total'] = (float) $order->final_total + $tipAmount;
            $orderUpdates['total_payable'] = (float) $order->total_payable + $tipAmount;
        }

        $order->update($orderUpdates);

        OrderPaymentTransaction::where('order_id', $order->id)
            ->where('payment_status', PaymentStatusEnum::PENDING())
            ->delete();

        $activeAssignment = DeliveryBoyAssignment::where('order_id', $order->id)
            ->whereNotIn('status', [
                DeliveryBoyAssignmentStatusEnum::CANCELED(),
                DeliveryBoyAssignmentStatusEnum::DROPPED(),
            ])
            ->first();

        if ($activeAssignment) {
            $activeAssignment->update([
                'total_earnings' => (float) ($activeAssignment->base_fee + $activeAssignment->per_store_pickup_fee + $activeAssignment->distance_based_fee + $activeAssignment->per_order_incentive + $newTipAmount),
            ]);
        }

        OrderAuditLog::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'action' => 'POST_ORDER_TIP_ADDED',
            'old_value' => ['tip_amount' => (float) $order->rider_tip_amount - $tipAmount],
            'new_value' => ['tip_amount' => $newTipAmount, 'transaction_id' => $transactionId],
        ]);

        return [
            'success' => true,
            'message' => 'Rider tip updated successfully.',
            'data' => [
                'order' => $order->fresh(),
                'added_tip' => $tipAmount,
                'total_tip' => $newTipAmount,
                'transaction_id' => $transactionId,
            ],
        ];
    }
}
