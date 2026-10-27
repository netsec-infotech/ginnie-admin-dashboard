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
        Schema::table('blogs', function (Blueprint $table): void {
            $table->json('category_ids')->nullable()->after('author_profile_id');
            $table->boolean('is_editors_pick')->default(false)->index()->after('status');
        });

        // Migrate existing category_id data to category_ids JSON array
        if (Schema::hasColumn('blogs', 'category_id')) {
            $blogs = DB::table('blogs')->whereNotNull('category_id')->get(['id', 'category_id']);
            foreach ($blogs as $blog) {
                DB::table('blogs')->where('id', $blog->id)->update([
                    'category_ids' => json_encode([(int) $blog->category_id]),
                ]);
            }

            Schema::table('blogs', function (Blueprint $table): void {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('author_profile_id')->constrained('blog_categories')->nullOnDelete();
        });

        // Migrate first category_id back if available
        $blogs = DB::table('blogs')->whereNotNull('category_ids')->get(['id', 'category_ids']);
        foreach ($blogs as $blog) {
            $catIds = json_decode($blog->category_ids, true);
            if (is_array($catIds) && ! empty($catIds)) {
                $firstCatId = (int) reset($catIds);
                DB::table('blogs')->where('id', $blog->id)->update([
                    'category_id' => $firstCatId,
                ]);
            }
        }

        Schema::table('blogs', function (Blueprint $table): void {
            $table->dropColumn(['category_ids', 'is_editors_pick']);
        });
    }
};
