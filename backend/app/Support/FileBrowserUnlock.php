<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived permission to browse a host's filesystem.
 *
 * The file browser reads and overwrites any file on the target as root. That is
 * the same reach as the terminal, and it is far less obvious: a shell announces
 * what it is, a file listing looks harmless. So it is gated the same way — the
 * account password, checked against the hash, in exchange for a grant.
 *
 * A grant rather than a password on every request: asking for the password
 * before each click would train the operator to type it reflexively, which is
 * worse than asking once and expiring quickly. Fifteen minutes, bound to one
 * account and one server, so a leaked value opens nothing else and not for
 * long.
 */
final class FileBrowserUnlock
{
    /** How long a grant lasts. Long enough to work, short enough to matter. */
    private const TTL_SECONDS = 900;

    /** Issue a grant for this account on this server. */
    public static function issue(int $userId, int $serverId): string
    {
        $token = Str::random(48);
        Cache::put(self::key($userId, $serverId, $token), true, self::TTL_SECONDS);

        return $token;
    }

    /**
     * Is this grant valid here?
     *
     * Checked against account AND server: the token alone is not a capability,
     * which is the same rule the terminal session id follows.
     */
    public static function valid(int $userId, int $serverId, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return Cache::get(self::key($userId, $serverId, $token)) === true;
    }

    /** Give a grant back early — leaving the tab should not leave it open. */
    public static function revoke(int $userId, int $serverId, string $token): void
    {
        if ($token !== '') {
            Cache::forget(self::key($userId, $serverId, $token));
        }
    }

    private static function key(int $userId, int $serverId, string $token): string
    {
        return 'fb:'.$userId.':'.$serverId.':'.hash('sha256', $token);
    }
}
