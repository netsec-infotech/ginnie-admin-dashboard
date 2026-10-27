<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('availability_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->enum('status', ['pending', 'notified'])->default('pending');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['subscribable_type', 'subscribable_id'], 'avail_notif_subscribable_index');
            $table->unique(
                ['user_id', 'subscribable_type', 'subscribable_id', 'store_id'],
                'avail_notif_user_subscribable_store_unique'
            );
        });

        if (Schema::hasTable('product_notifications')) {
            DB::table('availability_notifications')->insertUsing(
                [
                    'user_id',
                    'subscribable_type',
                    'subscribable_id',
                    'store_id',
                    'status',
                    'notified_at',
                    'created_at',
                    'updated_at',
                ],
                DB::table('product_notifications')->select([
                    'user_id',
                    DB::raw("'product_variant'"),
                    'product_variant_id',
                    'store_id',
                    'status',
                    'notified_at',
                    'created_at',
                    'updated_at',
                ])
            );

            Schema::drop('product_notifications');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('product_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->enum('status', ['pending', 'notified'])->default('pending');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'product_id', 'product_variant_id', 'store_id'],
                'prod_notif_user_variant_store_unique'
            );
        });

        DB::table('product_notifications')->insertUsing(
            [
                'user_id',
                'product_id',
                'product_variant_id',
                'store_id',
                'status',
                'notified_at',
                'created_at',
                'updated_at',
            ],
            DB::table('availability_notifications')
                ->join('product_variants', 'product_variants.id', '=', 'availability_notifications.subscribable_id')
                ->where('availability_notifications.subscribable_type', 'product_variant')
                ->select([
                    'availability_notifications.user_id',
                    'product_variants.product_id',
                    'availability_notifications.subscribable_id',
                    'availability_notifications.store_id',
                    'availability_notifications.status',
                    'availability_notifications.notified_at',
                    'availability_notifications.created_at',
                    'availability_notifications.updated_at',
                ])
        );

        Schema::dropIfExists('availability_notifications');
    }
};
