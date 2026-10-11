<?php

namespace App\Models;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Enums\BulkUpload\BulkUploadOperationEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static create(mixed $attributes)
 * @method static find($id)
 * @method static where($column, $operator = null, $value = null)
 */
class BulkUploadJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uploadable_type',
        'operation',
        'match_field',
        'notify_on_finish',
        'user_id',
        'file_disk',
        'file_path',
        'failed_rows_path',
        'original_filename',
        'column_mapping',
        'default_values',
        'total_rows',
        'processed_rows',
        'success_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'status',
        'error_log',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'default_values' => 'array',
        'status' => BulkUploadJobStatusEnum::class,
        'operation' => BulkUploadOperationEnum::class,
        'notify_on_finish' => 'boolean',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'success_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('uploadable_type', $type);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', BulkUploadJobStatusEnum::PENDING());
    }

    public function scopeRunnable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BulkUploadJobStatusEnum::PENDING(),
            BulkUploadJobStatusEnum::PROCESSING(),
        ]);
    }

    public function isTerminal(): bool
    {
        // Compare scalar value to scalar values — the cast makes $this->status
        // a BulkUploadJobStatusEnum instance, while ::COMPLETED() returns the
        // underlying string. Strict in_array(instance, [strings]) is always false.
        $value = $this->status?->value;
        return in_array($value, [
            BulkUploadJobStatusEnum::COMPLETED(),
            BulkUploadJobStatusEnum::FAILED(),
            BulkUploadJobStatusEnum::CANCELLED(),
        ], true);
    }

    /**
     * Human-readable expiry text for the per-row errors CSV. Used by the
     * "Download failed rows" button's tooltip on the job detail page and
     * returned by the status-polling JSON so the tooltip stays current as the
     * job moves from PROCESSING into a terminal state.
     *
     * Returns null when there's nothing to label (no errors file on disk).
     */
    public function failedRowsExpiryLabel(): ?string
    {
        if (! $this->failed_rows_path) {
            return null;
        }

        $statusValue = $this->status?->value;
        $isCompleted = $statusValue === BulkUploadJobStatusEnum::COMPLETED();
        $isFailedOrCancelled = in_array($statusValue, [
            BulkUploadJobStatusEnum::FAILED(),
            BulkUploadJobStatusEnum::CANCELLED(),
        ], true);

        if (! $this->isTerminal() || ! $this->finished_at) {
            $completedDays = (int) config('bulk_upload.retention_days', 7);
            $failedDays = (int) config('bulk_upload.retention_failed_days', 30);

            return __('labels.bulk_upload_errors_expires_after_completion', [
                'days' => $completedDays,
                'failed_days' => $failedDays,
            ]);
        }

        $days = $isCompleted
            ? (int) config('bulk_upload.retention_days', 7)
            : ($isFailedOrCancelled ? (int) config('bulk_upload.retention_failed_days', 30) : null);

        if ($days === null) {
            return null;
        }

        return __('labels.bulk_upload_errors_expires_at', [
            'date' => $this->finished_at->copy()->addDays($days)->format('Y-m-d H:i'),
        ]);
    }
}
