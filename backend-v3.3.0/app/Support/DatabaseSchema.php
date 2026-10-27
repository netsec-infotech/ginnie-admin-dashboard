<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class DatabaseSchema
{
    private array $tables = [];

    public function hasTable(string $table): bool
    {
        try {
            return $this->tables[$table] ??= Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}

