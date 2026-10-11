<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS pricing breakdown — store the special-price savings total on the
 * order itself so the receipt can show "you saved ₹X" without re-deriving
 * the regular prices at print time.
 *
 * Default 0 keeps customer-web orders unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pos_savings')) {
                $table->decimal('pos_savings', 12, 2)->default(0)->after('promo_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pos_savings')) {
                $table->dropColumn('pos_savings');
            }
        });
    }
};
