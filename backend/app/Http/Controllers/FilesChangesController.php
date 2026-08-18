<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\FileChangeSignal;
use App\Support\SseSlot;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Server-Sent Events stream that wakes a sync client up when Files changes
 * remotely, instead of it only ever finding out on its own fallback poll
 * interval (ledgerline-cli's `files sync --interval`, 5 minutes by default —
 * see internal/files/changes.go there for the client half of this). One
 * `data:` line whenever App\Support\FileChangeSignal's per-user marker moves
 * (bumped by App\Observers\FileChangeObserver on any FileEntry/FileFolder
 * create/update/delete/restore, from any code path — the REST API, WebDAV,
 * ...); an SSE comment line otherwise, every POLL_SECONDS, purely to keep
 * the connection alive through any intermediate proxy/load balancer. Runs
 * for at most MAX_SECONDS, then closes — the client is expected to just
 * reconnect, the same as any standard SSE consumer (browsers' own
 * EventSource does this natively; ledgerline-cli implements the same
 * reconnect-with-backoff loop by hand).
 *
 * MAX_SECONDS is deliberately short (not the ~5-minute proxy/LB ceiling it
 * could safely be) and concurrent streams are capped BOTH per user and
 * across all users combined via App\Support\SseSlot — see that class's doc
 * comment for why the global cap exists on top of the per-user one (this
 * app runs under Octane with a small, fixed worker pool, and this stream
 * blocks one worker for its entire lifetime; per-user alone does not bound
 * total exposure on a multi-user, multi-device system). All three together
 * bound how long, and how many at once, a worker can be tied up here —
 * without them, a handful of concurrent connections (whether from one
 * runaway client or several different users' sync clients) could exhaust
 * every worker and make the whole app (every other route) unresponsive.
 */
class FilesChangesController extends Controller
{
    // Short enough that a slot is only briefly unavailable to a second
    // connection attempt if SseSlot's cap is ever saturated; still well
    // under any typical proxy/LB idle timeout the client would otherwise
    // have to worry about.
    private const DEFAULT_MAX_SECONDS = 45;

    private const DEFAULT_POLL_SECONDS = 2;

    /**
     * $maxSeconds/$pollSeconds default to the real values above; the
     * container resolves this controller with no arguments (Laravel doesn't
     * need to know about them), so production is unaffected. Tests
     * construct this directly with tiny values instead, so exercising the
     * actual loop doesn't mean a test that runs for the real MAX_SECONDS.
     */
    public function __construct(
        private readonly int $maxSeconds = self::DEFAULT_MAX_SECONDS,
        private readonly int $pollSeconds = self::DEFAULT_POLL_SECONDS,
    ) {}

    public function stream(Request $request): Response
    {
        $userId = (int) $this->requireUser($request)->id;

        // Heartbeat TTL: comfortably longer than one poll tick (so a live
        // stream's own next heartbeat always lands before its slot could
        // expire) but short enough that a slot a crashed worker never
        // released self-heals quickly rather than staying stuck for the
        // rest of this stream's would-be MAX_SECONDS lifetime.
        $slotTtl = max(10, $this->pollSeconds * 5);
        $slot = SseSlot::acquire($userId, $slotTtl);
        if ($slot === null) {
            return response()->json(['error' => 'too_many_streams'], 503);
        }

        return response()->stream(function () use ($userId, $slot, $slotTtl): void {
            try {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                $deadline = microtime(true) + $this->maxSeconds;
                $lastSeen = FileChangeSignal::lastChangedAt($userId);

                echo "retry: 3000\n\n";
                $this->flushNow();

                while (microtime(true) < $deadline) {
                    if (connection_aborted()) {
                        return;
                    }
                    [$line, $lastSeen] = self::nextEvent($userId, $lastSeen);
                    echo $line;
                    $this->flushNow();
                    SseSlot::heartbeat($userId, $slot, $slotTtl);
                    if ($this->pollSeconds > 0) {
                        sleep($this->pollSeconds);
                    }
                }
            } finally {
                // Any exit path — the loop ran its course, an early return
                // on disconnect, or an uncaught exception — must free the
                // slot immediately rather than leaving it to expire on its
                // own TTL; the whole point of a small per-user cap is that
                // the next connection attempt (this stream's own client,
                // reconnecting right after this one closes) should not have
                // to wait out a stale lease it didn't need to.
                SseSlot::release($userId, $slot);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            // Caddy (this app's proxy — see docker/frankenphp) honours this to
            // skip its own response buffering, the same reason nginx deployments
            // set it; harmless if the layer in front doesn't understand it.
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * One poll tick's worth of decision-making, pulled out of the streaming
     * closure specifically so it's unit-testable without going anywhere near
     * Symfony's StreamedResponse/output-buffering machinery (see
     * FilesChangesStreamTest): compares $userId's current signal against
     * $lastSeen and returns [the SSE line to emit, the new $lastSeen to
     * carry forward].
     *
     * @return array{0: string, 1: ?float}
     */
    public static function nextEvent(int $userId, ?float $lastSeen): array
    {
        $now = FileChangeSignal::lastChangedAt($userId);
        if ($now !== null && $now !== $lastSeen) {
            return ['data: '.json_encode(['changedAt' => $now], JSON_THROW_ON_ERROR)."\n\n", $now];
        }

        return [": ping\n\n", $lastSeen];
    }

    private function flushNow(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }
}
