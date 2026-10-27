<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 1B: rider drop tracking + admin block.
     *
     * - drop_count drives the auto-flag threshold; is_flagged surfaces the rider
     *   on the admin reliability dashboard.
     * - is_blocked / blocked_at / blocked_reason / blocked_by_admin_id are set
     *   atomically by the admin block endpoint and gate the rider's login + new
     *   order acceptance. blocked_by_admin_id is a nullable FK to users.id with
     *   nullOnDelete so the audit trail survives admin user deletion.
     */
    public function up(): void
    {
        Schema::table('delivery_boys', function (Blueprint $table) {
            $table->unsignedInteger('drop_count')->default(0)->after('verification_remark');
            $table->boolean('is_flagged')->default(false)->after('drop_count');

            $table->boolean('is_blocked')->default(false)->after('is_flagged');
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
            $table->string('blocked_reason')->nullable()->after('blocked_at');
            $table->foreignId('blocked_by_admin_id')
                ->nullable()
                ->after('blocked_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_boys', function (Blueprint $table) {
            $table->dropForeign(['blocked_by_admin_id']);
            $table->dropColumn([
                'drop_count',
                'is_flagged',
                'is_blocked',
                'blocked_at',
                'blocked_reason',
                'blocked_by_admin_id',
            ]);
        });
    }
};
