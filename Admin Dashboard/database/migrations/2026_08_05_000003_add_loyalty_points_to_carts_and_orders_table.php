<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->boolean('use_loyalty_points')->default(false)->after('rider_tip_amount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('rider_tip_amount');
            $table->decimal('loyalty_points_discount_amount', 10, 2)->default(0.00)->after('loyalty_points_redeemed');
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('loyalty_points_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['use_loyalty_points']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_points_redeemed',
                'loyalty_points_discount_amount',
                'loyalty_points_earned',
            ]);
        });
    }
};
