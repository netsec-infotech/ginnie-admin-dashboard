<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track HOW the cashier returned the money on a POS refund.
 *
 *  refund_method     — short code: cash | upi | gateway | store_credit | other
 *  refund_method_meta — small JSON for free-form context (gateway txn id, etc.)
 *
 * Idempotent: skips if column already exists. Default 'cash' so historical
 * rows have a sane value (every prior POS refund was effectively manual cash
 * since v1 had no method capture).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_refunds', 'refund_method')) {
                $table->string('refund_method', 32)->default('cash')->after('total_amount');
            }
            if (!Schema::hasColumn('pos_refunds', 'refund_method_meta')) {
                $table->json('refund_method_meta')->nullable()->after('refund_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('pos_refunds', 'refund_method_meta')) {
                $table->dropColumn('refund_method_meta');
            }
            if (Schema::hasColumn('pos_refunds', 'refund_method')) {
                $table->dropColumn('refund_method');
            }
        });
    }
};
