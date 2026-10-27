<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $table->string('background_type', 20)->default('solid')->after('subtitle');
            $table->string('background_color', 20)->default('#ffffff')->after('background_type');
            $table->string('gradient_start_color', 20)->default('#0d6efd')->after('background_color');
            $table->string('gradient_end_color', 20)->default('#20c997')->after('gradient_start_color');
            $table->unsignedSmallInteger('gradient_angle')->default(180)->after('gradient_end_color');
            $table->string('background_image_fit', 20)->default('cover')->after('gradient_angle');
            $table->string('background_image_position', 20)->default('center')->after('background_image_fit');
            $table->boolean('overlay_enabled')->default(false)->after('background_image_position');
            $table->string('overlay_color', 20)->default('#000000')->after('overlay_enabled');
            $table->decimal('overlay_opacity', 4, 2)->default(0)->after('overlay_color');
            $table->string('title_color', 20)->default('#111827')->after('overlay_opacity');
            $table->string('subtitle_color', 20)->default('#6b7280')->after('title_color');
            $table->string('content_alignment', 20)->default('center')->after('subtitle_color');
            $table->string('media_fit', 20)->default('contain')->after('content_alignment');
            $table->string('button_background_color', 20)->default('#0d6efd')->after('media_fit');
            $table->string('button_foreground_color', 20)->default('#ffffff')->after('button_background_color');
            $table->string('indicator_active_color', 20)->default('#0d6efd')->after('button_foreground_color');
            $table->string('indicator_inactive_color', 20)->default('#dbe2ea')->after('indicator_active_color');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $table->dropColumn([
                'background_type',
                'background_color',
                'gradient_start_color',
                'gradient_end_color',
                'gradient_angle',
                'background_image_fit',
                'background_image_position',
                'overlay_enabled',
                'overlay_color',
                'overlay_opacity',
                'title_color',
                'subtitle_color',
                'content_alignment',
                'media_fit',
                'button_background_color',
                'button_foreground_color',
                'indicator_active_color',
                'indicator_inactive_color',
            ]);
        });
    }
};
