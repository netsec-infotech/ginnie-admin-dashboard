<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Table for managing global (admin) and store/seller specific return reasons.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('product_return_reasons');
        Schema::dropIfExists('return_reasons');
        Schema::enableForeignKeyConstraints();

        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            // seller_id is null for global admin return reasons, or points to seller for custom seller reasons
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->string('title');
            // Single unified proof required flag (applies to customer proof submission and rider pickup proof)
            $table->boolean('proof_required')->default(false);
            $table->boolean('otp_required')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_reasons');
    }
};
