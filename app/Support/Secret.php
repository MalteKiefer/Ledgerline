<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Env;

/**
 * Read a secret from a mounted file when a `<KEY>_FILE` variable points at one
 * (the Docker-secret convention), else fall back to the plain `<KEY>` env var.
 *
 * Using this in config keeps a secret out of the container's environment
 * entirely — it is never in `docker inspect` or `/proc/<pid>/environ`, only on
 * the read-only secret mount that Laravel reads at config time. Backward
 * compatible: with no `<KEY>_FILE` set it behaves exactly like env().
 */
final class Secret
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $file = Env::get($key.'_FILE');
        if (is_string($file) && $file !== '' && is_readable($file)) {
            $value = file_get_contents($file);
            if ($value !== false) {
                // Trim a single trailing newline (common in secret files) without
                // stripping a password that legitimately ends in whitespace.
                return preg_replace('/\r?\n$/', '', $value);
            }
        }

        return Env::get($key, $default);
    }
}
