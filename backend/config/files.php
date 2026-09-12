<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | File storage disk
    |--------------------------------------------------------------------------
    |
    | The Flysystem disk (see config/filesystems.php) blobs are stored on:
    | invoice/quote PDFs, receipts, company logos, avatars. Defaults to the
    | private S3-compatible "files" disk.
    |
    */

    'disk' => env('FILES_DISK', 'files'),

    /*
    |--------------------------------------------------------------------------
    | Maximum upload size (megabytes)
    |--------------------------------------------------------------------------
    |
    | Used by the Finance module's blob uploads (receipts, invoice/quote PDFs).
    */

    'max_upload_mb' => (int) env('FILES_MAX_UPLOAD_MB', 512),

];
