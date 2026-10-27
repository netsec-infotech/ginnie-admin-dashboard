<?php

use App\Enums\Order\OrderCreatedByEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS-1: Schema foundation for seller-side Point of Sale.
 *
 * Strictly additive. Existing customer order behaviour is unchanged because
 * the new `orders.created_by` column defaults to "customer" — every existing
 * row backfills correctly.
 *
 * Data seeding (walk-in placeholder user + pos_settings row) is handled
 * by PosFoundationSeeder, not this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'created_by')) {
                $table->enum('created_by', OrderCreatedByEnum::values())
                    ->default(OrderCreatedByEnum::CUSTOMER())
                    ->index()
                    ->after('user_id');
            }
            if (!Schema::hasColumn('orders', 'walkin_customer_name')) {
                $table->string('walkin_customer_name', 255)->nullable()->after('email');
            }
            if (!Schema::hasColumn('orders', 'walkin_customer_mobile')) {
                $table->string('walkin_customer_mobile', 32)->nullable()->after('walkin_customer_name');
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'receipt_template')) {
                $table->json('receipt_template')->nullable()->after('metadata');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'receipt_template')) {
                $table->dropColumn('receipt_template');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'walkin_customer_mobile')) {
                $table->dropColumn('walkin_customer_mobile');
            }
            if (Schema::hasColumn('orders', 'walkin_customer_name')) {
                $table->dropColumn('walkin_customer_name');
            }
            if (Schema::hasColumn('orders', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
