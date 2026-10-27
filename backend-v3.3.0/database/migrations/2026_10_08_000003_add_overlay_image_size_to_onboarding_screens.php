<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('onboarding_screens', 'overlay_image_size')) {
            return;
        }

        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $table->unsignedTinyInteger('overlay_image_size')->default(100)->after('overlay_image_position');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('onboarding_screens', 'overlay_image_size')) {
            return;
        }

        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $table->dropColumn('overlay_image_size');
        });
    }
};
