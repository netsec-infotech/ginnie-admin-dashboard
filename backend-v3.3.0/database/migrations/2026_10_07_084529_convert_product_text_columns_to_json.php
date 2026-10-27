<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $columns = ['title', 'short_description', 'description'];

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->addTemporaryJsonColumns();
        $this->populateTemporaryJsonColumns();
        $this->verifyTemporaryJsonColumns();
        $this->swapToJsonColumns();
        $this->enforceJsonSchema();
        $this->verifyCanonicalJsonColumns();
        $this->dropColumnsIfPresent([
            'title_i18n', 'short_description_i18n', 'description_i18n',
            'title_legacy', 'short_description_legacy', 'description_legacy',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $sources = collect($this->columns)
            ->mapWithKeys(fn (string $column): array => [$column => $this->rollbackJsonSource($column)])
            ->all();

        if (collect($sources)->every(fn (?string $source): bool => $source === null)) {
            $this->enforceScalarSchema();
            $this->verifyScalarColumns();
            $this->dropColumnsIfPresent($this->rollbackCleanupColumns());

            return;
        }

        Schema::table('products', function (Blueprint $table) use ($sources): void {
            foreach ($sources as $column => $source) {
                if ($source === null || Schema::hasColumn('products', "{$column}_scalar")) {
                    continue;
                }

                $column === 'description'
                    ? $table->text("{$column}_scalar")->nullable()
                    : $table->string("{$column}_scalar", 255)->nullable();
            }
        });

        $selectColumns = ['id'];
        foreach ($sources as $column => $source) {
            if ($source !== null) {
                $selectColumns[] = $source;
                $selectColumns[] = "{$column}_scalar";
            }
        }

        DB::table('products')->select(array_values(array_unique($selectColumns)))->orderBy('id')->chunkById(100, function ($products) use ($sources): void {
            foreach ($products as $product) {
                $updates = [];
                foreach ($sources as $column => $source) {
                    $target = "{$column}_scalar";
                    if ($source !== null && $product->{$target} === null) {
                        $updates[$target] = $this->englishValue($product->{$source}, $product->id, $column);
                    }
                }

                if ($updates !== []) {
                    DB::table('products')->where('id', $product->id)->update($updates);
                }
            }
        });

        $this->verifyRollbackTemporaryColumns($sources);
        $this->swapToScalarColumns();
        $this->enforceScalarSchema();
        $this->verifyScalarColumns();
        $this->dropColumnsIfPresent($this->rollbackCleanupColumns());
    }

    private function addTemporaryJsonColumns(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ($this->columns as $column) {
                if (! $this->isJsonColumn($column) && ! Schema::hasColumn('products', "{$column}_i18n")) {
                    $table->json("{$column}_i18n")->nullable();
                }
            }
        });
    }

    private function populateTemporaryJsonColumns(): void
    {
        $states = $this->forwardStates();
        $selectColumns = ['id'];
        foreach ($states as $state) {
            $selectColumns = array_merge($selectColumns, array_filter([$state['source'], $state['target']]));
        }

        if (array_values(array_unique($selectColumns)) === ['id']) {
            return;
        }

        DB::table('products')->select(array_values(array_unique($selectColumns)))->orderBy('id')->chunkById(100, function ($products) use ($states): void {
            foreach ($products as $product) {
                $updates = [];
                foreach ($states as $column => $state) {
                    if ($state['source'] === null || $state['target'] === null) {
                        continue;
                    }

                    $value = (string) ($product->{$state['source']} ?? '');
                    if (trim($value) === '') {
                        throw new RuntimeException("Product {$product->id} has an empty canonical {$column}.");
                    }
                    if ($product->{$state['target']} === null) {
                        $updates[$state['target']] = json_encode(['en' => $value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                }

                if ($updates !== []) {
                    DB::table('products')->where('id', $product->id)->update($updates);
                }
            }
        });
    }

    private function verifyTemporaryJsonColumns(): void
    {
        $states = $this->forwardStates();
        $selectColumns = ['id'];
        foreach ($states as $state) {
            if ($state['source'] !== null && $state['target'] !== null) {
                $selectColumns[] = $state['source'];
                $selectColumns[] = $state['target'];
            }
        }

        if (array_values(array_unique($selectColumns)) === ['id']) {
            return;
        }

        DB::table('products')->select(array_values(array_unique($selectColumns)))->orderBy('id')->chunkById(100, function ($products) use ($states): void {
            foreach ($products as $product) {
                foreach ($states as $column => $state) {
                    if ($state['source'] === null || $state['target'] === null) {
                        continue;
                    }

                    if ($this->englishValue($product->{$state['target']}, $product->id, $column) !== (string) ($product->{$state['source']} ?? '')) {
                        throw new RuntimeException("Product {$product->id} {$column} failed translation migration verification.");
                    }
                }
            }
        });
    }

    private function swapToJsonColumns(): void
    {
        foreach ($this->columns as $column) {
            if (Schema::hasColumn('products', $column) && ! $this->isJsonColumn($column) && ! Schema::hasColumn('products', "{$column}_legacy")) {
                Schema::table('products', fn (Blueprint $table) => $table->renameColumn($column, "{$column}_legacy"));
            }
            if (Schema::hasColumn('products', "{$column}_i18n") && ! Schema::hasColumn('products', $column)) {
                Schema::table('products', fn (Blueprint $table) => $table->renameColumn("{$column}_i18n", $column));
            }
        }
    }

    private function enforceJsonSchema(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('title')->nullable(false)->change();
            $table->json('short_description')->nullable(false)->change();
            $table->json('description')->nullable(false)->change();
        });
    }

    private function verifyCanonicalJsonColumns(): void
    {
        foreach ($this->columns as $column) {
            if (! $this->isJsonColumn($column)) {
                throw new RuntimeException("Product migration did not produce a JSON {$column} column.");
            }
        }

        $legacyColumns = collect($this->columns)->mapWithKeys(fn (string $column): array => [
            $column => Schema::hasColumn('products', "{$column}_legacy") ? "{$column}_legacy" : null,
        ])->all();
        $selectColumns = array_merge(['id'], $this->columns, array_values(array_filter($legacyColumns)));

        DB::table('products')->select(array_values(array_unique($selectColumns)))->orderBy('id')->chunkById(100, function ($products) use ($legacyColumns): void {
            foreach ($products as $product) {
                foreach ($this->columns as $column) {
                    $value = $this->englishValue($product->{$column}, $product->id, $column);
                    $legacyColumn = $legacyColumns[$column];
                    if ($legacyColumn !== null && $value !== (string) ($product->{$legacyColumn} ?? '')) {
                        throw new RuntimeException("Product {$product->id} canonical {$column} does not match its legacy value.");
                    }
                }
            }
        });
    }

    /** @return array<string, array{source: ?string, target: ?string}> */
    private function forwardStates(): array
    {
        $states = [];
        foreach ($this->columns as $column) {
            $source = $this->scalarSource($column);
            $states[$column] = ['source' => $source, 'target' => $this->temporaryJsonTarget($column, $source)];
        }

        return $states;
    }

    private function scalarSource(string $column): ?string
    {
        if (Schema::hasColumn('products', "{$column}_legacy")) {
            return "{$column}_legacy";
        }
        if ($this->isJsonColumn($column)) {
            return null;
        }

        return Schema::hasColumn('products', $column) ? $column : null;
    }

    private function temporaryJsonTarget(string $column, ?string $source): ?string
    {
        if ($source === null) {
            return null;
        }
        if (Schema::hasColumn('products', "{$column}_i18n")) {
            return "{$column}_i18n";
        }
        if ($this->isJsonColumn($column)) {
            return null;
        }

        throw new RuntimeException("Product migration has no JSON target for the {$column} column.");
    }

    private function rollbackJsonSource(string $column): ?string
    {
        if ($this->isJsonColumn($column)) {
            return $column;
        }
        if (Schema::hasColumn('products', $column)) {
            return null;
        }

        return Schema::hasColumn('products', "{$column}_json") ? "{$column}_json" : null;
    }

    /** @param  array<string, ?string>  $sources */
    private function verifyRollbackTemporaryColumns(array $sources): void
    {
        $selectColumns = ['id'];
        foreach ($sources as $column => $source) {
            if ($source !== null) {
                $selectColumns[] = $source;
                $selectColumns[] = "{$column}_scalar";
            }
        }

        DB::table('products')->select(array_values(array_unique($selectColumns)))->orderBy('id')->chunkById(100, function ($products) use ($sources): void {
            foreach ($products as $product) {
                foreach ($sources as $column => $source) {
                    if ($source === null) {
                        continue;
                    }

                    $value = $this->englishValue($product->{$source}, $product->id, $column);
                    if ($product->{"{$column}_scalar"} !== $value || ! $this->fitsLegacyColumn($column, $value)) {
                        throw new RuntimeException("Product {$product->id} {$column} failed rollback verification.");
                    }
                }
            }
        });
    }

    private function swapToScalarColumns(): void
    {
        foreach ($this->columns as $column) {
            if ($this->isJsonColumn($column) && ! Schema::hasColumn('products', "{$column}_json")) {
                Schema::table('products', fn (Blueprint $table) => $table->renameColumn($column, "{$column}_json"));
            }
            if (Schema::hasColumn('products', "{$column}_scalar") && ! Schema::hasColumn('products', $column)) {
                Schema::table('products', fn (Blueprint $table) => $table->renameColumn("{$column}_scalar", $column));
            }
        }
    }

    private function enforceScalarSchema(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('title', 255)->nullable(false)->change();
            $table->string('short_description', 255)->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
        });
    }

    private function verifyScalarColumns(): void
    {
        foreach ($this->columns as $column) {
            if (! Schema::hasColumn('products', $column) || $this->isJsonColumn($column)) {
                throw new RuntimeException("Product rollback did not restore the scalar {$column} column.");
            }
        }

        DB::table('products')->select(array_merge(['id'], $this->columns))->orderBy('id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                foreach ($this->columns as $column) {
                    $value = $product->{$column};
                    if (! is_string($value) || trim($value) === '' || ! $this->fitsLegacyColumn($column, $value)) {
                        throw new RuntimeException("Product {$product->id} has unsafe scalar {$column} data.");
                    }
                }
            }
        });
    }

    private function fitsLegacyColumn(string $column, string $value): bool
    {
        return $column === 'description' ? strlen($value) <= 65535 : mb_strlen($value) <= 255;
    }

    private function englishValue(mixed $value, int|string $productId, string $column): string
    {
        $translations = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        $english = is_array($translations) ? ($translations['en'] ?? null) : null;
        if (! is_string($english) || trim($english) === '') {
            throw new RuntimeException("Product {$productId} has an empty canonical {$column}.");
        }

        return $english;
    }

    private function isJsonColumn(string $column): bool
    {
        if (! Schema::hasColumn('products', $column)) {
            return false;
        }

        $type = Schema::getColumnType('products', $column);
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
        if (Schema::hasColumn('products', "{$column}_json")) {
            return false;
        }
        if ($column === 'title') {
            return true;
        }
        if (Schema::hasColumn('products', "{$column}_i18n")) {
            return false;
        }
        if (Schema::hasColumn('products', "{$column}_legacy")
            || Schema::hasColumn('products', "{$column}_scalar")) {
            return true;
        }

        return Schema::hasColumn('products', 'title') && Schema::getColumnType('products', 'title') === 'text';
    }

    private function hasJsonValidityConstraint(string $column): bool
    {
        try {
            $constraints = DB::table('information_schema.CHECK_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', 'products')
                ->pluck('CHECK_CLAUSE');
        } catch (Throwable) {
            return false;
        }

        $expectedClause = "json_valid({$column})";

        return $constraints->contains(function (mixed $clause) use ($expectedClause): bool {
            $normalizedClause = is_string($clause)
                ? strtolower((string) preg_replace('/[`"\s]/', '', $clause))
                : '';

            return str_contains($normalizedClause, $expectedClause);
        });
    }

    /** @return array<int, string> */
    private function rollbackCleanupColumns(): array
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = "{$column}_json";
            $columns[] = "{$column}_scalar";
        }

        return $columns;
    }

    /** @param  array<int, string>  $columns */
    private function dropColumnsIfPresent(array $columns): void
    {
        $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('products', $column)));
        if ($existing !== []) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
