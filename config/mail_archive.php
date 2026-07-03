<?php

declare(strict_types=1);

return [
    /*
    | How many new messages to fetch per folder on each sync run. A large
    | mailbox drains over successive hourly runs (newest first), keeping any
    | single run bounded.
    */
    'per_run_cap' => (int) env('MAIL_ARCHIVE_PER_RUN_CAP', 1000),
];
