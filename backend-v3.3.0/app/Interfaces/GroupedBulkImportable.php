<?php

namespace App\Interfaces;

use App\Models\BulkUploadJob;

/**
 * Optional extension for importers whose logical record spans multiple CSV rows.
 *
 * Products use this because one product handle can contain many variant/store rows,
 * while brands and categories keep the simpler one-row processRow() flow.
 */
interface GroupedBulkImportable
{
    /**
     * Return the logical grouping key for a CSV row, such as a product handle.
     *
     * @param  array<string,mixed>  $row
     */
    public function groupKey(array $row, BulkUploadJob $job): string;

    /**
     * Process all rows for one logical record.
     *
     * @param  array<int, array<string,mixed>>  $rows
     * @return array{0:string,1:string}
     */
    public function processRows(array $rows, BulkUploadJob $job): array;
}
