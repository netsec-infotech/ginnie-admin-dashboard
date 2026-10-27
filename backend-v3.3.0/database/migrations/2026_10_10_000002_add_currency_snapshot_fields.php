<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'base_currency_code')) {
                $table->string('base_currency_code', 3)->nullable()->after('currency_code');
            }

            if (! Schema::hasColumn('orders', 'display_currency_code')) {
                $table->string('display_currency_code', 3)->nullable()->after('currency_rate');
            }

            if (! Schema::hasColumn('orders', 'display_exchange_rate')) {
                $table->decimal('display_exchange_rate', 18, 8)->nullable()->after('display_currency_code');
            }
        });

        Schema::table('order_payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('order_payment_transactions', 'order_currency_code')) {
                $table->string('order_currency_code', 3)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('order_payment_transactions', 'gateway_currency_code')) {
                $table->string('gateway_currency_code', 10)->nullable()->after('order_currency_code');
            }

            if (! Schema::hasColumn('order_payment_transactions', 'gateway_amount')) {
                $table->decimal('gateway_amount', 12, 2)->nullable()->after('gateway_currency_code');
            }

            if (! Schema::hasColumn('order_payment_transactions', 'gateway_exchange_rate')) {
                $table->decimal('gateway_exchange_rate', 18, 8)->nullable()->after('gateway_amount');
            }
        });

        Schema::table('pos_refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_refunds', 'currency_code')) {
                $table->string('currency_code', 3)->nullable()->after('total_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('pos_refunds', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
        });

        Schema::table('order_payment_transactions', function (Blueprint $table) {
            foreach (['gateway_exchange_rate', 'gateway_amount', 'gateway_currency_code', 'order_currency_code'] as $column) {
                if (Schema::hasColumn('order_payment_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['display_exchange_rate', 'display_currency_code', 'base_currency_code'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
