<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('meta_template_id')->nullable()->after('module');
            $table->string('category')->nullable()->after('status');
            $table->string('quality_score')->nullable()->after('category');
            $table->string('rejected_reason')->nullable()->after('quality_score');
            $table->json('components')->nullable()->after('rejected_reason');
            $table->timestamp('last_synced_at')->nullable()->after('components');
            $table->text('sync_error')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn([
                'meta_template_id',
                'category',
                'quality_score',
                'rejected_reason',
                'components',
                'last_synced_at',
                'sync_error',
            ]);
        });
    }
};
