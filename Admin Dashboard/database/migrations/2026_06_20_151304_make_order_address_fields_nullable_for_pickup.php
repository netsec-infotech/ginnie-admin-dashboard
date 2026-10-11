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
            $table->text('billing_address_1')->nullable()->change();
            $table->string('billing_landmark')->nullable()->change();
            $table->string('billing_zip', 20)->nullable()->change();
            $table->string('billing_phone', 20)->nullable()->change();
            $table->decimal('billing_latitude', 10, 8)->nullable()->change();
            $table->decimal('billing_longitude', 11, 8)->nullable()->change();
            $table->string('billing_city')->nullable()->change();
            $table->string('billing_state')->nullable()->change();
            $table->string('billing_country')->nullable()->change();
            $table->string('billing_country_code', 3)->nullable()->change();

            $table->text('shipping_address_1')->nullable()->change();
            $table->string('shipping_landmark')->nullable()->change();
            $table->string('shipping_zip', 20)->nullable()->change();
            $table->string('shipping_phone', 20)->nullable()->change();
            $table->decimal('shipping_latitude', 10, 8)->nullable()->change();
            $table->decimal('shipping_longitude', 11, 8)->nullable()->change();
            $table->string('shipping_city')->nullable()->change();
            $table->string('shipping_state')->nullable()->change();
            $table->string('shipping_country')->nullable()->change();
            $table->string('shipping_country_code', 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('billing_address_1')->nullable(false)->change();
            $table->string('billing_landmark')->nullable(false)->change();
            $table->string('billing_zip', 20)->nullable(false)->change();
            $table->string('billing_phone', 20)->nullable(false)->change();
            $table->decimal('billing_latitude', 10, 8)->nullable(false)->change();
            $table->decimal('billing_longitude', 11, 8)->nullable(false)->change();
            $table->string('billing_city')->nullable(false)->change();
            $table->string('billing_state')->nullable(false)->change();
            $table->string('billing_country')->nullable(false)->change();
            $table->string('billing_country_code', 3)->nullable(false)->change();

            $table->text('shipping_address_1')->nullable(false)->change();
            $table->string('shipping_landmark')->nullable(false)->change();
            $table->string('shipping_zip', 20)->nullable(false)->change();
            $table->string('shipping_phone', 20)->nullable(false)->change();
            $table->decimal('shipping_latitude', 10, 8)->nullable(false)->change();
            $table->decimal('shipping_longitude', 11, 8)->nullable(false)->change();
            $table->string('shipping_city')->nullable(false)->change();
            $table->string('shipping_state')->nullable(false)->change();
            $table->string('shipping_country')->nullable(false)->change();
            $table->string('shipping_country_code', 3)->nullable(false)->change();
        });
    }
};
