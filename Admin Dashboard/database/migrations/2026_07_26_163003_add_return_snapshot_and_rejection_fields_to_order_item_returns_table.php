<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add snapshot and rejection/responsible party fields to order_item_returns.
     */
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'return_reason_id')) {
                $table->unsignedBigInteger('return_reason_id')->nullable()->after('reason');
            }
            if (!Schema::hasColumn('order_item_returns', 'return_reason_name')) {
                $table->string('return_reason_name')->nullable()->after('return_reason_id');
            }
            if (!Schema::hasColumn('order_item_returns', 'snapshot_otp_required')) {
                $table->boolean('snapshot_otp_required')->default(false)->after('return_reason_name');
            }
            if (!Schema::hasColumn('order_item_returns', 'snapshot_proof_required')) {
                $table->boolean('snapshot_proof_required')->default(false)->after('snapshot_otp_required');
            }
            if (!Schema::hasColumn('order_item_returns', 'rejection_reason_id')) {
                $table->unsignedBigInteger('rejection_reason_id')->nullable()->after('store_rejection_reason');
            }
            if (!Schema::hasColumn('order_item_returns', 'rejection_reason_name')) {
                $table->string('rejection_reason_name')->nullable()->after('rejection_reason_id');
            }
            if (!Schema::hasColumn('order_item_returns', 'responsible_party')) {
                $table->enum('responsible_party', ['customer', 'rider'])->nullable()->after('rejection_reason_name');
            }
            if (!Schema::hasColumn('order_item_returns', 'deduct_from_rider')) {
                $table->boolean('deduct_from_rider')->default(false)->after('responsible_party');
            }
            if (!Schema::hasColumn('order_item_returns', 'deduction_amount')) {
                $table->decimal('deduction_amount', 10, 2)->nullable()->after('deduct_from_rider');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            $cols = [
                'return_reason_id',
                'return_reason_name',
                'snapshot_otp_required',
                'snapshot_proof_required',
                'rejection_reason_id',
                'rejection_reason_name',
                'responsible_party',
                'deduct_from_rider',
                'deduction_amount',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('order_item_returns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
