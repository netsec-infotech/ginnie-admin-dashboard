<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE subscription_plan_limits MODIFY COLUMN `key` ENUM('store_limit','product_limit','role_limit','system_user_limit','variation_product_limit','pos_access') NOT NULL");
            DB::statement("ALTER TABLE seller_subscription_usages MODIFY COLUMN `key` ENUM('store_limit','product_limit','role_limit','system_user_limit','variation_product_limit','pos_access') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscription_plan_limits MODIFY COLUMN `key` ENUM('store_limit','product_limit','role_limit','system_user_limit','variation_product_limit') NOT NULL");
        DB::statement("ALTER TABLE seller_subscription_usages MODIFY COLUMN `key` ENUM('store_limit','product_limit','role_limit','system_user_limit','variation_product_limit') NOT NULL");
    }
};
