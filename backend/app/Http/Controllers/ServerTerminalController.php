<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunServerTerminal;
use App\Models\AuditLog;
use App\Models\Server;
use App\Support\TerminalChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Open and drive an interactive shell on a monitored host.
 *
 * The one place in this application where what runs on a remote machine is
 * decided by whoever is typing. Everything here is the fence around that:
 *
 *  - The account password is required to OPEN a session, every single time, and
 *    is never remembered. A stolen device token alone cannot get a shell.
 *  - Opening is audited, with the server named. A shell that leaves no trace is
 *    worse than no shell.
 *  - Sessions are capped in number, in lifetime and in idle time.
 *
 * Bytes travel base64-encoded in both directions, and that is not ceremony.
 * A terminal is a byte pipe: keystrokes include control characters (Ctrl-C is
 * 0x03, arrow keys are escape sequences) and output can be any byte at all, so
 * a program that prints binary would produce a JSON body that json_encode
 * cannot represent and the whole session would die. Encoding also puts the
 * payload out of reach of the global TrimStrings middleware, which would
 * otherwise eat the newline that submits every command.
 *
 * Polling rather than a stream. This runtime serves requests from a fixed pool
 * of workers and a long-lived stream pins one for the session's duration; the
 * app has already been taken down that way once. Short polls cost a little
 * latency and cannot exhaust the pool.
 */
class ServerTerminalController extends Controller
{
    /** Two at a time. A third is far more likely a mistake than a need. */
    private const MAX_PER_USER = 2;

    /** Open a session. Requires the account password, every time. */
    public function open(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'current_password' => ['required', 'string'],
            'cols' => ['sometimes', 'integer', 'min:20', 'max:500'],
            'rows' => ['sometimes', 'integer', 'min:5', 'max:200'],
        ]);

        // Deliberately unconditional: not "unless recently confirmed", not
        // "unless this is a native device". A shell is the one action where the
        // convenience of skipping this is not worth what it buys an attacker.
        if (! Hash::check($request->string('current_password')->value(), (string) $user->password)) {
            return response()->json(['error' => 'password_invalid'], 422);
        }

        if ($this->liveSessions($user->id) >= self::MAX_PER_USER) {
            return response()->json(['error' => 'too_many_sessions'], 429);
        }

        $channel = TerminalChannel::open($user->id, $server->id);
        $this->trackSession($user->id, $channel->id);

        AuditLog::record('server.terminal_opened', $server, [
            'server' => $server->name,
            'host' => $server->host,
        ], $user->id);

        RunServerTerminal::dispatch(
            $server->id,
            $channel->id,
            $request->integer('cols', 80),
            $request->integer('rows', 24),
        );

        return response()->json(['session' => $channel->id], 201)->header('Cache-Control', 'no-store');
    }

    /** Read whatever the shell has produced since the given cursor. */
    public function poll(Request $request, Server $server, string $session): JsonResponse
    {
        $channel = $this->resolve($request, $server, $session);
        if ($channel === null) {
            return response()->json(['error' => 'no_session'], 404);
        }

        // Polling is what proves somebody is still watching; the job reads this
        // to decide whether to keep the shell open.
        $channel->touch();

        $out = $channel->readOutput($request->integer('cursor'));

        return response()->json([
            'ready' => $channel->isReady(),
            'data' => base64_encode($out['data']),
            'cursor' => $out['cursor'],
            'closed' => $channel->closedReason(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Send keystrokes. */
    public function input(Request $request, Server $server, string $session): JsonResponse
    {
        $channel = $this->resolve($request, $server, $session);
        if ($channel === null) {
            return response()->json(['error' => 'no_session'], 404);
        }

        $request->validate(['data' => ['required', 'string', 'max:8192']]);

        // strict: a body that is not valid base64 is a client error, not
        // something to silently pass half of to a shell.
        $decoded = base64_decode($request->string('data')->value(), true);
        if ($decoded === false) {
            return response()->json(['error' => 'bad_input'], 422);
        }

        $channel->touch();
        $channel->pushInput($decoded);

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    /** Close the session. The job notices on its next tick and stops the shell. */
    public function close(Request $request, Server $server, string $session): JsonResponse
    {
        $channel = $this->resolve($request, $server, $session);
        if ($channel === null) {
            return response()->json(['error' => 'no_session'], 404);
        }

        $channel->close('closed');

        return response()->json(['ok' => true]);
    }

    /**
     * A session belongs to one user and one server, and both are checked on
     * every call: the id alone is not enough to speak to somebody else's shell.
     */
    private function resolve(Request $request, Server $server, string $session): ?TerminalChannel
    {
        $user = $this->requireUser($request);

        // The route already restricts the shape, but this is the value that
        // indexes the cache, so it is checked here too.
        if (preg_match('/^[A-Za-z0-9]{48}$/', $session) !== 1) {
            return null;
        }

        $channel = new TerminalChannel($session);
        $meta = $channel->meta();
        if ($meta === null || $meta['user_id'] !== $user->id || $meta['server_id'] !== $server->id) {
            return null;
        }

        return $channel;
    }

    /** How many of this user's sessions are still alive. */
    private function liveSessions(int $userId): int
    {
        $ids = Cache::get($this->sessionsKey($userId));
        if (! is_array($ids)) {
            return 0;
        }

        $live = 0;
        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }
            $channel = new TerminalChannel($id);
            if ($channel->meta() !== null && $channel->closedReason() === null) {
                $live++;
            }
        }

        return $live;
    }

    private function trackSession(int $userId, string $id): void
    {
        $ids = Cache::get($this->sessionsKey($userId));
        $ids = is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
        $ids[] = $id;
        // Only the most recent handful matter; anything older has expired anyway.
        Cache::put($this->sessionsKey($userId), array_slice($ids, -8), TerminalChannel::MAX_LIFETIME);
    }

    private function sessionsKey(int $userId): string
    {
        return 'srv-term-sessions:'.$userId;
    }
}
