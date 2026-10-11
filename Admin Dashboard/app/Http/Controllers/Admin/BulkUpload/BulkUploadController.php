<?php

namespace App\Http\Controllers\Admin\BulkUpload;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\ConfirmBulkUploadRequest;
use App\Http\Requests\Brand\PreviewBulkUploadRequest;
use App\Interfaces\BulkImportable;
use App\Models\BulkUploadJob;
use App\Services\BulkUpload\BulkUploadJobService;
use App\Services\BulkUpload\BulkUploadModuleConfig;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Generic, module-agnostic bulk upload controller.
 * Dispatches to a per-module BulkImportable resolved from the registry —
 * routes are registered via the Route::bulkUpload() macro so each module
 * gets the full set with consistent names.
 */
class BulkUploadController extends Controller
{
    use ChecksPermissions, AuthorizesRequests;

    public function __construct(
        protected BulkUploadModuleRegistry $registry,
        protected BulkUploadJobService     $jobService,
    )
    {
    }

    /**
     * Step 0: render the upload page for a module. Shows the lockout notice
     * if a job is already pending/processing for this module.
     */
    public function page(string $module): View
    {
        $cfg = $this->registry->get($module);
        $this->authorize('create', $cfg->modelClass);
        $importer = $this->resolveImporter($cfg);
        $activeJob = $this->jobService->findActiveJob($cfg->modelClass);

        return view('admin.bulk-upload.upload', [
            'moduleConfig' => $cfg,
            'availableFields' => $importer->availableFields(),
            'availableMatchFields' => $importer->availableMatchFields(),
            'activeJob' => $activeJob,
        ]);
    }

