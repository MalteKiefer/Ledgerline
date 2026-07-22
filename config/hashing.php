<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Server-side password hashing uses Argon2id (PHP's PASSWORD_ARGON2ID via
    | libsodium). This app is zero-knowledge, so the only server-side password
    | hash is the optional public-share password gate (a rate-limited access
    | control, never the encryption root). We still prefer a memory-hard KDF
    | over bcrypt for that gate. Hash::check() auto-detects the algorithm from
    | the stored hash prefix, so pre-existing bcrypt hashes keep verifying and
    | only NEWLY created hashes use Argon2id.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Calibrated per spec §5/§24 to land in the ~500ms–1000ms range on the
    | production server hardware. Starting point: memory 65536 KiB (64 MiB),
    | time 4 iterations, threads 1. These are conservative libsodium-friendly
    | defaults; re-measure with `password_hash('x', PASSWORD_ARGON2ID, [...])`
    | on the target host and tune the env overrides if the timing drifts.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Not used by this app (no server-side login password; OIDC handles auth).
    | Kept for framework completeness.
    |
    */

    'rehash_on_login' => true,

];
