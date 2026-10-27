<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->boolean('notify_on_finish')->default(false)->after('match_field');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->dropColumn('notify_on_finish');
        });
    }
};
