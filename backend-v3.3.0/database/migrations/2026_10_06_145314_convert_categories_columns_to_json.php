<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $this->addTemporaryJsonColumns();
        $this->populateTemporaryJsonColumns();
        $this->verifyTemporaryJsonColumns();
        $this->swapToJsonColumns();
        $this->verifyCanonicalJsonColumns();
        $this->dropColumnsIfPresent(['title_i18n', 'description_i18n', 'title_legacy', 'description_legacy']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $titleJsonSource = $this->rollbackJsonSource('title');
        $descriptionJsonSource = $this->rollbackJsonSource('description');

        if ($titleJsonSource === null && $descriptionJsonSource === null) {
            $this->enforceScalarSchema();
            $this->verifyScalarColumns();
            $this->dropColumnsIfPresent(['title_scalar', 'description_scalar', 'title_json', 'description_json']);

            return;
        }

        Schema::table('categories', function (Blueprint $table) use ($titleJsonSource, $descriptionJsonSource): void {
            if ($titleJsonSource !== null && ! Schema::hasColumn('categories', 'title_scalar')) {
                $table->string('title_scalar', 255)->nullable();
            }
            if ($descriptionJsonSource !== null && ! Schema::hasColumn('categories', 'description_scalar')) {
                $table->text('description_scalar')->nullable();
            }
        });

        $columns = array_values(array_unique(array_filter([
            'id',
            $titleJsonSource,
            $descriptionJsonSource,
            $titleJsonSource !== null ? 'title_scalar' : null,
            $descriptionJsonSource !== null ? 'description_scalar' : null,
        ])));

        DB::table('categories')->select($columns)->orderBy('id')->chunkById(100, function ($categories) use ($titleJsonSource, $descriptionJsonSource): void {
            foreach ($categories as $category) {
                $updates = [];

                if ($titleJsonSource !== null && $category->title_scalar === null) {
                    $updates['title_scalar'] = $this->englishTitle($category->{$titleJsonSource}, $category->id);
                }

                if ($descriptionJsonSource !== null && $category->description_scalar === null) {
                    $description = $this->englishDescription($category->{$descriptionJsonSource}, $category->id);
                    if ($description !== null) {
                        $updates['description_scalar'] = $description;
                    }
                }

                if ($updates !== []) {
                    DB::table('categories')->where('id', $category->id)->update($updates);
                }
            }
        });

        $this->verifyRollbackTemporaryColumns($titleJsonSource, $descriptionJsonSource);
        $this->swapToScalarColumns();
        $this->enforceScalarSchema();
        $this->verifyScalarColumns();
        $this->dropColumnsIfPresent(['title_json', 'description_json', 'title_scalar', 'description_scalar']);
    }

    private function addTemporaryJsonColumns(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if (! $this->isJsonColumn('title') && ! Schema::hasColumn('categories', 'title_i18n')) {
                $table->json('title_i18n')->nullable();
            }
            if (! $this->isJsonColumn('description') && ! Schema::hasColumn('categories', 'description_i18n')) {
                $table->json('description_i18n')->nullable();
            }
        });
    }

    private function populateTemporaryJsonColumns(): void
    {
        $titleSource = $this->scalarSource('title');
        $descriptionSource = $this->scalarSource('description');
        $titleTarget = $this->temporaryJsonTarget('title', $titleSource);
        $descriptionTarget = $this->temporaryJsonTarget('description', $descriptionSource);
        $columns = array_values(array_unique(array_filter([
            'id',
            $titleSource,
            $descriptionSource,
            $titleTarget,
            $descriptionTarget,
        ])));

        if ($columns === ['id']) {
            return;
        }

        DB::table('categories')->select($columns)->orderBy('id')->chunkById(100, function ($categories) use ($titleSource, $descriptionSource, $titleTarget, $descriptionTarget): void {
            foreach ($categories as $category) {
                $updates = [];

                if ($titleSource !== null && $titleTarget !== null) {
                    $title = (string) ($category->{$titleSource} ?? '');
                    if (trim($title) === '') {
                        throw new RuntimeException("Category {$category->id} has an empty canonical title.");
                    }
                    if ($category->{$titleTarget} === null) {
                        $updates[$titleTarget] = json_encode(['en' => $title], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                }

                if ($descriptionSource !== null && $descriptionTarget !== null && $category->{$descriptionTarget} === null) {
                    $description = $category->{$descriptionSource};
                    if ($description !== null) {
                        $updates[$descriptionTarget] = json_encode(['en' => (string) $description], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                }

                if ($updates !== []) {
                    DB::table('categories')->where('id', $category->id)->update($updates);
                }
            }
        });
    }

    private function verifyTemporaryJsonColumns(): void
    {
        $titleSource = $this->scalarSource('title');
        $descriptionSource = $this->scalarSource('description');
        $titleTarget = $this->temporaryJsonTarget('title', $titleSource);
        $descriptionTarget = $this->temporaryJsonTarget('description', $descriptionSource);
        $columns = array_values(array_unique(array_filter([
            'id',
            $titleSource,
            $descriptionSource,
            $titleTarget,
            $descriptionTarget,
        ])));

        if ($columns === ['id']) {
            return;
        }

        DB::table('categories')->select($columns)->orderBy('id')->chunkById(100, function ($categories) use ($titleSource, $descriptionSource, $titleTarget, $descriptionTarget): void {
            foreach ($categories as $category) {
                if ($titleSource !== null && $titleTarget !== null) {
                    $title = $this->englishTitle($category->{$titleTarget}, $category->id);
                    if ($title !== (string) ($category->{$titleSource} ?? '')) {
                        throw new RuntimeException("Category {$category->id} title failed translation migration verification.");
                    }
                }

                if ($descriptionSource !== null && $descriptionTarget !== null) {
                    $sourceDescription = $category->{$descriptionSource};
                    if ($sourceDescription === null) {
                        if ($category->{$descriptionTarget} !== null) {
                            throw new RuntimeException("Category {$category->id} null description was not preserved.");
                        }
                    } elseif ($this->englishDescription($category->{$descriptionTarget}, $category->id, true) !== (string) $sourceDescription) {
                        throw new RuntimeException("Category {$category->id} description failed translation migration verification.");
                    }
                }
            }
        });
    }

    private function swapToJsonColumns(): void
    {
        if (Schema::hasColumn('categories', 'title') && ! $this->isJsonColumn('title') && ! Schema::hasColumn('categories', 'title_legacy')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('title', 'title_legacy'));
        }
        if (Schema::hasColumn('categories', 'description') && ! $this->isJsonColumn('description') && ! Schema::hasColumn('categories', 'description_legacy')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('description', 'description_legacy'));
        }
        if (Schema::hasColumn('categories', 'title_i18n') && ! Schema::hasColumn('categories', 'title')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('title_i18n', 'title'));
        }
        if (Schema::hasColumn('categories', 'description_i18n') && ! Schema::hasColumn('categories', 'description')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('description_i18n', 'description'));
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->json('title')->nullable(false)->change();
            $table->json('description')->nullable()->change();
        });
    }

    private function verifyCanonicalJsonColumns(): void
    {
        if (! $this->isJsonColumn('title') || ! $this->isJsonColumn('description')) {
            throw new RuntimeException('Category migration did not produce both JSON columns.');
        }

        $titleSource = Schema::hasColumn('categories', 'title_legacy') ? 'title_legacy' : null;
        $descriptionSource = Schema::hasColumn('categories', 'description_legacy') ? 'description_legacy' : null;
        $columns = array_values(array_unique(array_filter(['id', 'title', 'description', $titleSource, $descriptionSource])));

        DB::table('categories')->select($columns)->orderBy('id')->chunkById(100, function ($categories) use ($titleSource, $descriptionSource): void {
            foreach ($categories as $category) {
                $title = $this->englishTitle($category->title, $category->id);
                $description = $this->englishDescription($category->description, $category->id);

                if ($titleSource !== null && $title !== (string) ($category->{$titleSource} ?? '')) {
                    throw new RuntimeException("Category {$category->id} canonical title does not match its legacy value.");
                }

                if ($descriptionSource !== null && $description !== $category->{$descriptionSource}) {
                    throw new RuntimeException("Category {$category->id} canonical description does not match its legacy value.");
                }
            }
        });
    }

    private function englishTitle(mixed $value, int|string $categoryId): string
    {
        $translations = $this->translationMap($value, $categoryId, 'title');
        $title = $translations['en'] ?? null;

        if (! is_string($title) || trim($title) === '') {
            throw new RuntimeException("Category {$categoryId} has an empty canonical title.");
        }

        return $title;
    }

    private function englishDescription(mixed $value, int|string $categoryId, bool $requireEnglish = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $translations = $this->translationMap($value, $categoryId, 'description');
        if (! array_key_exists('en', $translations)) {
            if ($requireEnglish) {
                throw new RuntimeException("Category {$categoryId} has no canonical English description.");
            }

            return null;
        }

        if (! is_string($translations['en'])) {
            throw new RuntimeException("Category {$categoryId} has invalid canonical description data.");
        }

        return $translations['en'];
    }

    /** @return array<string, mixed> */
    private function translationMap(mixed $value, int|string $categoryId, string $column): array
    {
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Category {$categoryId} has invalid {$column} translation data.");
        }

        return $decoded;
    }

    private function scalarSource(string $column): ?string
    {
        if (Schema::hasColumn('categories', "{$column}_legacy")) {
            return "{$column}_legacy";
        }

        if ($this->isJsonColumn($column)) {
            return null;
        }

        return Schema::hasColumn('categories', $column) ? $column : null;
    }

    private function temporaryJsonTarget(string $column, ?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        if (Schema::hasColumn('categories', "{$column}_i18n")) {
            return "{$column}_i18n";
        }

        if ($this->isJsonColumn($column)) {
            return null;
        }

        throw new RuntimeException("Category migration has no JSON target for the {$column} column.");
    }

    private function rollbackJsonSource(string $column): ?string
    {
        if ($this->isJsonColumn($column)) {
            return $column;
        }

        if (Schema::hasColumn('categories', $column)) {
            return null;
        }

        return Schema::hasColumn('categories', "{$column}_json") ? "{$column}_json" : null;
    }

    private function verifyRollbackTemporaryColumns(?string $titleSource, ?string $descriptionSource): void
    {
        $columns = array_values(array_unique(array_filter([
            'id',
            $titleSource,
            $descriptionSource,
            $titleSource !== null ? 'title_scalar' : null,
            $descriptionSource !== null ? 'description_scalar' : null,
        ])));

        DB::table('categories')->select($columns)->orderBy('id')->chunkById(100, function ($categories) use ($titleSource, $descriptionSource): void {
            foreach ($categories as $category) {
                if ($titleSource !== null) {
                    $title = $this->englishTitle($category->{$titleSource}, $category->id);
                    if ($category->title_scalar !== $title || mb_strlen($title) > 255) {
                        throw new RuntimeException("Category {$category->id} title failed rollback verification.");
                    }
                }

                if ($descriptionSource !== null) {
                    $description = $this->englishDescription($category->{$descriptionSource}, $category->id);
                    if ($category->description_scalar !== $description) {
                        throw new RuntimeException("Category {$category->id} description failed rollback verification.");
                    }
                }
            }
        });
    }

    private function swapToScalarColumns(): void
    {
        if ($this->isJsonColumn('title') && ! Schema::hasColumn('categories', 'title_json')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('title', 'title_json'));
        }
        if ($this->isJsonColumn('description') && ! Schema::hasColumn('categories', 'description_json')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('description', 'description_json'));
        }
        if (Schema::hasColumn('categories', 'title_scalar') && ! Schema::hasColumn('categories', 'title')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('title_scalar', 'title'));
        }
        if (Schema::hasColumn('categories', 'description_scalar') && ! Schema::hasColumn('categories', 'description')) {
            Schema::table('categories', fn (Blueprint $table) => $table->renameColumn('description_scalar', 'description'));
        }
    }

    private function enforceScalarSchema(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('title', 255)->nullable(false)->change();
            $table->text('description')->nullable()->change();
        });
    }

    private function verifyScalarColumns(): void
    {
        if (! Schema::hasColumn('categories', 'title') || ! Schema::hasColumn('categories', 'description')) {
            throw new RuntimeException('Category rollback did not restore both scalar columns.');
        }

        DB::table('categories')->select(['id', 'title'])->orderBy('id')->chunkById(100, function ($categories): void {
            foreach ($categories as $category) {
                if (! is_string($category->title) || trim($category->title) === '' || mb_strlen($category->title) > 255) {
                    throw new RuntimeException("Category {$category->id} has unsafe scalar title data.");
                }
            }
        });
    }

    private function isJsonColumn(string $column): bool
    {
        if (! Schema::hasColumn('categories', $column)) {
            return false;
        }

        $type = Schema::getColumnType('categories', $column);
        if ($type === 'json') {
            return true;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $type === 'text' && $this->isSqliteJsonColumnState($column);
        }

        if (! in_array($type, ['text', 'longtext'], true)) {
            return false;
        }

        return $this->hasJsonValidityConstraint($column);
    }

    private function isSqliteJsonColumnState(string $column): bool
    {
        if ($column === 'title') {
            return true;
        }

        if (Schema::hasColumn('categories', 'description_i18n')) {
            return false;
        }

        if (Schema::hasColumn('categories', 'description_legacy')
            || Schema::hasColumn('categories', 'description_scalar')
            || Schema::hasColumn('categories', 'title_json')) {
            return true;
        }

        return Schema::hasColumn('categories', 'title')
            && in_array(Schema::getColumnType('categories', 'title'), ['json', 'text', 'longtext'], true);
    }

    private function hasJsonValidityConstraint(string $column): bool
    {
        try {
            $constraints = DB::table('information_schema.CHECK_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', 'categories')
                ->pluck('CHECK_CLAUSE');
        } catch (Throwable) {
            return false;
        }

        $expectedClause = "json_valid({$column})";

        return $constraints->contains(function (mixed $clause) use ($expectedClause): bool {
            if (! is_string($clause)) {
                return false;
            }

            $normalizedClause = strtolower((string) preg_replace('/[`"\s]/', '', $clause));

            return str_contains($normalizedClause, $expectedClause);
        });
    }

    /** @param  array<int, string>  $columns */
    private function dropColumnsIfPresent(array $columns): void
    {
        $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('categories', $column)));
        if ($existing !== []) {
            Schema::table('categories', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
