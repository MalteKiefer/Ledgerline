<?php

declare(strict_types=1);

return [
    /*
     * Maximum byte size accepted for a sealed vault manifest (personal or
     * shared). Override via VAULT_MANIFEST_MAX_BYTES in .env.
     */
    'manifest_max_bytes' => (int) env('VAULT_MANIFEST_MAX_BYTES', 16_000_000),
];
