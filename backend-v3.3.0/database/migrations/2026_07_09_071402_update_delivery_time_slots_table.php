<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('delivery_time_slots')) {
            Schema::table('delivery_time_slots', function (Blueprint $table) {
                if (!Schema::hasColumn('delivery_time_slots', 'label')) {
                    $table->string('label', 100)->nullable()->after('store_id');
                }
                if (!Schema::hasColumn('delivery_time_slots', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('is_active');
                }
            });

            try {
                Schema::table('delivery_time_slots', function (Blueprint $table) {
                    $table->time('start_time')->nullable()->change();
                    $table->time('end_time')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Ignore if change() fails on MySQL timestamp conversion or missing DBAL
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_time_slots', function (Blueprint $table) {
            $table->dropColumn(['label', 'sort_order']);
            $table->timestamp('start_time')->nullable()->change();
            $table->timestamp('end_time')->nullable()->change();
        });
    }
};
