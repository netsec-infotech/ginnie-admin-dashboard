<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split tender — cash + online QR for a single bill.
 *
 * `pos_payment_sessions.cash_portion` stores the cash amount the cashier
 * physically collected; the session's `amount` column stays as the ONLINE
 * portion only (because that's what we hand to Razorpay). When the online
 * portion confirms, PosOrderService records both halves on the order.
 *
 * `orders.pos_split_cash` is the receipt-side breakdown — total = pos_split_cash
 * + (final_total - pos_split_cash) [the online portion].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_payment_sessions', 'cash_portion')) {
                $table->decimal('cash_portion', 12, 2)->default(0)->after('amount');
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pos_split_cash')) {
                $table->decimal('pos_split_cash', 12, 2)->default(0)->after('pos_savings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('pos_payment_sessions', 'cash_portion')) {
                $table->dropColumn('cash_portion');
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pos_split_cash')) {
                $table->dropColumn('pos_split_cash');
            }
        });
    }
};
