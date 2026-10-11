<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rows per cron tick
    |--------------------------------------------------------------------------
    | The scheduled bulk-uploads:run command processes at most this many rows
    | of a single job per tick. The job persists a cursor (processed_rows) so
    | a large file resumes across ticks.
    */
    'rows_per_tick' => env('BULK_UPLOAD_ROWS_PER_TICK', 300),

    /*
    |--------------------------------------------------------------------------
    | Logo download guards
    |--------------------------------------------------------------------------
    | When a row's logo_url is mapped, the importer downloads it via Guzzle.
    | These caps prevent a slow/large URL from blocking the tick.
    */
    'logo_download_timeout' => env('BULK_UPLOAD_LOGO_TIMEOUT', 10),
    'logo_max_bytes' => env('BULK_UPLOAD_LOGO_MAX_BYTES', 5 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    | Uploaded CSVs are stored on this disk under temp_dir. On terminal status
    | (completed / failed / cancelled) the file is deleted.
    */
    'file_disk' => env('BULK_UPLOAD_DISK', 'local'),
    'temp_dir' => 'bulk-uploads',

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    | Number of CSV rows (excluding header) returned for the mapping preview.
    | Larger files are still processed in full — this only caps what's shown
    | in the mapping UI.
    */
    'preview_rows' => env('BULK_UPLOAD_PREVIEW_ROWS', 50),

    /*
    |--------------------------------------------------------------------------
    | File size cap
    |--------------------------------------------------------------------------
    | Max CSV size accepted by the preview/upload endpoint, in kilobytes.
    */
    'max_file_kb' => 10240,

    /*
    |--------------------------------------------------------------------------
    | Retention (used by bulk-uploads:prune)
    |--------------------------------------------------------------------------
    | retention_days        — COMPLETED job rows older than this many days are
    |                         hard-deleted from bulk_upload_jobs.
    | retention_failed_days — FAILED / CANCELLED rows older than this are
    |                         hard-deleted. Kept longer than completed jobs
    |                         because the error log has debugging value.
    | orphan_csv_hours      — Files under temp_dir/ that don't belong to any
    |                         non-terminal job AND are older than this many
    |                         hours are deleted. Catches preview uploads the
    |                         admin never confirmed plus any artifact a
    |                         crashed mid-tick left behind.
    */
    'retention_days' => env('BULK_UPLOAD_RETENTION_DAYS', 7),
    'retention_failed_days' => env('BULK_UPLOAD_RETENTION_FAILED_DAYS', 30),
    'orphan_csv_hours' => env('BULK_UPLOAD_ORPHAN_CSV_HOURS', 6),
];
