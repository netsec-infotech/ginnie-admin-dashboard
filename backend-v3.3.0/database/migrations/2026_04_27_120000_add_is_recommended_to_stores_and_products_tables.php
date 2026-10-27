<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_recommended')->default(false)->after('visibility_status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_recommended')->default(false)->after('featured');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_recommended');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_recommended');
        });
    }
};
