<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS refunds — cashier can reverse a recent sale (full or per-item, partial qty).
 *
 *  pos_refunds       — one row per refund event (an "occurrence" the cashier creates)
 *  pos_refund_lines  — child rows, one per order_item that was refunded in that event
 *
 * Why two tables: an order item can be partially refunded across multiple events
 * (e.g. cashier refunds 1 of 3, then later refunds another 1). Summing
 * pos_refund_lines.quantity per order_item_id gives the total refunded qty.
 * When that sum equals the order_item's original quantity, the item is fully
 * refunded and order_items.status flips to REFUNDED.
 *
 * Idempotent so the auto-updater can re-run it safely on a partial apply.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pos_refunds')) {
            Schema::create('pos_refunds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('refunded_by_user_id')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
                $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
                $table->foreign('refunded_by_user_id')->references('id')->on('users')->nullOnDelete();

                $table->index(['store_id', 'created_at']);
                $table->index('order_id');
            });
        }

        if (!Schema::hasTable('pos_refund_lines')) {
            Schema::create('pos_refund_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_refund_id');
                $table->unsignedBigInteger('order_item_id');
                $table->unsignedInteger('quantity');
                $table->decimal('amount', 12, 2)->default(0);
                $table->timestamps();

                $table->foreign('pos_refund_id')->references('id')->on('pos_refunds')->cascadeOnDelete();
                $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();

                $table->index('order_item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_refund_lines');
        Schema::dropIfExists('pos_refunds');
    }
};
