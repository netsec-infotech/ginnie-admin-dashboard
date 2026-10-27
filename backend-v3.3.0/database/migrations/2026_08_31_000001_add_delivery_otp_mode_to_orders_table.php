<?php

use App\Enums\Order\DeliveryOtpModeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_otp_mode')) {
                $table->string('delivery_otp_mode', 20)->default(DeliveryOtpModeEnum::ITEM_WISE())->after('delivery_boy_id');
            }

            if (! Schema::hasColumn('orders', 'delivery_otp')) {
                $table->string('delivery_otp', 6)->nullable()->after('delivery_otp_mode');
            }

            if (! Schema::hasColumn('orders', 'delivery_otp_verified')) {
                $table->boolean('delivery_otp_verified')->default(false)->after('delivery_otp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('orders', 'delivery_otp_verified') ? 'delivery_otp_verified' : null,
                Schema::hasColumn('orders', 'delivery_otp') ? 'delivery_otp' : null,
                Schema::hasColumn('orders', 'delivery_otp_mode') ? 'delivery_otp_mode' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
