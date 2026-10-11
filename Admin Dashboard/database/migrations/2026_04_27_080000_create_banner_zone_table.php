<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_zone', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('banner_id');
            $table->unsignedBigInteger('zone_id');
            $table->foreign('banner_id')->references('id')->on('banners')->cascadeOnDelete();
            $table->foreign('zone_id')->references('id')->on('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['banner_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_zone');
    }
};
