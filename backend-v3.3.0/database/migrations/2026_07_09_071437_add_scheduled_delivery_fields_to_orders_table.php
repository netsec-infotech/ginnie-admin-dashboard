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
            // Customer's chosen date for scheduled delivery (e.g., 2026-07-10)
            $table->date('scheduled_date')->nullable()->after('delivery_time_slot_id');
            // Snapshot of the display string (e.g., "10:00 - 12:00") at the time of order placement
            $table->string('scheduled_time_slot_label')->nullable()->after('scheduled_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_date',
                'scheduled_time_slot_label',
            ]);
        });
    }
};
