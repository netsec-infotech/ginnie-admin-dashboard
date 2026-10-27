<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE language_translations MODIFY `key` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $hasCaseDuplicates = DB::table('language_translations')
            ->selectRaw('language_id, LOWER(`key`) AS normalized_key')
            ->groupBy('language_id', DB::raw('LOWER(`key`)'))
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasCaseDuplicates) {
            throw new RuntimeException('Cannot restore case-insensitive translation keys while case-distinct keys exist.');
        }

        DB::statement('ALTER TABLE language_translations MODIFY `key` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
