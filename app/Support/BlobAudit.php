<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BlobAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Canonical, secret-free forensic recorder for blob/shard lifecycle events. EVERY
 * mutation of a content or record-shard blob — and every sealed sharded-store root
 * write/reject — routes through here, so a future data-loss incident can be traced
 * end to end: who (user_id), when (created_at), what (action + blob ref), from
 * where (source web/api/command + ip), why (reason), and the result, plus a sha256
 * over the ciphertext (create) or the sealed root and its shard set (root_write).
 *
 * Zero-knowledge is preserved: blob refs are non-secret UUIDs, hashes are over
 * CIPHERTEXT (never plaintext/keys), sizes are the already-padded stored bytes.
 * Best-effort: an audit failure must never break the operation being audited.
 */
final class BlobAudit
{
    /** Hash create uploads only up to this size (covers every record shard / collection / meta blob). */
    public const HASH_MAX_BYTES = 1_048_576; // 1 MiB

    /**
     * @param  array{
     *     blob?: ?string, size?: ?int, sha256?: ?string, user_id?: ?int, source?: ?string,
     *     reason?: ?string, result?: string, meta?: array<string, mixed>|null
     * }  $opts
     */
    public static function record(string $action, string $module, array $opts = []): void
    {
        try {
            $uid = $opts['user_id'] ?? Auth::id();

            BlobAuditLog::create([
                'user_id' => is_numeric($uid) ? (int) $uid : null,
                'module' => $module,
                'action' => $action,
                'blob' => $opts['blob'] ?? null,
                'size' => $opts['size'] ?? null,
                'sha256' => $opts['sha256'] ?? null,
                'source' => $opts['source'] ?? self::source(),
                'reason' => $opts['reason'] ?? null,
                'result' => $opts['result'] ?? 'ok',
                'meta' => self::withIp($opts['meta'] ?? null),
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable) {
            // Forensic logging is best-effort; never break the audited action.
        }
    }

    /** sha256 (hex) of a stored file, but only when small enough to be a shard/collection blob. */
    public static function hashSmallFile(string $path, int $size): ?string
    {
        if ($size > self::HASH_MAX_BYTES) {
            return null;
        }
        try {
            $h = hash_file('sha256', $path);

            return $h === false ? null : $h;
        } catch (\Throwable) {
            return null;
        }
    }

    /** sha256 (hex) of an in-memory ciphertext string (the sealed root). */
    public static function hashString(string $ciphertext): string
    {
        return hash('sha256', $ciphertext);
    }

    /**
     * Stable sha256 over a set of shard refs (order-independent) so a root_write's
     * shard set can be fingerprinted and compared across versions — the key signal
     * for spotting the exact write that dropped a shard.
     *
     * @param  iterable<int, string>  $refs
     */
    public static function shardSetHash(iterable $refs): string
    {
        $list = [];
        foreach ($refs as $r) {
            if (is_string($r) && $r !== '') {
                $list[] = $r;
            }
        }
        sort($list);

        return hash('sha256', implode("\n", $list));
    }

    private static function source(): string
    {
        if (App::runningInConsole()) {
            return 'command';
        }

        return RequestFacade::is('api/*') ? 'api' : 'web';
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    private static function withIp(?array $meta): ?array
    {
        if (App::runningInConsole()) {
            return $meta;
        }
        try {
            $ip = RequestFacade::ip();
            if ($ip !== null && $ip !== '') {
                $meta = ($meta ?? []) + ['ip' => $ip];
            }
        } catch (\Throwable) {
            // ignore
        }

        return $meta;
    }
}
