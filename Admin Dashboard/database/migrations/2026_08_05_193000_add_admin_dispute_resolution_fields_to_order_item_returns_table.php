<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ==========================================================
        // Return Dispute System
        // Phase 4
        // Migration for Admin Dispute Resolution Fields
        // ==========================================================
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'resolved_by_admin_at')) {
                $table->timestamp('resolved_by_admin_at')->nullable()->after('disputed_at');
            }
            if (!Schema::hasColumn('order_item_returns', 'resolved_by')) {
                $table->unsignedBigInteger('resolved_by')->nullable()->after('resolved_by_admin_at');
                $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_item_returns', 'admin_resolution')) {
                $table->string('admin_resolution', 20)->nullable()->after('resolved_by'); // 'customer', 'seller', 'rider'
            }
        });

        // Add classifications and rider tracking to platform expense records
        Schema::table('platform_expense_records', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_expense_records', 'delivery_boy_id')) {
                $table->unsignedBigInteger('delivery_boy_id')->nullable()->after('order_item_return_id');
                $table->foreign('delivery_boy_id')->references('id')->on('delivery_boys')->nullOnDelete();
            }
            if (!Schema::hasColumn('platform_expense_records', 'classification')) {
                $table->string('classification', 30)->nullable()->after('delivery_boy_id');
            }
        });

        // Update return_status enum to include 'rejected' in production databases (MySQL/PostgreSQL)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_item_returns MODIFY COLUMN return_status ENUM('cancelled', 'requested', 'seller_approved', 'seller_rejected', 'pickup_assigned', 'picked_up', 'received_by_seller', 'refund_processed', 'completed', 'rejected_at_store', 'disputed', 'rejected') DEFAULT 'requested'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ==========================================================
        // Return Dispute System
        // Phase 4
        // Rollback Admin Dispute Resolution Fields
        // ==========================================================
        Schema::table('platform_expense_records', function (Blueprint $table) {
            $table->dropForeign(['delivery_boy_id']);
            $table->dropColumn(['delivery_boy_id', 'classification']);
        });

        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['resolved_by_admin_at', 'resolved_by', 'admin_resolution']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_item_returns MODIFY COLUMN return_status ENUM('cancelled', 'requested', 'seller_approved', 'seller_rejected', 'pickup_assigned', 'picked_up', 'received_by_seller', 'refund_processed', 'completed', 'rejected_at_store', 'disputed') DEFAULT 'requested'");
        }
    }
};
