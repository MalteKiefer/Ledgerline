<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Private, S3-compatible object storage for uploaded files. MinIO in
        // local development; Cloudflare R2 / S3 in production. The FILES_S3_*
        // variables take precedence, falling back to the standard AWS_* set so
        // a Laravel Cloud R2 bucket (which provides AWS_*) works unchanged.
        //
        // No object ACL/visibility is set: R2 rejects S3 ACLs, and files are
        // never publicly listable — every download streams through the app
        // behind team authorization, so the bucket stays private.
        'files' => [
            'driver' => 's3',
            'key' => env('FILES_S3_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('FILES_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('FILES_S3_REGION', env('AWS_DEFAULT_REGION', 'auto')),
            'bucket' => env('FILES_S3_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('FILES_S3_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('FILES_S3_USE_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', true)),
            // aws-sdk-php >= 3.337 sends integrity checksums (x-amz-checksum-*)
            // by default, which several S3-compatible providers (Hetzner Object
            // Storage, Backblaze B2, some MinIO builds) reject. Only send/verify
            // them when the operation actually requires it. Override per env if a
            // provider ever needs the stricter "when_supported".
            'request_checksum_calculation' => env('FILES_S3_CHECKSUM_CALCULATION', 'when_required'),
            'response_checksum_validation' => env('FILES_S3_CHECKSUM_VALIDATION', 'when_required'),
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
