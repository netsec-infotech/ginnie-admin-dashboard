<?php

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use App\Support\Currency\CurrencyWriteGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderPaymentTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'order_id',
        'user_id',
        'transaction_id',
        'amount',
        'currency',
        'order_currency_code',
        'gateway_currency_code',
        'gateway_amount',
        'gateway_exchange_rate',
        'payment_method',
        'payment_status',
        'message',
        'payment_details'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_amount' => 'decimal:2',
        'gateway_exchange_rate' => 'decimal:8',
        'payment_details' => 'json',
//        'payment_status' => PaymentStatusEnum::class
    ];

    /**
     * Get all possible payment statuses
     *
     * @return array
     */
    public static function getPaymentStatuses(): array
    {
        return PaymentStatusEnum::values();
    }

    /**
     * Get the order associated with the payment transaction
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who made the payment
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set Razorpay payment details
     *
     * @param string $orderId
     * @param string $paymentId
     * @param string $signature
     * @return void
     */
    public function setRazorpayDetails(string $orderId, string $paymentId, string $signature): void
    {
        $details = $this->payment_details ?? [];
        $details['razorpay_order_id'] = $orderId;
        $details['razorpay_payment_id'] = $paymentId;
        $details['razorpay_signature'] = $signature;
        $this->payment_details = $details;
    }

    public static function saveTransaction(array $data, string $paymentId, $paymentMethod, $paymentStatus)
    {
        $gatewayCurrency = app(CurrencyWriteGuard::class)->requireValid($data['currency'] ?? null, 'Payment transaction');

        return OrderPaymentTransaction::updateOrCreate(
            ['transaction_id' => $paymentId],
            [
                'uuid' => Str::uuid()->toString(),
                'order_id' => $data['order_id'] ?? null,
                'user_id' => $data['user_id'],
                'transaction_id' => $paymentId,
                'amount' => $data['amount'] / 100,
                'currency' => $gatewayCurrency,
                'order_currency_code' => self::resolveOrderCurrency($data['order_id'] ?? null),
                'gateway_currency_code' => $gatewayCurrency,
                'gateway_amount' => isset($data['amount']) ? ((float) $data['amount'] / 100) : null,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'message' => 'Payment Captured verification',
                'payment_details' => $data ?? null,
            ]
        );
    }

    public static function createTransaction(array $data, $paymentMethod, $paymentStatus)
    {
        $gatewayCurrency = app(CurrencyWriteGuard::class)->requireValid($data['currency'] ?? null, 'Payment transaction');

        return OrderPaymentTransaction::create([
                'uuid' => Str::uuid()->toString(),
                'order_id' => $data['order_id'] ?? null,
                'user_id' => $data['user_id'],
                'transaction_id' => $data['transaction_id'] ?? $data['payment_id'] ?? "",
                'amount' => $data['amount'],
                'currency' => $gatewayCurrency,
                'order_currency_code' => self::resolveOrderCurrency($data['order_id'] ?? null),
                'gateway_currency_code' => $gatewayCurrency,
                'gateway_amount' => $data['amount'] ?? null,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'message' => 'Payment Captured verification',
                'payment_details' => $data ?? null,
            ]
        );
    }

    private static function resolveOrderCurrency($orderId): ?string
    {
        if (empty($orderId)) {
            return null;
        }

        $currencyCode = Order::where('id', $orderId)->value('currency_code');

        return app(CurrencyWriteGuard::class)->nullableValid($currencyCode, 'Order');
    }
}
