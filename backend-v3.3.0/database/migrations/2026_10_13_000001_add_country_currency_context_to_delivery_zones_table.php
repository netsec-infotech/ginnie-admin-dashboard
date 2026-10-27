<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_zones', 'country_id')) {
                $table->foreignId('country_id')->nullable()->after('slug')->constrained('countries')->nullOnDelete();
            }

            if (! Schema::hasColumn('delivery_zones', 'country_code')) {
                $table->string('country_code', 8)->nullable()->after('country_id');
            }

            if (! Schema::hasColumn('delivery_zones', 'currency_code')) {
                $table->string('currency_code', 3)->nullable()->after('country_code');
            }

            if (! Schema::hasColumn('delivery_zones', 'currency_symbol')) {
                $table->string('currency_symbol', 16)->nullable()->after('currency_code');
            }

            if (! Schema::hasColumn('delivery_zones', 'country_detection_status')) {
                $table->string('country_detection_status', 32)->nullable()->after('currency_symbol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            foreach (['country_detection_status', 'currency_symbol', 'currency_code', 'country_code'] as $column) {
                if (Schema::hasColumn('delivery_zones', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('delivery_zones', 'country_id')) {
                $table->dropConstrainedForeignId('country_id');
            }
        });
    }
};
