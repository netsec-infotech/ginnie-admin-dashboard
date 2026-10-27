<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currency_metadata')) {
            Schema::create('currency_metadata', function (Blueprint $table) {
                $table->string('code', 3)->primary();
                $table->string('name')->nullable();
                $table->string('symbol', 16)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->unsignedInteger('minor_unit_factor')->default(100);
                $table->boolean('is_enabled')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_metadata');
    }
};
