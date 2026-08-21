<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Snapshot retention
    |--------------------------------------------------------------------------
    |
    | How many days of per-server snapshots to keep for the trend charts. The
    | newest snapshot per server is never pruned — it is the live status.
    |
    */

    'fact_retention_days' => (int) env('SERVERS_FACT_RETENTION_DAYS', 30),

];
