<?php

declare(strict_types=1);

return [
    /*
     * Maximum number of paired mobile devices (Sanctum tokens) a user may hold.
     * When a new device is paired beyond this limit, the oldest is revoked.
     */
    'max' => (int) env('PAIRING_MAX_DEVICES', 3),
];
