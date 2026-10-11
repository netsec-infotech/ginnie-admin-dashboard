<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Budget & spend (decimal for money — never float)
            $table->decimal('budget', 12, 2);
            $table->decimal('spent', 12, 2)->default(0);
            // Snapshot of the CPC rate at campaign creation time
            $table->decimal('cpc_rate_snapshot', 10, 4);

            // Placements are always all-slots (search + related); stored for audit
            $table->json('placements')->nullable();

            // Status lifecycle:
            // draft → pending_approval → approved/rejected → running → paused/completed/force_stopped
            $table->string('status', 30)->default('draft')->index();

            // Admin approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Force-stop
            $table->foreignId('force_stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('force_stop_reason')->nullable();
            $table->timestamp('force_stopped_at')->nullable();

            // Wallet deduction reference (the wallet transaction that reserved the budget)
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
