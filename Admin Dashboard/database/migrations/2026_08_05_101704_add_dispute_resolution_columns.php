<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ==========================================================
        // Return Dispute System
        // Phase 1
        // Database Migration for Dispute Resolution & Platform Expenses
        // ==========================================================
        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->string('pickup_condition')->nullable(); // 'good', 'damaged', 'opened_package'
            $table->text('pickup_remarks')->nullable();     // Rider remarks during pickup
            $table->string('dispute_reason')->nullable();   // Seller reason for escalation
            $table->timestamp('disputed_at')->nullable();   // Dispute start timestamp
            
            // Dispute outcome decision storage
            $table->boolean('refund_customer')->nullable();
            $table->string('loss_bearer')->nullable();       // 'seller', 'platform', 'customer'
            $table->boolean('pay_rider')->nullable();
        });

        Schema::create('platform_expense_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_return_id')->constrained('order_item_returns')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3);
            $table->string('reference_number')->unique();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ==========================================================
        // Return Dispute System
        // Phase 1
        // Database Migration for Dispute Resolution & Platform Expenses
        // ==========================================================
        Schema::dropIfExists('platform_expense_records');
        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_condition',
                'pickup_remarks',
                'dispute_reason',
                'disputed_at',
                'refund_customer',
                'loss_bearer',
                'pay_rider',
            ]);
        });
    }
};
