<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS-3e: per-store UPI configuration for the in-store online QR option.
 *
 * Cashier shows a UPI QR (deeplink: upi://pay?…), customer pays from any
 * UPI app, cashier confirms in their own app, then taps "Mark as paid".
 * No payment-gateway integration required at this stage — a real-time
 * webhook layer can be added later as a per-PSP plugin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'pos_upi_vpa')) {
                $table->string('pos_upi_vpa', 100)->nullable()->after('receipt_template');
            }
            if (!Schema::hasColumn('stores', 'pos_upi_payee_name')) {
                $table->string('pos_upi_payee_name', 100)->nullable()->after('pos_upi_vpa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'pos_upi_payee_name')) {
                $table->dropColumn('pos_upi_payee_name');
            }
            if (Schema::hasColumn('stores', 'pos_upi_vpa')) {
                $table->dropColumn('pos_upi_vpa');
            }
        });
    }
};
