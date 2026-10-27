<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add return audit columns (return_id, balance_before, balance_after) to wallet_transactions.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'return_id')) {
                $table->unsignedBigInteger('return_id')->nullable()->after('store_id');
            }
            if (!Schema::hasColumn('wallet_transactions', 'balance_before')) {
                $table->decimal('balance_before', 10, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('wallet_transactions', 'balance_after')) {
                $table->decimal('balance_after', 10, 2)->nullable()->after('balance_before');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $cols = ['return_id', 'balance_before', 'balance_after'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('wallet_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
