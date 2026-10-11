<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add rider_deduction_type field to order_item_returns table.
     */
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'rider_deduction_type')) {
                $table->string('rider_deduction_type')->nullable()->after('deduct_from_rider');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (Schema::hasColumn('order_item_returns', 'rider_deduction_type')) {
                $table->dropColumn('rider_deduction_type');
            }
        });
    }
};
