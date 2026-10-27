<?php

namespace App\Interfaces;

interface TranslationBulkImportable
{
    public function validateTranslationLocale(string $locale): void;

    /** @return array<int, string> */
    public function translationExportColumns(string $locale): array;

    /** @return array<string, mixed> */
    public function translationExportRow($model, string $locale): array;
}
