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
        if (! Schema::hasTable('bulk_upload_jobs')) {
            return;
        }

        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('bulk_upload_jobs', 'target_locale')) {
                $table->string('target_locale', 10)->nullable()->after('operation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bulk_upload_jobs')) {
            return;
        }

        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('bulk_upload_jobs', 'target_locale')) {
                $table->dropColumn('target_locale');
            }
        });
    }
};
