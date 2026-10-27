<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->string('operation', 20)->default('create')->after('uploadable_type');
            $table->string('match_field', 60)->nullable()->after('operation');
            $table->unsignedInteger('updated_count')->default(0)->after('success_count');
            $table->unsignedInteger('skipped_count')->default(0)->after('updated_count');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->dropColumn(['operation', 'match_field', 'updated_count', 'skipped_count']);
        });
    }
};
