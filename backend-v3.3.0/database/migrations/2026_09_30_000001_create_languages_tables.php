<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('surface', 30);
            $table->string('code', 20);
            $table->string('name', 80);
            $table->string('native_name', 80);
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['surface', 'code']);
            $table->index(['surface', 'is_active']);
        });

        Schema::create('language_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->text('value');
            $table->timestamps();
            $table->unique(['language_id', 'key']);
        });

        $now = now();
        DB::table('languages')->insert([
            ['surface' => 'panel', 'code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'is_recommended' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['surface' => 'panel', 'code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['surface' => 'panel', 'code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['surface' => 'panel', 'code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['surface' => 'panel', 'code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => false, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (['es', 'fr', 'de', 'zh'] as $code) {
            $path = resource_path("language-packs/panel/{$code}.json");
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $languageId = DB::table('languages')->where('surface', 'panel')->where('code', $code)->value('id');
            $rows = collect(Arr::dot($payload['labels'] ?? []))
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value, string $key): array => [
                    'language_id' => $languageId,
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values();

            $rows->chunk(500)->each(fn ($chunk) => DB::table('language_translations')->insert($chunk->all()));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
    }
};
