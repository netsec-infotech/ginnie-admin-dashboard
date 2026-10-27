<?php

namespace App\Interfaces;

use App\Models\BulkUploadJob;

/**
 * Contract implemented by per-module importer services (Brand, Category, …)
 * so the generic bulk-uploads:run command can drive any module the same way.
 */
interface BulkImportable
{
    /** Row outcome categories — used by processRow's return and the service counters. */
    public const OUTCOME_CREATED = 'created';
    public const OUTCOME_UPDATED = 'updated';
    public const OUTCOME_SKIPPED = 'skipped';
    public const OUTCOME_FAILED = 'failed';

    /**
     * Short module slug used in URLs / route names (e.g. 'brands').
     */
    public function moduleKey(): string;

    /**
     * Fully-qualified model class this importer creates.
     */
    public function modelClass(): string;

    /**
     * Default CSV template the admin can download as a starting point.
     *
     * @return array{columns: array<int,string>, sample: array<int,string>}
     */
    public function csvTemplate(): array;

    /**
     * Fields that can be mapped to CSV columns.
     *
     * @return array<int, array{key:string,label:string,required:bool,type:string}>
     */
    public function availableFields(): array;

    /**
     * Validate a confirmed mapping before the job is created.
     * Throws \InvalidArgumentException with a translation key when invalid.
     *
     * @param  array<string,string|null>  $mapping    field_key => csv_header
     * @param  array<string,string>       $headers    list of CSV header names
     */
    public function validateMapping(array $mapping, array $headers): void;

    /**
     * Process a single CSV row.
     * Return [string $outcome, string $message] where $outcome is one of the
     * OUTCOME_* constants on this interface. The message is the log line.
     *
     * @param  array<string,mixed>  $row       csv header => raw value
     * @param  BulkUploadJob        $job
     * @return array{0:string,1:string}
     */
    public function processRow(array $row, BulkUploadJob $job): array;

    /**
     * CSV column headers used for export, in the order they appear in the file.
     *
     * @return array<int, string>
     */
    public function exportColumns(): array;

    /**
     * Flatten a single model into an associative array keyed by exportColumns().
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array<string, mixed>
     */
    public function exportRow($model): array;

    /**
     * Filter definitions exposed in the export modal.
     * Each entry: ['key' => 'status', 'label' => 'labels.status',
     *              'type' => 'select|select_remote|date',
     *              'options' => [['value' => 'active', 'label' => 'labels.active']],
     *              'depends_on' => ['key' => 'scope_type', 'value' => 'category']  // optional
     *             ]
     *
     * @return array<int, array<string,mixed>>
     */
    public function exportFilters(): array;

    /**
     * Apply the request's filter values to a base query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string,mixed>                    $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyExportFilters($query, array $filters);

    /**
     * Fields the admin can pick as the match key in Update mode.
     * Each entry: ['key' => 'id', 'label' => 'labels.id', 'column' => 'id']
     * `column` is the actual DB column the importer queries against.
     *
     * @return array<int, array{key:string,label:string,column:string}>
     */
    public function availableMatchFields(): array;
}
