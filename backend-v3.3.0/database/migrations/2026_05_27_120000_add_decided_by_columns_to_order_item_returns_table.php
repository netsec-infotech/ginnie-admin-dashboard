<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            // Who closed the case (approve/reject) — seller or admin acting on
            // the seller's behalf. Null until a decision is made.
            $table->string('decided_by_type', 20)->nullable()->after('seller_comment');
            $table->unsignedBigInteger('decided_by_id')->nullable()->after('decided_by_type');
            $table->text('admin_comment')->nullable()->after('decided_by_id');
            $table->timestamp('admin_decided_at')->nullable()->after('seller_approved_at');

            $table->foreign('decided_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['decided_by_type', 'decided_by_id']);
        });
    }

    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->dropForeign(['decided_by_id']);
            $table->dropIndex(['decided_by_type', 'decided_by_id']);
            $table->dropColumn(['decided_by_type', 'decided_by_id', 'admin_comment', 'admin_decided_at']);
        });
    }
};
