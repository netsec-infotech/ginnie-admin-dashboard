<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when the customer dismisses the post-signup referral prompt
            // so the app can avoid re-prompting them. Distinct from friends_code
            // which only ever holds an actually-applied referrer code.
            $table->timestamp('referral_prompt_dismissed_at')->nullable()->after('friends_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_prompt_dismissed_at');
        });
    }
};
