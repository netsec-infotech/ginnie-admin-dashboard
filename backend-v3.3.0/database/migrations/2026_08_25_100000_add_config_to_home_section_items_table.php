<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_section_items', function (Blueprint $table) {
            $table->json('config')->nullable()->after('link_url');
        });
    }

    public function down(): void
    {
        Schema::table('home_section_items', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};
