<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->index()->after('use_loyalty_points');
            $table->unsignedBigInteger('activity_version')->default(0)->after('last_activity_at');
            $table->timestamp('abandoned_cart_notified_at')->nullable()->after('activity_version');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn([
                'last_activity_at',
                'activity_version',
                'abandoned_cart_notified_at',
            ]);
        });
    }
};
