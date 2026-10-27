<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 3 — captures every admin override on an order so the timeline
     * stays auditable. Also gets used by the auto-escalation pipeline (3C)
     * for system-generated entries (admin_id null), and by the seller cancel
     * + rider drop flows once they're rolled into the unified audit stream.
     */
    public function up(): void
    {
        Schema::create('order_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            // Admin user who triggered the action. Nullable because system-generated
            // entries (escalation, scheduled jobs) have no human actor.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            // Action key — keep loose (string) so we can extend without a migration.
            // Conventional values: force_status, force_cancel, force_refund,
            // reassign_rider, add_note, escalation_flagged.
            $table->string('action');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_audit_logs');
    }
};
