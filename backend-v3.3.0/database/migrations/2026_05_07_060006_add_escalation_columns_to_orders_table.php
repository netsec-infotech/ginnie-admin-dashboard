<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 3C — auto-escalation. is_flagged + escalation_reasons surface stuck
     * orders on the admin dashboard. Filled by OrderEscalationService.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false)->after('status');
            $table->json('escalation_reasons')->nullable()->after('is_flagged');
            $table->timestamp('escalated_at')->nullable()->after('escalation_reasons');

            $table->index('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['is_flagged']);
            $table->dropColumn(['is_flagged', 'escalation_reasons', 'escalated_at']);
        });
    }
};
