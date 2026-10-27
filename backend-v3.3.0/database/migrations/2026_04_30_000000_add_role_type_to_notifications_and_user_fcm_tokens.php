<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_fcm_tokens') && ! Schema::hasColumn('user_fcm_tokens', 'role_type')) {
            Schema::table('user_fcm_tokens', function (Blueprint $table) {
                $table->string('role_type', 32)->nullable()->after('device_type');
                $table->index('role_type');
            });
        }

        if (Schema::hasTable('notifications') && ! Schema::hasColumn('notifications', 'role_type')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('role_type', 32)->nullable()->after('type');
                $table->index('role_type');
            });
        }

        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'role_type')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::table('notifications')
                    ->whereNull('role_type')
                    ->update([
                        'role_type' => DB::raw("
                            CASE json_extract(`data`, '$.sent_to')
                                WHEN 'admin' THEN 'admin'
                                WHEN 'customer' THEN 'customer'
                                WHEN 'user' THEN 'customer'
                                WHEN 'seller' THEN 'seller'
                                WHEN 'delivery_boy' THEN 'rider'
                                WHEN 'rider' THEN 'rider'
                                ELSE NULL
                            END
                        "),
                    ]);
            } else {
                DB::table('notifications')
                    ->whereNull('role_type')
                    ->update([
                        'role_type' => DB::raw("
                            CASE JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.sent_to'))
                                WHEN 'admin' THEN 'admin'
                                WHEN 'customer' THEN 'customer'
                                WHEN 'user' THEN 'customer'
                                WHEN 'seller' THEN 'seller'
                                WHEN 'delivery_boy' THEN 'rider'
                                WHEN 'rider' THEN 'rider'
                                ELSE NULL
                            END
                        "),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'role_type')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex(['role_type']);
                $table->dropColumn('role_type');
            });
        }

        if (Schema::hasTable('user_fcm_tokens') && Schema::hasColumn('user_fcm_tokens', 'role_type')) {
            Schema::table('user_fcm_tokens', function (Blueprint $table) {
                $table->dropIndex(['role_type']);
                $table->dropColumn('role_type');
            });
        }
    }
};
