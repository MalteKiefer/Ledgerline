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

    /*
    |--------------------------------------------------------------------------
    | Reachability check retention
    |--------------------------------------------------------------------------
    |
    | How many days of ping and port results to keep. This table gains rows
    | continuously — a handful per server every few minutes — so the window is
    | what bounds its size. Nothing here is worth keeping past it: current state
    | is always the newest row.
    |
    */

    'check_retention_days' => (int) env('SERVERS_CHECK_RETENTION_DAYS', 30),

];
