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
        Schema::create('home_navbar_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('app_sort_order')->default(0);
            $table->unsignedInteger('web_sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_app')->default(true);
            $table->boolean('show_on_web')->default(true);
            $table->json('home_appearance')->nullable();
            $table->json('search_labels')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['is_active', 'app_sort_order'], 'home_nav_items_active_app_sort_idx');
            $table->index(['is_active', 'web_sort_order'], 'home_nav_items_active_web_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_navbar_items');
    }
};
