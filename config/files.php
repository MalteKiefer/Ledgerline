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
    | Per-user storage quota (megabytes)
    |--------------------------------------------------------------------------
    |
    | Maximum bytes a single user may occupy (all stored content blobs).
    | 0 = unlimited.
    |
    */

    'quota_mb' => (int) env('FILES_QUOTA_MB', 0),

    /*
    | Grace window (hours) before an orphaned (never-synced) blob is eligible
    | for sweeping. No active sweeper command currently runs; the value is an
    | AppSettings-backed default (see AppServiceProvider SETTING_OVERRIDES).
    */
    'blob_orphan_grace_hours' => (int) env('FILES_BLOB_ORPHAN_GRACE_HOURS', 24),

];
