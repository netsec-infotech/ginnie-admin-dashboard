<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to make seller_id nullable on global_product_attributes table.
     */
    public function up(): void
    {
        Schema::table('global_product_attributes', function (Blueprint $table) {
            // Change seller_id column to be nullable for system-wide admin global attributes
            $table->unsignedBigInteger('seller_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_product_attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id')->nullable(false)->change();
        });
    }
};
