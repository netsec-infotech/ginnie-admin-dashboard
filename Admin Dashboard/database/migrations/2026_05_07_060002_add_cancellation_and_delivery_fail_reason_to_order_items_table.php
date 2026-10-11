<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 1B: capture the reason an item is cancelled or had its delivery
     * attempt fail. Read by the admin escalation dashboard and surfaced in
     * customer/seller order resources.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('cancellation_reason')->nullable()->after('status');
            $table->string('delivery_fail_reason')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'delivery_fail_reason']);
        });
    }
};
