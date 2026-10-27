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
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'rider_tip_amount')) {
                $table->decimal('rider_tip_amount', 10, 2)->default(0.00)->after('user_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'rider_tip_amount')) {
                $table->decimal('rider_tip_amount', 10, 2)->default(0.00)->after('per_store_drop_off_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'rider_tip_amount')) {
                $table->dropColumn('rider_tip_amount');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'rider_tip_amount')) {
                $table->dropColumn('rider_tip_amount');
            }
        });
    }
};
