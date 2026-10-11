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
            $table->time('scheduled_slot_start_time')->nullable()->after('scheduled_time_slot_label');
            $table->time('scheduled_slot_end_time')->nullable()->after('scheduled_slot_start_time');
            $table->string('reschedule_status')->nullable()->after('scheduled_slot_end_time');
            $table->string('reschedule_reason')->nullable()->after('reschedule_status');
            $table->dateTime('reschedule_deadline')->nullable()->after('reschedule_reason');
            $table->dateTime('preparation_scheduled_at')->nullable()->after('reschedule_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_slot_start_time',
                'scheduled_slot_end_time',
                'reschedule_status',
                'reschedule_reason',
                'reschedule_deadline',
                'preparation_scheduled_at',
            ]);
        });
    }
};
