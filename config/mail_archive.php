<?php

declare(strict_types=1);

return [
    /*
    | How many new messages to fetch PER FOLDER on each sync run (newest first),
    | so every folder makes progress each run instead of one large folder eating
    | the whole run. A big folder drains over successive runs.
    */
    'per_run_cap' => (int) env('MAIL_ARCHIVE_PER_RUN_CAP', 100),

    /*
    | Global wall-clock budget for a whole sync run (seconds), across all
    | accounts and folders. Fetching stops once this is reached even if per-folder
    | caps were not, so a slow/large mailbox never makes a run drag on; the rest
    | drains over the next runs.
    */
    'max_run_seconds' => (int) env('MAIL_ARCHIVE_MAX_RUN_SECONDS', 300),

    /*
    | Largest stored .eml (bytes) the app will load into memory to render,
    | download an attachment from, or re-append on restore. A hostile message
    | with a huge attachment would otherwise OOM the PHP-FPM worker; past this
    | size the request is refused (413) rather than read. Default 25 MiB.
    */
    'max_render_bytes' => (int) env('MAIL_ARCHIVE_MAX_RENDER_BYTES', 25 * 1024 * 1024),
];
