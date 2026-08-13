<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Server-side password hashing uses Argon2id (PHP's PASSWORD_ARGON2ID via
    | libsodium). These parameters protect the PRIMARY credential at rest: the
    | first-party Fortify email+password login hash (User::casts() maps
    | `password => 'hashed'`). If the database ever leaks, this is the main
    | offline-cracking target, so a memory-hard KDF is chosen deliberately over
    | bcrypt — do NOT lower the cost. Hash::check() auto-detects the algorithm
    | from the stored hash prefix, so any pre-existing bcrypt hashes keep
    | verifying and only NEWLY created hashes use Argon2id.
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
    | Enabled so Fortify transparently re-hashes the login password with the
    | current Argon2id parameters on the user's next successful login whenever
    | those parameters change. Keeps stored hashes at the current cost.
    |
    */

    'rehash_on_login' => true,

];
