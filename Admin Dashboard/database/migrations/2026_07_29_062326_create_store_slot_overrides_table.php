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
        Schema::create('store_slot_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('override_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_blocked')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'override_date', 'start_time', 'end_time'], 'store_slot_override_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_slot_overrides');
    }
};
