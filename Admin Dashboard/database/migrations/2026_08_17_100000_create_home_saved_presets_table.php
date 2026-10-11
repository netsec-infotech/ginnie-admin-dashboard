<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_saved_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // hero | products | categories | brands | banners
            $table->string('style')->nullable();
            $table->json('config')->nullable();
            $table->json('items')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('type', 'home_saved_presets_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_saved_presets');
    }
};
