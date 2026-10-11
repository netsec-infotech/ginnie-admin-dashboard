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
        // 1. Create delivery_boy_online_settlements table
        Schema::create('delivery_boy_online_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('delivery_boy_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_gateway')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('delivery_boy_id', 'fk_dbos_delivery_boy')
                ->references('id')->on('delivery_boys')->onDelete('cascade');
        });

        // 2. Create delivery_boy_online_settlement_assignments table
        Schema::create('delivery_boy_online_settlement_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_settlement_id');
            $table->unsignedBigInteger('delivery_boy_assignment_id');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->foreign('online_settlement_id', 'fk_dbosa_settlement')
                ->references('id')->on('delivery_boy_online_settlements')->onDelete('cascade');
            $table->foreign('delivery_boy_assignment_id', 'fk_dbosa_assignment')
                ->references('id')->on('delivery_boy_assignments')->onDelete('cascade');
        });

        // 3. Add columns to delivery_boy_cash_transactions table
        Schema::table('delivery_boy_cash_transactions', function (Blueprint $table) {
            // [CHANGE] Added fields for tracking manual vs online payment channel and gateway
            $table->string('payment_channel')->default('manual')->after('transaction_type'); // manual, online
            $table->string('payment_gateway')->nullable()->after('payment_channel');
            $table->unsignedBigInteger('online_settlement_id')->nullable()->after('payment_gateway');

            $table->foreign('online_settlement_id', 'fk_dbct_settlement')
                ->references('id')->on('delivery_boy_online_settlements')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_boy_cash_transactions', function (Blueprint $table) {
            $table->dropForeign('fk_dbct_settlement');
            $table->dropColumn(['payment_channel', 'payment_gateway', 'online_settlement_id']);
        });

        Schema::dropIfExists('delivery_boy_online_settlement_assignments');
        Schema::dropIfExists('delivery_boy_online_settlements');
    }
};
