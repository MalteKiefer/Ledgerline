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

];
