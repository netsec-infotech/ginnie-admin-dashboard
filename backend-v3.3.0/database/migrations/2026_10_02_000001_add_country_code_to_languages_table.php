<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->string('country_code', 10)->nullable()->after('native_name');
        });

        $mappings = [
            'en' => 'us',
            'es' => 'es',
            'fr' => 'fr',
            'de' => 'de',
            'zh' => 'cn',
            'hi' => 'in',
            'ar' => 'sa',
            'gu' => 'in',
            'te' => 'in',
        ];

        foreach ($mappings as $code => $countryCode) {
            DB::table('languages')
                ->where('code', $code)
                ->whereNull('country_code')
                ->update(['country_code' => $countryCode]);
        }
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
