<?php

declare(strict_types=1);

return [

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

    /*
    |--------------------------------------------------------------------------
    | SFTP transfer tuning
    |--------------------------------------------------------------------------
    |
    | A backup archive can be large and is staged from remote storage before the
    | upload, so the transfer runs long. phpseclib's default 10s timeout drops a
    | slow/large SFTP write mid-stream ("Connection closed prematurely"). Give the
    | session generous time + a couple of extra connect tries.
    |
    */

    'sftp_timeout' => (int) env('BACKUP_SFTP_TIMEOUT', 300),
    'sftp_max_tries' => (int) env('BACKUP_SFTP_MAX_TRIES', 5),

];
