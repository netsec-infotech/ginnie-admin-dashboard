<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->default('app'); // HomePagePlatformEnum: app | web
            $table->string('scope_type')->default('global'); // HomePageScopeEnum: global | category
            $table->unsignedBigInteger('scope_id')->nullable(); // Category ID when scope_type = category
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['platform', 'scope_type', 'scope_id'], 'home_layouts_platform_scope_unique');
            $table->index('platform', 'home_layouts_platform_index');
        });

        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_layout_id')->constrained('home_layouts')->cascadeOnDelete();
            $table->string('type'); // HomeSectionTypeEnum: hero | products | categories | brands | banners
            $table->string('title')->nullable();
            $table->string('style')->nullable(); // Primary visual preset
            $table->json('config')->nullable(); // JSON scalar settings
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['home_layout_id', 'sort_order'], 'home_sections_layout_sort_index');
            $table->index('type', 'home_sections_type_index');
        });

        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->string('purpose')->default('display'); // ItemPurposeEnum: display | filter
            $table->nullableMorphs('itemable'); // Product | Category | Brand; null = custom/banner-only
            $table->string('title')->nullable(); // Per-item title override
            $table->string('link_url')->nullable(); // Banner custom URL
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['home_section_id', 'purpose', 'sort_order'], 'home_section_items_section_purpose_sort_idx');
        });

        Schema::create('home_section_zone', function (Blueprint $table) {
            $table->unsignedBigInteger('home_section_id');
            $table->unsignedBigInteger('zone_id');
            $table->timestamps();

            $table->primary(['home_section_id', 'zone_id']);
            $table->index('zone_id', 'home_section_zone_zone_id_index');

            $table->foreign('home_section_id', 'home_section_zone_section_id_foreign')
                ->references('id')->on('home_sections')->cascadeOnDelete();
            $table->foreign('zone_id', 'home_section_zone_zone_id_foreign')
                ->references('id')->on('delivery_zones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_zone');
        Schema::dropIfExists('home_section_items');
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('home_layouts');
    }
};
