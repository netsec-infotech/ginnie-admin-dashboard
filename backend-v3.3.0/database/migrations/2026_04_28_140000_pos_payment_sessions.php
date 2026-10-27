<?php

use App\Enums\Pos\PosPaymentSessionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS Online Payment QR — session table.
 *
 * Cashier picks "Online payment" → backend creates a session row with the
 * frozen cart payload + a unique token. POS displays a QR encoding the
 * public payment URL `/pay/pos/{token}`. Customer scans, opens the page,
 * pays via Razorpay (or any future gateway). On confirmation we promote
 * the session to a real Order via PosOrderService.
 *
 * No Order is created until payment actually lands, so abandoned QR sessions
 * never pollute the orders table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();

            // Scoping
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('seller_id')->index();

            // Frozen cart snapshot — exact payload that PosOrderService::create
            // will receive once payment confirms. Keeps stock validation honest
            // (re-runs at confirm-time) but variant/addon/customer choices
            // can't drift between scan and pay.
            $table->json('payload');

            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 8)->default('INR');

            // pending → paid | failed | expired | cancelled
            $table->enum('status', PosPaymentSessionStatusEnum::values())
                ->default(PosPaymentSessionStatusEnum::PENDING())
                ->index();

            // Gateway specifics (nullable until customer picks a method on the
            // payment page).
            $table->string('gateway', 32)->nullable();             // 'razorpay' | 'stripe' | …
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('failure_reason', 500)->nullable();

            // Set when the session graduates into a real Order.
            $table->unsignedBigInteger('order_id')->nullable()->index();

            $table->timestamp('expires_at')->index();
            $table->timestamps();

            // FKs are intentionally soft (no cascade) — we want sessions to
            // outlive their order rows for audit purposes if a row is purged.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payment_sessions');
    }
};
