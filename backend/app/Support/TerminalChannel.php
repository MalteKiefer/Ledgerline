<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The pipe between a browser and the shell a queue worker is holding open.
 *
 * Two processes that never share memory: the job owns the SSH subprocess, the
 * web request owns the HTTP round trip. Everything between them goes through
 * the cache.
 *
 * Chunks are numbered and stored under their own keys rather than appended to a
 * list, so this works on every cache driver — a list would mean Redis-only
 * primitives, and the tests would then be testing a different mechanism than
 * production uses. Each side keeps its own cursor and reads forward.
 *
 * Everything is short-lived by construction: a session that stops being polled
 * expires, and with it every byte of whatever was on screen.
 */
class TerminalChannel
{
    /** A session cannot outlive this even if both ends stay interested. */
    public const MAX_LIFETIME = 1800;

    /** No poll and no keystroke for this long means nobody is watching. */
    public const IDLE_TIMEOUT = 120;

    /** Long enough for a reader that is a few chunks behind, short enough not to linger. */
    private const CHUNK_TTL = 300;

    public function __construct(public string $id) {}

    public static function open(int $userId, int $serverId): self
    {
        // 32 bytes of randomness: the id is the capability for the rest of the
        // session, alongside the owner check on every call.
        $channel = new self(Str::random(48));
        Cache::put($channel->key('meta'), [
            'user_id' => $userId,
            'server_id' => $serverId,
            'started_at' => time(),
        ], self::MAX_LIFETIME);
        $channel->touch();

        return $channel;
    }

    /**
     * @return array{user_id:int,server_id:int,started_at:int}|null
     */
    public function meta(): ?array
    {
        $meta = Cache::get($this->key('meta'));
        if (! is_array($meta) || ! is_int($meta['user_id'] ?? null) || ! is_int($meta['server_id'] ?? null)) {
            return null;
        }

        $started = $meta['started_at'] ?? null;

        return [
            'user_id' => $meta['user_id'],
            'server_id' => $meta['server_id'],
            'started_at' => is_int($started) ? $started : 0,
        ];
    }

    /** The job announces it has a shell; until then the browser is still waiting on a worker. */
    public function markReady(): void
    {
        Cache::put($this->key('ready'), true, self::MAX_LIFETIME);
    }

    public function isReady(): bool
    {
        return Cache::get($this->key('ready')) === true;
    }

    /**
     * Close the session. Both ends do this: the browser when the user leaves,
     * the job when the shell exits. Whoever is second finds it already closed.
     */
    public function close(?string $reason = null): void
    {
        Cache::put($this->key('closed'), $reason ?? 'closed', self::MAX_LIFETIME);
    }

    public function closedReason(): ?string
    {
        $value = Cache::get($this->key('closed'));

        return is_string($value) ? $value : null;
    }

    /** Someone is still there. Read by the job to decide whether to keep the shell open. */
    public function touch(): void
    {
        Cache::put($this->key('seen'), time(), self::MAX_LIFETIME);
    }

    public function idleSeconds(): int
    {
        $seen = Cache::get($this->key('seen'));

        return is_int($seen) ? time() - $seen : 0;
    }

    /** Terminal output, from the job towards the browser. */
    public function pushOutput(string $data): void
    {
        $this->push('out', $data);
    }

    /**
     * @return array{data:string,cursor:int}
     */
    public function readOutput(int $cursor): array
    {
        return $this->read('out', $cursor);
    }

    /** Keystrokes, from the browser towards the job. */
    public function pushInput(string $data): void
    {
        $this->push('in', $data);
    }

    /**
     * @return array{data:string,cursor:int}
     */
    public function readInput(int $cursor): array
    {
        return $this->read('in', $cursor);
    }

    private function push(string $lane, string $data): void
    {
        if ($data === '') {
            return;
        }
        // increment() is atomic on Redis, which is what makes two writers safe;
        // the array driver used in tests is single-process, so it agrees.
        $n = Cache::increment($this->key($lane.':seq'));
        $n = is_int($n) ? $n : 1;
        Cache::put($this->key($lane.':'.$n), $data, self::CHUNK_TTL);
    }

    /**
     * Read everything from the cursor forward, coalesced. A gap means a chunk
     * expired before it was read — the reader had stopped reading, so the run
     * ends there rather than silently splicing together output from either side
     * of a hole.
     *
     * @return array{data:string,cursor:int}
     */
    private function read(string $lane, int $cursor): array
    {
        $seq = Cache::get($this->key($lane.':seq'));
        $seq = is_int($seq) ? $seq : 0;

        $data = '';
        $at = max(0, $cursor);
        while ($at < $seq) {
            $chunk = Cache::get($this->key($lane.':'.($at + 1)));
            if (! is_string($chunk)) {
                break;
            }
            $data .= $chunk;
            $at++;
            // Bound one response; the rest arrives on the next poll.
            if (strlen($data) > 256 * 1024) {
                break;
            }
        }

        return ['data' => $data, 'cursor' => $at];
    }

    private function key(string $suffix): string
    {
        return 'srv-term:'.$this->id.':'.$suffix;
    }
}
