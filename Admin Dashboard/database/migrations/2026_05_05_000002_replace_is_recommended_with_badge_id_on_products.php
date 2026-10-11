<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_recommended');
            $table->foreignId('badge_id')->nullable()->after('featured')->constrained('badges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['badge_id']);
            $table->dropColumn('badge_id');
            $table->boolean('is_recommended')->default(false)->after('featured');
        });
    }
};
