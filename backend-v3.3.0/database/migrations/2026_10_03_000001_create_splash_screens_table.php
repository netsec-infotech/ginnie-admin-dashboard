<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splash_screens', function (Blueprint $table) {
            $table->id();
            $table->string('app_type', 30)->index();
            $table->string('slot', 30)->index();
            $table->string('version', 40);
            $table->string('media_type', 20);
            $table->unsignedTinyInteger('min_display_seconds')->default(2);
            $table->timestamp('activate_at')->nullable();
            $table->timestamps();

            $table->unique(['app_type', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splash_screens');
    }
};
