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
        Schema::table('stores', function (Blueprint $table) {
            // Master toggle to enable or disable scheduled delivery for this store
            $table->boolean('is_scheduled_delivery_enabled')->default(false)->after('allows_pickup');
            // Minimum buffer time (in minutes) required before a slot start time (default 60 mins)
            $table->unsignedInteger('scheduled_delivery_buffer_minutes')->default(60)->after('is_scheduled_delivery_enabled');
            // Maximum days in advance a customer can schedule an order (default 7 days)
            $table->unsignedInteger('max_advance_booking_days')->default(7)->after('scheduled_delivery_buffer_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'is_scheduled_delivery_enabled',
                'scheduled_delivery_buffer_minutes',
                'max_advance_booking_days',
            ]);
        });
    }
};
