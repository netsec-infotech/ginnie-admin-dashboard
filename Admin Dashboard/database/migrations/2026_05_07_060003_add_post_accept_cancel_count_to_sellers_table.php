<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 1B: counter that increments whenever a seller cancels an item they
     * had already accepted. Feeds the admin reliability dashboard and the
     * auto-escalation rules in Phase 3.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->unsignedInteger('post_accept_cancel_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('post_accept_cancel_count');
        });
    }
};
