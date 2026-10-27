<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'acceptance_timeout_snapshot')) {
                $table->json('acceptance_timeout_snapshot')->nullable()->after('delivery_otp_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'acceptance_timeout_snapshot')) {
                $table->dropColumn('acceptance_timeout_snapshot');
            }
        });
    }
};
