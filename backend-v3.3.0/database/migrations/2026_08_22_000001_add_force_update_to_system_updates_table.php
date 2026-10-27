<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_updates', function (Blueprint $table) {
            $table->boolean('force_update')->default(false)->after('min_supported_version');
        });
    }

    public function down(): void
    {
        Schema::table('system_updates', function (Blueprint $table) {
            $table->dropColumn('force_update');
        });
    }
};
