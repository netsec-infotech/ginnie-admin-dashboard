<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $table->string('overlay_type', 20)->default('color')->after('overlay_enabled');
            $table->string('overlay_image_fit', 20)->default('cover')->after('overlay_opacity');
            $table->string('overlay_image_position', 20)->default('center')->after('overlay_image_fit');
            $table->unsignedTinyInteger('overlay_image_size')->default(100)->after('overlay_image_position');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $table->dropColumn([
                'overlay_type',
                'overlay_image_fit',
                'overlay_image_position',
                'overlay_image_size',
            ]);
        });
    }
};
