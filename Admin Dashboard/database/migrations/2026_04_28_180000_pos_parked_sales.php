<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS hold / park sale.
 *
 * Cashier hits "Hold" mid-sale — current cart + customer + discount get
 * frozen here so they can serve the next customer and resume later. No
 * Order is created until the cashier reaches checkout from a resumed sale.
 *
 * Stock is NOT reserved — a parked cart is just a snapshot. PosOrderService
 * re-validates stock at the actual checkout moment, exactly the same way
 * a fresh cart would be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_parked_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            // Cashier-set short label (e.g. "Customer in red shirt") — purely
            // for the resume picker so they can identify which is which.
            $table->string('label', 120)->nullable();
            $table->json('payload'); // { items[], customer_id?, walkin_*, discount_*, order_note? }
            $table->decimal('amount', 12, 2)->default(0); // pre-computed total at hold time, for the picker UI
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_parked_sales');
    }
};
