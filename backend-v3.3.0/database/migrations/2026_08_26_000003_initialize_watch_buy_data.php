<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('watch_buy_profiles')->insertOrIgnore([
            'profile_type' => 'platform',
            'username' => 'hyperlocal',
            'bio' => 'Official Hyperlocal profile',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('settings')->insertOrIgnore([
            'variable' => 'watch_and_buy',
            'value' => json_encode([
                'enabled' => true,
                'title' => 'Watch & Buy',
                'maxDurationSeconds' => 60,
                'maxUploadMb' => 50,
                'maxProductsPerReel' => 10,
                'statusesEnabled' => true,
                'maxStatusDurationSeconds' => 15,
                'maxProductsPerStatus' => 5,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve administrator-configured settings during a rollback.
    }
};
