<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Phase 1B: extend the delivery_boy_assignments.status DB enum to include
     * 'dropped' so riders can voluntarily abandon an assignment.
     *
     * MySQL ALTER TABLE is the only portable way to extend an ENUM in place
     * without dropping/recreating the column.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE delivery_boy_assignments MODIFY COLUMN status ENUM('assigned','in_progress','completed','canceled','dropped') NOT NULL DEFAULT 'assigned'");
        }
    }

    public function down(): void
    {
        // Refuse to roll back if any row already uses the new value — silent
        // truncation would corrupt history.
        $hasDropped = DB::table('delivery_boy_assignments')->where('status', 'dropped')->exists();
        if ($hasDropped) {
            throw new \RuntimeException('Cannot drop the "dropped" status: delivery_boy_assignments rows are still using it.');
        }

        DB::statement("ALTER TABLE delivery_boy_assignments MODIFY COLUMN status ENUM('assigned','in_progress','completed','canceled') NOT NULL DEFAULT 'assigned'");
    }
};
