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
        Schema::table('orders', function (Blueprint $table) {
            // Flag to track if a COD order was paid online at the customer's doorstep via delivery boy QR/link
            $table->boolean('is_cod_paid_online')->default(false)->after('payment_status');
            // Stores the name of the online gateway used for doorstep payment (e.g., razorpay, stripe, paystack, flutterwave)
            $table->string('cod_online_gateway')->nullable()->after('is_cod_paid_online');
            // Stores temporary gateway order/intent ID or reference while doorstep payment is in progress or completed
            $table->string('cod_online_reference')->nullable()->after('cod_online_gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'is_cod_paid_online',
                'cod_online_gateway',
                'cod_online_reference',
            ]);
        });
    }
};
