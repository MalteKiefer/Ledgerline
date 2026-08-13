<?php

declare(strict_types=1);

return [
    /*
     * Maximum number of paired mobile devices (Sanctum tokens) a user may hold.
     * When a new device is paired beyond this limit, the oldest is revoked.
     */
    'max' => (int) env('PAIRING_MAX_DEVICES', 3),

    /*
     * Remote wipe: after this grace window (minutes) a token flagged for wipe is
     * hard-revoked on its next request. The grace lets the client fetch the flag
     * via /me or the heartbeat and self-erase its local state first.
     */
    'wipe_grace_minutes' => (int) env('DEVICE_WIPE_GRACE_MINUTES', 15),

    /*
     * Idle expiry: a device token unused for this many days is revoked on next
     * contact. 0 disables the idle check (the absolute Sanctum expiration still
     * applies). A stolen but idle token dies without an owner action.
     */
    'idle_days' => (int) env('DEVICE_IDLE_DAYS', 90),
];
