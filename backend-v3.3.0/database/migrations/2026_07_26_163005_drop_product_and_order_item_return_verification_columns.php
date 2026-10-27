<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Clean up database schema by dropping redundant return verification columns
     * from products and order_items tables.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'return_requires_otp')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['return_requires_otp', 'return_requires_proof']);
            });
        }

        if (Schema::hasColumn('order_items', 'return_requires_otp')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn(['return_requires_otp', 'return_requires_proof']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('return_requires_otp')->default(false);
            $table->boolean('return_requires_proof')->default(false);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('return_requires_otp')->default(false);
            $table->boolean('return_requires_proof')->default(false);
        });
    }
};
