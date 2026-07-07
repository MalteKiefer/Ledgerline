<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | File storage disk
    |--------------------------------------------------------------------------
    |
    | The Flysystem disk (see config/filesystems.php) uploaded files are stored
    | on. Defaults to the private S3-compatible "files" disk.
    |
    */

    'disk' => env('FILES_DISK', 'files'),

    /*
    |--------------------------------------------------------------------------
    | Maximum upload size (megabytes)
    |--------------------------------------------------------------------------
    */

    'max_upload_mb' => (int) env('FILES_MAX_UPLOAD_MB', 512),

    /*
    |--------------------------------------------------------------------------
    | Trash retention (days)
    |--------------------------------------------------------------------------
    |
    | How long a soft-deleted (trashed) file is kept before the daily
    | files:prune-trash command permanently purges it and its blobs.
    |
    */

    'trash_retention_days' => (int) env('FILES_TRASH_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Per-user storage quota (megabytes)
    |--------------------------------------------------------------------------
    |
    | Maximum bytes a single user may occupy (live + trashed files + kept
    | versions). 0 = unlimited.
    |
    */

    'quota_mb' => (int) env('FILES_QUOTA_MB', 0),

    /*
    |--------------------------------------------------------------------------
    | Archive (zip) limits
    |--------------------------------------------------------------------------
    |
    | Caps for creating and extracting zip archives in the file browser, to
    | bound memory/disk and blunt zip bombs. max_entries = number of files an
    | archive may hold/extract; max_mb = total uncompressed bytes.
    |
    */

    'archive_max_entries' => (int) env('FILES_ARCHIVE_MAX_ENTRIES', 5000),

    'archive_max_mb' => (int) env('FILES_ARCHIVE_MAX_MB', 2048),

    /*
    | Grace window before an orphaned (never-synced) blob is swept by
    | files:prune-trash. Was read by the command but previously undeclared.
    */
    'blob_orphan_grace_hours' => (int) env('FILES_BLOB_ORPHAN_GRACE_HOURS', 24),

];
