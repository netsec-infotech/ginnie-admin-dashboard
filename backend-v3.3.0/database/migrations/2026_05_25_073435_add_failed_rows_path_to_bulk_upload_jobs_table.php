<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->string('failed_rows_path', 500)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->dropColumn('failed_rows_path');
        });
    }
};
