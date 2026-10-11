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
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_id')->nullable()->after('scope_id');
            $table->dropUnique('home_layouts_platform_scope_unique');
            $table->unique(['platform', 'scope_type', 'scope_id', 'zone_id'], 'home_layouts_platform_scope_zone_unique');
            $table->index('zone_id', 'home_layouts_zone_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->dropUnique('home_layouts_platform_scope_zone_unique');
            $table->dropIndex('home_layouts_zone_id_index');
            $table->dropColumn('zone_id');
            $table->unique(['platform', 'scope_type', 'scope_id'], 'home_layouts_platform_scope_unique');
        });
    }
};
