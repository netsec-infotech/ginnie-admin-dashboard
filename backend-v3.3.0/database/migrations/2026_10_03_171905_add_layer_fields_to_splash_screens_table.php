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
        Schema::table('splash_screens', function (Blueprint $table) {
            $table->string('bg_type', 20)->default('color')->after('media_type');
            $table->string('bg_color', 9)->nullable()->after('bg_type');
            $table->boolean('overlay_enabled')->default(false)->after('activate_at');
            $table->string('overlay_type', 20)->nullable()->after('overlay_enabled');
            $table->decimal('overlay_x_percent', 5, 2)->default(50)->after('overlay_type');
            $table->decimal('overlay_y_percent', 5, 2)->default(45)->after('overlay_x_percent');
            $table->decimal('overlay_width_percent', 5, 2)->default(60)->after('overlay_y_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('splash_screens', function (Blueprint $table) {
            $table->dropColumn([
                'bg_type',
                'bg_color',
                'overlay_enabled',
                'overlay_type',
                'overlay_x_percent',
                'overlay_y_percent',
                'overlay_width_percent',
            ]);
        });
    }
};
