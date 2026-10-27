<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands')) {
            return;
        }

        if ($this->isJsonColumn('title') && $this->isJsonColumn('description')) {
            $this->verifyCanonicalJsonColumns();
            $this->dropColumnsIfPresent(['title_i18n', 'description_i18n', 'title_legacy', 'description_legacy']);

            return;
        }

        $this->addJsonTemporaryColumns();
        $this->populateJsonTemporaryColumns();
        $this->verifyTemporaryJsonColumns();
        $this->swapToJsonColumns();
        $this->verifyCanonicalJsonColumns();
        $this->dropColumnsIfPresent(['title_i18n', 'description_i18n', 'title_legacy', 'description_legacy']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('brands')) {
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

        Schema::table('brands', function (Blueprint $table) use ($titleJsonSource, $descriptionJsonSource): void {
            if ($titleJsonSource !== null && ! Schema::hasColumn('brands', 'title_scalar')) {
                $table->string('title_scalar', 255)->nullable();
            }
            if ($descriptionJsonSource !== null && ! Schema::hasColumn('brands', 'description_scalar')) {
                $table->string('description_scalar', 255)->nullable();
            }
        });

        $columns = array_values(array_filter([
            'id',
            $titleJsonSource,
            $descriptionJsonSource,
            $titleJsonSource !== null ? 'title_scalar' : null,
            $descriptionJsonSource !== null ? 'description_scalar' : null,
        ]));

        DB::table('brands')->select(array_values(array_unique($columns)))->orderBy('id')->chunkById(100, function ($brands) use ($titleJsonSource, $descriptionJsonSource): void {
            foreach ($brands as $brand) {
                $updates = [];

                if ($titleJsonSource !== null && $brand->title_scalar === null) {
                    $title = $this->englishValue($brand->{$titleJsonSource}, $brand->id, 'title', false);
                    $this->guardSafeScalarValue($brand->id, 'title', $title);
                    $updates['title_scalar'] = $title;
                }

                if ($descriptionJsonSource !== null && $brand->description_scalar === null) {
                    $description = $this->englishValue($brand->{$descriptionJsonSource}, $brand->id, 'description', true);
                    $this->guardSafeScalarValue($brand->id, 'description', $description);
                    $updates['description_scalar'] = $description;
                }

                if ($updates !== []) {
                    DB::table('brands')->where('id', $brand->id)->update($updates);
                }
            }
        });

        $this->verifyRollbackTemporaryColumns($titleJsonSource, $descriptionJsonSource);

        if ($this->isJsonColumn('title') && ! Schema::hasColumn('brands', 'title_json')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('title', 'title_json'));
        }
        if ($this->isJsonColumn('description') && ! Schema::hasColumn('brands', 'description_json')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('description', 'description_json'));
        }
        if (Schema::hasColumn('brands', 'title_scalar') && ! Schema::hasColumn('brands', 'title')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('title_scalar', 'title'));
        }
        if (Schema::hasColumn('brands', 'description_scalar') && ! Schema::hasColumn('brands', 'description')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('description_scalar', 'description'));
        }

        $this->enforceScalarSchema();

        $this->verifyScalarColumns();
        $this->dropColumnsIfPresent(['title_json', 'description_json', 'title_scalar', 'description_scalar']);
    }

    private function addJsonTemporaryColumns(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            if (! Schema::hasColumn('brands', 'title_i18n')) {
                $table->json('title_i18n')->nullable();
            }
            if (! Schema::hasColumn('brands', 'description_i18n')) {
                $table->json('description_i18n')->nullable();
            }
        });
    }

    private function populateJsonTemporaryColumns(): void
    {
        $titleSource = Schema::hasColumn('brands', 'title_legacy') ? 'title_legacy' : 'title';
        $descriptionSource = Schema::hasColumn('brands', 'description_legacy') ? 'description_legacy' : 'description';

        DB::table('brands')
            ->select(['id', $titleSource, $descriptionSource, 'title_i18n', 'description_i18n'])
            ->orderBy('id')
            ->chunkById(100, function ($brands) use ($titleSource, $descriptionSource): void {
                foreach ($brands as $brand) {
                    $rawTitle = (string) ($brand->{$titleSource} ?? '');
                    $rawDescription = (string) ($brand->{$descriptionSource} ?? '');

                    if (trim($rawTitle) === '') {
                        throw new RuntimeException("Brand {$brand->id} has an empty canonical title.");
                    }

                    $updates = [];
                    if ($brand->title_i18n === null) {
                        $updates['title_i18n'] = json_encode(['en' => $rawTitle], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                    if ($brand->description_i18n === null) {
                        $updates['description_i18n'] = json_encode(['en' => $rawDescription], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                    if ($updates !== []) {
                        DB::table('brands')->where('id', $brand->id)->update($updates);
                    }
                }
            });
    }

    private function verifyTemporaryJsonColumns(): void
    {
        $titleSource = Schema::hasColumn('brands', 'title_legacy') ? 'title_legacy' : 'title';
        $descriptionSource = Schema::hasColumn('brands', 'description_legacy') ? 'description_legacy' : 'description';

        DB::table('brands')
            ->select(['id', $titleSource, $descriptionSource, 'title_i18n', 'description_i18n'])
            ->orderBy('id')
            ->chunkById(100, function ($brands) use ($titleSource, $descriptionSource): void {
                foreach ($brands as $brand) {
                    $title = $this->englishValue($brand->title_i18n, $brand->id, 'title', false);
                    $description = $this->englishValue($brand->description_i18n, $brand->id, 'description', true);

                    if ($title !== (string) ($brand->{$titleSource} ?? '')) {
                        throw new RuntimeException("Brand {$brand->id} title failed translation migration verification.");
                    }

                    if ($description !== (string) ($brand->{$descriptionSource} ?? '')) {
                        throw new RuntimeException("Brand {$brand->id} description failed translation migration verification.");
                    }
                }
            });
    }

    private function swapToJsonColumns(): void
    {
        if (Schema::hasColumn('brands', 'title') && ! $this->isJsonColumn('title') && ! Schema::hasColumn('brands', 'title_legacy')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('title', 'title_legacy'));
        }
        if (Schema::hasColumn('brands', 'description') && ! $this->isJsonColumn('description') && ! Schema::hasColumn('brands', 'description_legacy')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('description', 'description_legacy'));
        }
        if (Schema::hasColumn('brands', 'title_i18n') && ! Schema::hasColumn('brands', 'title')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('title_i18n', 'title'));
        }
        if (Schema::hasColumn('brands', 'description_i18n') && ! Schema::hasColumn('brands', 'description')) {
            Schema::table('brands', fn (Blueprint $table) => $table->renameColumn('description_i18n', 'description'));
        }

        Schema::table('brands', function (Blueprint $table): void {
            $table->json('title')->nullable(false)->change();
            $table->json('description')->nullable(false)->change();
        });
    }

    private function verifyCanonicalJsonColumns(): void
    {
        $titleSource = Schema::hasColumn('brands', 'title_legacy') ? 'title_legacy' : null;
        $descriptionSource = Schema::hasColumn('brands', 'description_legacy') ? 'description_legacy' : null;
        $columns = array_values(array_filter(['id', 'title', 'description', $titleSource, $descriptionSource]));

        DB::table('brands')->select(array_values(array_unique($columns)))->orderBy('id')->chunkById(100, function ($brands) use ($titleSource, $descriptionSource): void {
            foreach ($brands as $brand) {
                $title = $this->englishValue($brand->title, $brand->id, 'title', false);
                $description = $this->englishValue($brand->description, $brand->id, 'description', true);

                if ($titleSource !== null && $title !== (string) ($brand->{$titleSource} ?? '')) {
                    throw new RuntimeException("Brand {$brand->id} canonical title does not match its legacy value.");
                }

                if ($descriptionSource !== null && $description !== (string) ($brand->{$descriptionSource} ?? '')) {
                    throw new RuntimeException("Brand {$brand->id} canonical description does not match its legacy value.");
                }
            }
        });
    }

    private function englishValue(mixed $value, int|string $brandId, string $column, bool $allowBlank): string
    {
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_key_exists('en', $decoded) || ! is_string($decoded['en'])) {
            throw new RuntimeException("Brand {$brandId} has invalid canonical {$column} translation data.");
        }

        if (! $allowBlank && trim($decoded['en']) === '') {
            throw new RuntimeException("Brand {$brandId} has an empty canonical {$column}.");
        }

        return $decoded['en'];
    }

    private function rollbackJsonSource(string $column): ?string
    {
        if ($this->isJsonColumn($column)) {
            return $column;
        }

        if (Schema::hasColumn('brands', $column)) {
            return null;
        }

        $backup = $column.'_json';

        return Schema::hasColumn('brands', $backup) ? $backup : null;
    }

    private function guardSafeScalarValue(int|string $brandId, string $column, string $value): void
    {
        if (mb_strlen($value) > 255) {
            throw new RuntimeException("Brand {$brandId} cannot safely restore its English {$column} to VARCHAR(255).");
        }
    }

    private function verifyRollbackTemporaryColumns(?string $titleSource, ?string $descriptionSource): void
    {
        $columns = array_values(array_filter([
            'id',
            $titleSource,
            $descriptionSource,
            $titleSource !== null ? 'title_scalar' : null,
            $descriptionSource !== null ? 'description_scalar' : null,
        ]));

        DB::table('brands')->select(array_values(array_unique($columns)))->orderBy('id')->chunkById(100, function ($brands) use ($titleSource, $descriptionSource): void {
            foreach ($brands as $brand) {
                if ($titleSource !== null) {
                    $title = (string) $brand->title_scalar;
                    $this->guardSafeScalarValue($brand->id, 'title', $title);
                    if ($title !== $this->englishValue($brand->{$titleSource}, $brand->id, 'title', false)) {
                        throw new RuntimeException("Brand {$brand->id} title failed rollback verification.");
                    }
                }

                if ($descriptionSource !== null) {
                    $description = (string) $brand->description_scalar;
                    $this->guardSafeScalarValue($brand->id, 'description', $description);
                    if ($description !== $this->englishValue($brand->{$descriptionSource}, $brand->id, 'description', true)) {
                        throw new RuntimeException("Brand {$brand->id} description failed rollback verification.");
                    }
                }
            }
        });
    }

    private function verifyScalarColumns(): void
    {
        if (! Schema::hasColumn('brands', 'title') || ! Schema::hasColumn('brands', 'description')) {
            throw new RuntimeException('Brand rollback did not restore both canonical scalar columns.');
        }

        DB::table('brands')->select(['id', 'title', 'description'])->orderBy('id')->chunkById(100, function ($brands): void {
            foreach ($brands as $brand) {
                $title = $brand->title;
                $description = $brand->description;

                if (! is_string($title) || trim($title) === '') {
                    throw new RuntimeException("Brand {$brand->id} has an empty canonical title.");
                }
                if (! is_string($description)) {
                    throw new RuntimeException("Brand {$brand->id} has invalid canonical description data.");
                }

                $this->guardSafeScalarValue($brand->id, 'title', $title);
                $this->guardSafeScalarValue($brand->id, 'description', $description);
            }
        });
    }

    private function enforceScalarSchema(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->string('title', 255)->nullable(false)->change();
            $table->string('description', 255)->nullable(false)->change();
        });
    }

    private function isJsonColumn(string $column): bool
    {
        return Schema::hasColumn('brands', $column)
            && Schema::getColumnType('brands', $column) === 'json';
    }

    /** @param array<int, string> $columns */
    private function dropColumnsIfPresent(array $columns): void
    {
        $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('brands', $column)));
        if ($existing !== []) {
            Schema::table('brands', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