    /**
     * Step 1: parse the uploaded CSV and return headers + sample rows.
     */
    public function preview(string $module, PreviewBulkUploadRequest $request): JsonResponse
    {
        try {
            $cfg = $this->registry->get($module);
            $this->authorize('create', $cfg->modelClass);

            if ($active = $this->jobService->findActiveJob($cfg->modelClass)) {
                return $this->lockoutResponse($module, $active);
            }

            $stored = $this->jobService->storeUploadedFile($request->file('csv_file'));
            $previewRows = (int)config('bulk_upload.preview_rows', 50);
            $preview = $this->jobService->previewCsv($stored['disk'], $stored['path'], $previewRows);

            if (empty($preview['headers'])) {
                $this->jobService->deleteArtifacts(new BulkUploadJob([
                    'file_disk' => $stored['disk'],
                    'file_path' => $stored['path'],
                ]));
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_csv_no_headers', [], 422);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.bulk_upload_preview_ready',
                data: [
                    'preview_token' => $stored['token'],
                    'file_disk' => $stored['disk'],
                    'file_path' => $stored['path'],
                    'original_filename' => $stored['original_filename'],
                    'headers' => $preview['headers'],
                    'rows' => $preview['rows'],
                    'total_rows' => $preview['total_rows'],
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.validation_failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error('BulkUploadController@preview error', ['module' => $module, 'exception' => $e]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Step 2: persist the mapping and queue a job for the cron command to pick up.
     */
    public function confirm(string $module, ConfirmBulkUploadRequest $request): JsonResponse
    {
        try {
            $cfg = $this->registry->get($module);
            $this->authorize('create', $cfg->modelClass);

            if ($active = $this->jobService->findActiveJob($cfg->modelClass)) {
                return $this->lockoutResponse($module, $active);
            }

            $validated = $request->validated();
            $token = (string)$validated['preview_token'];
            $disk = (string)config('bulk_upload.file_disk', 'local');
            $tempDir = trim((string)config('bulk_upload.temp_dir', 'bulk-uploads'), '/');
            $relativePath = $this->resolveStoredCsv($disk, $tempDir, $token);
            if ($relativePath === null) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_file_not_found', [], 422);
            }

            $preview = $this->jobService->previewCsv($disk, $relativePath, 1);
            if (empty($preview['headers'])) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_csv_no_headers', [], 422);
            }

            $importer = $this->resolveImporter($cfg);
            try {
                $importer->validateMapping($validated['mapping'], $preview['headers']);
            } catch (\InvalidArgumentException $e) {
                return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
            }

            $operation = strtolower((string) ($validated['operation'] ?? 'create'));
            $matchField = $validated['match_field'] ?? null;
            if ($operation === 'update') {
                $allowed = collect($importer->availableMatchFields())->pluck('key')->all();
                if (! in_array($matchField, $allowed, true)) {
                    return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_invalid_match_field', [], 422);
                }
                // The CSV column for the match_field must be mapped so we can find the existing row.
                if (empty($validated['mapping'][$matchField])) {
                    return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_match_column_missing', [], 422);
                }
            }

            $totalRows = $this->jobService->countDataRows($disk, $relativePath);

            $job = $this->jobService->createPendingJob(
                uploadableType: $cfg->modelClass,
                userId: auth()->id(),
                disk: $disk,
                path: $relativePath,
                originalFilename: (string)$validated['original_filename'],
                columnMapping: $validated['mapping'],
                defaultValues: $validated['defaults'] ?? [],
                totalRows: $totalRows,
                operation: $operation,
                matchField: $matchField,
                notifyOnFinish: (bool) ($validated['notify_on_finish'] ?? false),
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.bulk_upload_queued',
                data: [
                    'job_id' => $job->id,
                    'total_rows' => $job->total_rows,
                    'redirect_url' => route("admin.{$module}.bulk-upload.jobs.show", ['id' => $job->id]),
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.validation_failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error('BulkUploadController@confirm error', ['module' => $module, 'exception' => $e]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Return the count of records that match the current export filters.
     * Used by the export modal to show "N items match" live as filters change.
     */
    public function exportCount(string $module, Request $request): JsonResponse
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);
        $importer = $this->resolveImporter($cfg);

        $filters = (array) $request->query('filters', []);
        $modelClass = $cfg->modelClass;
        $query = $importer->applyExportFilters($modelClass::query(), $filters);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.fetched_successfully',
            data: ['count' => $query->count()],
        );
    }

    /**
     * Stream the current contents of a module as a CSV, applying any filters
     * the importer exposes. The first column is always `id` so the resulting
     * file maps cleanly when re-imported in Update mode.
     */
    public function export(string $module, Request $request)
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);
        $importer = $this->resolveImporter($cfg);

        $columns = $importer->exportColumns();
        $filters = (array) $request->query('filters', []);
        $modelClass = $cfg->modelClass;
        $query = $importer->applyExportFilters($modelClass::query(), $filters);

        $filename = $module . '_export_' . now()->format('Y-m-d_Hi') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($query, $importer, $columns) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the file with proper encoding.
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            $query->orderBy('id')->chunk(500, function ($rows) use ($out, $importer, $columns) {
                foreach ($rows as $row) {
                    $payload = $importer->exportRow($row);
                    $line = [];
                    foreach ($columns as $col) {
                        $line[] = $payload[$col] ?? '';
                    }
                    fputcsv($out, $line);
                }
            });

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * Stream the CSV template the admin can use as a starting point.
     */
    public function template(string $module)
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);
        $importer = $this->resolveImporter($cfg);
        $template = $importer->csvTemplate();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $module . '_bulk_template.csv"',
        ];

        return response()->stream(function () use ($template) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $template['columns']);
            fputcsv($out, $template['sample']);
            fclose($out);
        }, 200, $headers);
    }

    /**
     * History list page for this module's jobs.
     */
    public function jobsIndex(string $module): View
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'original_filename', 'name' => 'original_filename', 'title' => __('labels.file')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'progress', 'name' => 'progress', 'title' => __('labels.bulk_upload_progress'), 'orderable' => false, 'searchable' => false],
            ['data' => 'success_count', 'name' => 'success_count', 'title' => __('labels.bulk_upload_success_count')],
            ['data' => 'failed_count', 'name' => 'failed_count', 'title' => __('labels.bulk_upload_failed_count')],
            ['data' => 'started_at', 'name' => 'started_at', 'title' => __('labels.started_at')],
            ['data' => 'finished_at', 'name' => 'finished_at', 'title' => __('labels.finished_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.bulk-upload.jobs-index', [
            'moduleConfig' => $cfg,
            'columns' => $columns,
        ]);
    }

    /**
     * Server-side datatable JSON for the jobs list.
     */
    public function jobsDatatable(string $module, Request $request): JsonResponse
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);

        $draw = (int)$request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = (string)($request->get('search')['value'] ?? '');
        $orderColumnIndex = (int)($request->get('order')[0]['column'] ?? 0);
        $orderDirection = (string)($request->get('order')[0]['dir'] ?? 'desc');
        $columns = ['id', 'original_filename', 'status', 'progress', 'success_count', 'failed_count', 'started_at', 'finished_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        if ($orderColumn === 'progress') {
            $orderColumn = 'processed_rows';
        }

        $query = BulkUploadJob::query()->forType($cfg->modelClass);
        $totalRecords = (clone $query)->count();

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('original_filename', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%");
            });
        }
        $filteredRecords = (clone $query)->count();

        $rows = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (BulkUploadJob $job) use ($module) {
                return [
                    'id' => $job->id,
                    'original_filename' => e($job->original_filename),
                    'status' => $this->renderStatusBadge($job->status),
                    'progress' => $this->renderProgress($job),
                    'success_count' => $job->success_count,
                    'failed_count' => $job->failed_count,
                    'started_at' => optional($job->started_at)->format('Y-m-d H:i'),
                    'finished_at' => optional($job->finished_at)->format('Y-m-d H:i'),
                    'action' => '<a class="btn btn-sm btn-outline-primary" href="'
                        . route("admin.{$module}.bulk-upload.jobs.show", ['id' => $job->id])
                        . '">' . e(__('labels.view')) . '</a>',
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $rows,
        ]);
    }

    /**
     * Detail page for a single job.
     * $id is typed int|string because Laravel's ControllerDispatcher passes
     * route segments as strings under strict_types — the union keeps
     * name-based parameter binding intact (a bare type-less param breaks it).
     */
    public function jobShow($id, string $module): View
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);
        $job = BulkUploadJob::query()->forType($cfg->modelClass)->findOrFail($id);

        return view('admin.bulk-upload.job-show', [
            'moduleConfig' => $cfg,
            'job' => $job,
        ]);
    }

    /**
     * Polling endpoint used by the detail page.
     */
    public function jobStatus($id, string $module): JsonResponse
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);
        $job = BulkUploadJob::query()->forType($cfg->modelClass)->find((int)$id);
        if (!$job) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.fetched_successfully',
            data: [
                'id' => $job->id,
                'status' => $job->status?->value,
                'status_label' => $this->statusLabel($job->status),
                'is_terminal' => $job->isTerminal(),
                'total_rows' => $job->total_rows,
                'processed_rows' => $job->processed_rows,
                'success_count' => $job->success_count,
                'updated_count' => $job->updated_count,
                'skipped_count' => $job->skipped_count,
                'failed_count' => $job->failed_count,
                'operation' => $job->operation?->value,
                'progress_percent' => $job->total_rows > 0
                    ? (int)floor(($job->processed_rows / $job->total_rows) * 100)
                    : 0,
                'error_log' => (string)($job->error_log ?? ''),
                'started_at' => optional($job->started_at)->toDateTimeString(),
                'finished_at' => optional($job->finished_at)->toDateTimeString(),
                // Lets the detail-page poll reveal the "Download failed rows"
                // button live as soon as the importer writes the first row to
                // the errors CSV.
                'has_failed_rows_artifact' => ! empty($job->failed_rows_path),
                // Tooltip copy for the download button — switches from generic
                // "expires N days after completion" to a concrete date once
                // the job hits a terminal state.
                'failed_rows_expires_label' => $job->failedRowsExpiryLabel(),
            ],
        );
    }

    /**
     * Cancel a running (or pending) job. The cron command picks up the new
     * status on its next row read and bails — rows already processed in the
     * current tick stay credited.
     *
     * Guarded against terminal jobs: COMPLETED / FAILED / CANCELLED return 409.
     */
    public function cancel($id, string $module): JsonResponse
    {
        try {
            $cfg = $this->registry->get($module);
            $this->authorize('create', $cfg->modelClass);

            $job = BulkUploadJob::query()->forType($cfg->modelClass)->find((int) $id);
            if (! $job) {
                return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
            }

            if ($job->isTerminal()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_cannot_cancel_terminal', [], 409);
            }

            $this->jobService->appendLog($job, __('labels.bulk_upload_cancelled_by_user'));
            $this->jobService->markCancelled($job);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.bulk_upload_cancelled',
                data: ['job_id' => $job->id],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        } catch (\Throwable $e) {
            Log::error('BulkUploadController@cancel error', ['module' => $module, 'id' => $id, 'exception' => $e]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Download the per-row errors CSV generated for this job. Streams the
     * file with `_row, ...input columns..., _error` so the admin can edit
     * and re-upload the fixed rows in Create mode.
     *
     * Returns 404 once the file has been swept (post-prune, or never
     * existed because the job had no failures).
     */
    public function downloadErrors($id, string $module)
    {
        $cfg = $this->registry->get($module);
        $this->authorize('viewAny', $cfg->modelClass);

        $job = BulkUploadJob::query()->forType($cfg->modelClass)->find((int) $id);
        if (! $job || ! $job->failed_rows_path) {
            abort(404, __('labels.bulk_upload_errors_not_available'));
        }

        $disk = $job->file_disk ?: (string) config('bulk_upload.file_disk', 'local');
        $storage = Storage::disk($disk);
        if (! $storage->exists($job->failed_rows_path)) {
            abort(404, __('labels.bulk_upload_errors_not_available'));
        }

        $filename = $module . '_job_' . $job->id . '_errors.csv';

        return $storage->download($job->failed_rows_path, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveImporter(BulkUploadModuleConfig $cfg): BulkImportable
    {
        return app($cfg->importerClass);
    }

    private function lockoutResponse(string $module, BulkUploadJob $active): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: false,
            message: 'labels.bulk_upload_already_in_progress',
            data: [
                'active_job_id' => $active->id,
                'redirect_url' => route("admin.{$module}.bulk-upload.jobs.show", ['id' => $active->id]),
            ],
            status: 409,
        );
    }

    private function resolveStoredCsv(string $disk, string $tempDir, string $token): ?string
    {
        $token = preg_replace('/[^A-Za-z0-9\-]/', '', $token) ?: '';
        if ($token === '') {
            return null;
        }
        $storage = Storage::disk($disk);
        $dir = $tempDir . '/' . $token;
        if (!$storage->exists($dir)) {
            return null;
        }
        $files = $storage->files($dir);
        return $files[0] ?? null;
    }

    private function renderStatusBadge(?BulkUploadJobStatusEnum $status): string
    {
        $value = $status?->value ?? 'pending';
        $class = match ($value) {
            BulkUploadJobStatusEnum::COMPLETED() => 'bg-success-lt',
            BulkUploadJobStatusEnum::PROCESSING() => 'bg-info-lt',
            BulkUploadJobStatusEnum::FAILED() => 'bg-danger-lt',
            BulkUploadJobStatusEnum::CANCELLED() => 'bg-warning-lt',
            default => 'bg-secondary-lt',
        };
        return '<span class="badge ' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?BulkUploadJobStatusEnum $status): string
    {
        $value = $status?->value ?? 'pending';
        return __('labels.bulk_upload_status_' . $value);
    }

    private function renderProgress(BulkUploadJob $job): string
    {
        $total = max($job->total_rows, 1);
        $pct = (int)floor(($job->processed_rows / $total) * 100);
        $pct = max(0, min(100, $pct));
        return '<div class="progress" style="height: 6px;">'
            . '<div class="progress-bar" style="width: ' . $pct . '%"></div>'
            . '</div>'
            . '<div class="small text-muted">' . $job->processed_rows . ' / ' . $job->total_rows . '</div>';
    }
}
