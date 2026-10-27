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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('return_requires_otp')->default(false)->after('requires_otp');
            $table->boolean('return_requires_proof')->default(false)->after('return_requires_otp');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('return_requires_otp')->default(false)->after('requires_otp');
            $table->boolean('return_requires_proof')->default(false)->after('return_requires_otp');
        });

        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->string('pickup_otp', 6)->nullable()->after('pickup_status');
            $table->boolean('pickup_otp_verified')->default(false)->after('pickup_otp');
            $table->text('store_rejection_reason')->nullable()->after('seller_comment');
            $table->string('action_on_rejection')->nullable()->after('store_rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->dropColumn(['pickup_otp', 'pickup_otp_verified', 'store_rejection_reason', 'action_on_rejection']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['return_requires_otp', 'return_requires_proof']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['return_requires_otp', 'return_requires_proof']);
        });
    }
};
