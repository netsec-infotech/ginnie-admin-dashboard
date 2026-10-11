<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Extend the delivery_boy_assignments DB enums to support the new
     * admin-driven cancellation + settlement flow:
     *
     *   status:         add 'cancelled_by_admin'
     *   payment_status: add 'rejected'
     *
     * MySQL ENUM extension is done in place via ALTER TABLE — same pattern
     * the 'dropped' status migration used.
     */
    public function up(): void
    {
        Schema::table('delivery_boy_assignments', function (Blueprint $table) {
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'canceled', 'dropped', 'cancelled_by_admin'])->change();
            $table->enum('payment_status', ['pending', 'paid', 'rejected'])->change();
        });
    }

    public function down(): void
    {
        $usingNewStatus = DB::table('delivery_boy_assignments')
            ->where('status', 'cancelled_by_admin')
            ->exists();

        if ($usingNewStatus) {
            throw new RuntimeException(
                'Cannot drop the "cancelled_by_admin" status: rows are still using it.'
            );
        }

        $usingNewPaymentStatus = DB::table('delivery_boy_assignments')
            ->where('payment_status', 'rejected')
            ->exists();

        if ($usingNewPaymentStatus) {
            throw new RuntimeException(
                'Cannot drop the "rejected" payment_status: rows are still using it.'
            );
        }

        Schema::table('delivery_boy_assignments', function (Blueprint $table) {
            $table->enum('status', [
                'assigned',
                'in_progress',
                'completed',
                'canceled',
                'dropped'
            ])->change();

            $table->enum('payment_status', [
                'pending',
                'paid'
            ])->change();
        });
    }
};
