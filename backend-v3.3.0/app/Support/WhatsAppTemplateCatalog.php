<?php

namespace App\Support;

class WhatsAppTemplateCatalog
{
    public const CUSTOMER_ORDER_PLACED = 'customer_order_placed';
    public const CUSTOMER_ORDER_DELIVERED_INVOICE = 'customer_order_delivered_invoice';
    public const CUSTOMER_ABANDONED_CART = 'customer_abandoned_cart';
    public const CUSTOMER_BACK_IN_STOCK = 'customer_back_in_stock';
    public const SELLER_ACCOUNT_VERIFICATION_STATUS = 'seller_account_verification_status';
    public const SELLER_NEW_ORDER_RECEIVED = 'seller_new_order_received';
    public const SELLER_STORE_VERIFICATION_STATUS = 'seller_store_verification_status';
    public const SELLER_SCHEDULED_ORDER = 'seller_scheduled_order';
    public const RIDER_ACCOUNT_VERIFICATION_STATUS = 'rider_account_verification_status';
    public const RIDER_ORDER_COMPLETED_EARNING = 'rider_order_completed_earning';
    public const RIDER_WITHDRAWAL_STATUS = 'rider_withdrawal_status';
    public const RIDER_CASH_LIMIT_REACHED = 'rider_cash_limit_reached';

    public const PARAMETER_FORMAT_POSITIONAL = 'positional';
    public const PARAMETER_FORMAT_NAMED = 'named';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAUSED = 'paused';

    public static function modules(): array
    {
        return [
            self::CUSTOMER_ORDER_PLACED => [
                'audience' => 'Customer',
                'label' => 'Order placed',
                'variables' => ['customer_name', 'order_number', 'total_amount', 'order_date'],
            ],
            self::CUSTOMER_ORDER_DELIVERED_INVOICE => [
                'audience' => 'Customer',
                'label' => 'Order delivered and invoice',
                'variables' => ['customer_name', 'order_number', 'total_amount', 'invoice_url'],
            ],
            self::CUSTOMER_ABANDONED_CART => [
                'audience' => 'Customer',
                'label' => 'Abandoned cart',
                'variables' => ['customer_name', 'cart_total', 'cart_url'],
            ],
            self::CUSTOMER_BACK_IN_STOCK => [
                'audience' => 'Customer',
                'label' => 'Notify me when in stock',
                'variables' => ['customer_name', 'product_name', 'product_url'],
            ],
            self::SELLER_ACCOUNT_VERIFICATION_STATUS => [
                'audience' => 'Seller',
                'label' => 'Account verification status',
                'variables' => ['seller_name', 'verification_status', 'remarks'],
            ],
            self::SELLER_NEW_ORDER_RECEIVED => [
                'audience' => 'Seller',
                'label' => 'New order received',
                'variables' => ['seller_name', 'order_number', 'store_name', 'total_amount'],
            ],
            self::SELLER_STORE_VERIFICATION_STATUS => [
                'audience' => 'Seller',
                'label' => 'Store verification status',
                'variables' => ['seller_name', 'store_name', 'verification_status', 'remarks'],
            ],
            self::SELLER_SCHEDULED_ORDER => [
                'audience' => 'Seller',
                'label' => 'Scheduled order',
                'variables' => ['seller_name', 'order_number', 'scheduled_date', 'scheduled_time'],
            ],
            self::RIDER_ACCOUNT_VERIFICATION_STATUS => [
                'audience' => 'Rider',
                'label' => 'Account verification status',
                'variables' => ['rider_name', 'verification_status', 'remarks'],
            ],
            self::RIDER_ORDER_COMPLETED_EARNING => [
                'audience' => 'Rider',
                'label' => 'Order completed and earning',
                'variables' => ['rider_name', 'order_number', 'earning_amount', 'completed_at'],
            ],
            self::RIDER_WITHDRAWAL_STATUS => [
                'audience' => 'Rider',
                'label' => 'Withdrawal request verification status',
                'variables' => ['rider_name', 'withdrawal_amount', 'withdrawal_status', 'remarks'],
            ],
            self::RIDER_CASH_LIMIT_REACHED => [
                'audience' => 'Rider',
                'label' => 'Cash limit reached',
                'variables' => ['rider_name', 'current_cash_amount', 'cash_limit'],
            ],
        ];
    }

    public static function moduleKeys(): array
    {
        return array_keys(self::modules());
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_PAUSED,
        ];
    }

    public static function parameterFormats(): array
    {
        return [
            self::PARAMETER_FORMAT_POSITIONAL,
            self::PARAMETER_FORMAT_NAMED,
        ];
    }

    public static function defaultVariables(string $module, string $format = self::PARAMETER_FORMAT_POSITIONAL): array
    {
        $variables = self::modules()[$module]['variables'] ?? [];

        if ($format === self::PARAMETER_FORMAT_NAMED) {
            return array_combine($variables, $variables) ?: [];
        }

        return collect($variables)
            ->values()
            ->mapWithKeys(fn (string $variable, int $index) => [(string) ($index + 1) => $variable])
            ->all();
    }

    public static function sampleData(string $module): array
    {
        return collect(self::modules()[$module]['variables'] ?? [])
            ->mapWithKeys(fn (string $variable) => [$variable => self::sampleValue($variable)])
            ->all();
    }

    protected static function sampleValue(string $variable): string
    {
        return match ($variable) {
            'customer_name' => 'John Customer',
            'seller_name' => 'Demo Seller',
            'rider_name' => 'Demo Rider',
            'order_number' => 'ORD-1001',
            'total_amount', 'cart_total' => '100.00',
            'order_date' => now()->toDateString(),
            'invoice_url', 'cart_url', 'product_url' => url('/'),
            'product_name' => 'Sample Product',
            'verification_status', 'withdrawal_status' => 'Approved',
            'store_name' => 'Demo Store',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00 AM',
            'earning_amount' => '25.00',
            'completed_at' => now()->toDayDateTimeString(),
            'withdrawal_amount' => '50.00',
            'current_cash_amount' => '500.00',
            'cash_limit' => '450.00',
            'remarks' => 'Test message',
            default => 'Sample',
        };
    }
}
