<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_section_zone', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('featured_section_id');
            $table->unsignedBigInteger('zone_id');
            $table->foreign('featured_section_id', 'fs_zone_section_fk')
                ->references('id')->on('featured_sections')->cascadeOnDelete();
            $table->foreign('zone_id', 'fs_zone_zone_fk')
                ->references('id')->on('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['featured_section_id', 'zone_id'], 'fs_zone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_section_zone');
    }
};
