<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currency_backfill_runs')) {
            Schema::create('currency_backfill_runs', function (Blueprint $table) {
                $table->id();
                $table->string('command');
                $table->string('mode', 20);
                $table->string('status', 20)->default('running');
                $table->json('options')->nullable();
                $table->json('checkpoints')->nullable();
                $table->json('summary')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['command', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_backfill_runs');
    }
};
