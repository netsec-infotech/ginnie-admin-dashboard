<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('product_variant_attributes', 'product_id')) {
            return;
        }

        // First add the column as nullable
        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('id');
        });

        DB::table('product_variant_attributes')->update([
            'product_id' => DB::raw('(select product_id from product_variants where product_variants.id = product_variant_attributes.product_variant_id)'),
        ]);

        // Make the column non-nullable and add the foreign key constraint
        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('product_variant_attributes', 'product_id')) {
            return;
        }

        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
