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
        if (! Schema::hasColumn('stores', 'delivery_zone_id')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['delivery_zone_id']);
            $table->dropColumn('delivery_zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stores', 'delivery_zone_id')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_zone_id')->nullable()->after('seller_id');
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->onDelete('set null');
        });
    }
};
