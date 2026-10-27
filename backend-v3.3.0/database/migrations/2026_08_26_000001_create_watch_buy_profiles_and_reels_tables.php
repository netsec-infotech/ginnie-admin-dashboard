<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_buy_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_type', 20);
            $table->foreignId('seller_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('username', 30)->unique();
            $table->string('bio', 160)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['profile_type', 'status']);
        });

        Schema::create('watch_buy_reels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->foreignId('profile_id')->constrained('watch_buy_profiles')->restrictOnDelete();
            $table->string('source', 20);
            $table->text('caption')->nullable();
            $table->string('status', 20)->default('published');
            $table->string('status_before_block', 20)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('video_width');
            $table->unsignedInteger('video_height');
            $table->unsignedBigInteger('video_size_bytes');
            $table->unsignedBigInteger('like_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['profile_id', 'status', 'published_at']);
        });

        Schema::create('watch_buy_reel_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained('watch_buy_reels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['reel_id', 'product_variant_id']);
            $table->unique(['reel_id', 'sort_order']);
        });

        Schema::create('watch_buy_reel_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained('watch_buy_reels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['reel_id', 'user_id']);
        });

        Schema::create('watch_buy_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('action', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_buy_moderation_logs');
        Schema::dropIfExists('watch_buy_reel_likes');
        Schema::dropIfExists('watch_buy_reel_products');
        Schema::dropIfExists('watch_buy_reels');
        Schema::dropIfExists('watch_buy_profiles');
    }
};
