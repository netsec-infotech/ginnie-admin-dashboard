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
            $table->unsignedInteger('scheduled_delivery_slot_duration')->default(60)->after('max_advance_booking_days');
            $table->unsignedInteger('scheduled_delivery_slot_capacity')->default(10)->after('scheduled_delivery_slot_duration');
            $table->boolean('is_temporarily_closed')->default(false)->after('scheduled_delivery_slot_capacity');
            $table->string('temporary_closure_reason')->nullable()->after('is_temporarily_closed');
            $table->unsignedInteger('prep_concurrency')->default(2)->after('temporary_closure_reason');
            $table->unsignedInteger('reschedule_deadline_hours')->default(24)->after('prep_concurrency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_delivery_slot_duration',
                'scheduled_delivery_slot_capacity',
                'is_temporarily_closed',
                'temporary_closure_reason',
                'prep_concurrency',
                'reschedule_deadline_hours',
            ]);
        });
    }
};
