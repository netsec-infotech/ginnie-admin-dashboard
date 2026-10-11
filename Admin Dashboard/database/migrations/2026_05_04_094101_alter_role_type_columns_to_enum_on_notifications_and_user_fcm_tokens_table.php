<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'role_type')) {
            DB::table('notifications')
                ->where('role_type', 'unknown')
                ->update(['role_type' => null]);
        }

        if (Schema::hasTable('user_fcm_tokens') && Schema::hasColumn('user_fcm_tokens', 'role_type')) {
            DB::table('user_fcm_tokens')
                ->where('role_type', 'unknown')
                ->update(['role_type' => null]);
        }

        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE `notifications`
            MODIFY `role_type` ENUM('admin', 'seller', 'customer', 'rider') NULL
        ");

        DB::statement("
            ALTER TABLE `user_fcm_tokens`
            MODIFY `role_type` ENUM('admin', 'seller', 'customer', 'rider') NULL
        ");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement('
            ALTER TABLE `notifications`
            MODIFY `role_type` VARCHAR(32) NULL
        ');

        DB::statement('
            ALTER TABLE `user_fcm_tokens`
            MODIFY `role_type` VARCHAR(32) NULL
        ');
    }
};
