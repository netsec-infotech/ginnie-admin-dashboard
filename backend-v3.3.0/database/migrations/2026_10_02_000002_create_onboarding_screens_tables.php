<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_screen_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_type', 30)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_screens', function (Blueprint $table) {
            $table->id();
            $table->string('app_type', 30)->index();
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_screens');
        Schema::dropIfExists('onboarding_screen_settings');
    }
};
