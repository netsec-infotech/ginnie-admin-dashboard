<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillTitleTranslations('product_variants');
        $this->backfillTitleTranslations('global_product_attributes');
        $this->backfillTitleTranslations('global_product_attribute_values');

        $this->convertTitleToJson('product_variants');
        $this->convertTitleToJson('global_product_attributes');
        $this->convertTitleToJson('global_product_attribute_values');
    }

    public function down(): void
    {
        $this->restoreEnglishTitles('product_variants');
        $this->restoreEnglishTitles('global_product_attributes');
        $this->restoreEnglishTitles('global_product_attribute_values');

        $this->convertTitleToString('product_variants');
        $this->convertTitleToString('global_product_attributes');
        $this->convertTitleToString('global_product_attribute_values');
    }

    private function backfillTitleTranslations(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'title')) {
            return;
        }

        DB::table($table)
            ->select(['id', 'title'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $title = (string) ($row->title ?? '');
                    $decoded = $title !== '' ? json_decode($title, true) : null;
                    if (is_array($decoded) && array_key_exists('en', $decoded)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['title' => json_encode(['en' => $title], JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    private function restoreEnglishTitles(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'title')) {
            return;
        }

        DB::table($table)
            ->select(['id', 'title'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $decoded = is_string($row->title) ? json_decode($row->title, true) : null;
                    if (! is_array($decoded)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['title' => (string) ($decoded['en'] ?? reset($decoded) ?: '')]);
                }
            });
    }

    private function convertTitleToJson(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'title') || Schema::getColumnType($table, 'title') === 'json') {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->json('title')->nullable(false)->change();
        });
    }

    private function convertTitleToString(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'title') || Schema::getColumnType($table, 'title') === 'varchar') {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->string('title', 255)->nullable(false)->change();
        });
    }
};
