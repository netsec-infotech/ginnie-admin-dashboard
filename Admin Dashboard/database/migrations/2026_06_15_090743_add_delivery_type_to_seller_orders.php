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
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('delivery_type', 16)
                ->default(\App\Enums\Order\OrderDeliveryTypeEnum::DELIVERY())
                ->after('seller_id');
            $table->timestamp('ready_for_pickup_at')->nullable()->after('delivery_type');
            $table->timestamp('picked_up_at')->nullable()->after('ready_for_pickup_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'ready_for_pickup_at', 'picked_up_at']);
        });
    }
};
