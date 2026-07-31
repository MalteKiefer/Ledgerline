<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mirror reconcile window
    |--------------------------------------------------------------------------
    |
    | A file/gallery mirror job normally does a fast incremental pass: it uploads
    | only blobs created since its high-water mark (from the blob ledger) and
    | never scans the whole disk or the whole destination. That means blobs
    | deleted at the source are not pruned by the incremental pass. Once this many
    | hours have elapsed since the last full pass, the job does one full
    | list-and-prune reconcile instead, which removes vanished blobs and closes
    | any gaps. Set to 0 to force a full reconcile every run (the old behaviour).
    |
    */

    'reconcile_hours' => (int) env('BACKUP_RECONCILE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Backup archive passphrase
    |--------------------------------------------------------------------------
    |
    | The passphrase that encrypts backup archives (incl. the sensitive DB dump)
    | can be supplied here instead of stored per-job in the database. When set, it
    | takes precedence over any DB-stored passphrase and is used for every job, so
    | the key that protects the archives never lives in the same database that
    | gets dumped into them. Prefer a Docker secret / file-based env for this.
    | Leave empty to keep the legacy per-job DB passphrase behaviour.
    |
    */

    'passphrase' => env('BACKUP_PASSPHRASE'),

];
