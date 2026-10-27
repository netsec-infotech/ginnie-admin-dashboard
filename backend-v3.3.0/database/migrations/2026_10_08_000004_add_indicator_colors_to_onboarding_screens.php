<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_screens', function (Blueprint $table): void {
            if (! Schema::hasColumn('onboarding_screens', 'indicator_active_color')) {
                $table->string('indicator_active_color', 20)->default('#0d6efd')->after('button_foreground_color');
            }

            if (! Schema::hasColumn('onboarding_screens', 'indicator_inactive_color')) {
                $table->string('indicator_inactive_color', 20)->default('#dbe2ea')->after('indicator_active_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_screens', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('onboarding_screens', 'indicator_active_color') ? 'indicator_active_color' : null,
                Schema::hasColumn('onboarding_screens', 'indicator_inactive_color') ? 'indicator_inactive_color' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
