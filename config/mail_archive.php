<?php

declare(strict_types=1);

return [
    /*
    | How many new messages to fetch per folder on each sync run. A large
    | mailbox drains over successive hourly runs (newest first), keeping any
    | single run bounded.
    */
    'per_run_cap' => (int) env('MAIL_ARCHIVE_PER_RUN_CAP', 1000),

    /*
    | Largest stored .eml (bytes) the app will load into memory to render,
    | download an attachment from, or re-append on restore. A hostile message
    | with a huge attachment would otherwise OOM the PHP-FPM worker; past this
    | size the request is refused (413) rather than read. Default 25 MiB.
    */
    'max_render_bytes' => (int) env('MAIL_ARCHIVE_MAX_RENDER_BYTES', 25 * 1024 * 1024),
];
