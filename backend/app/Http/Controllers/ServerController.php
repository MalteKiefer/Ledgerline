<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CollectServerFacts;
use App\Models\Server;
use App\Models\ServerFact;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;

/**
 * Monitored servers, reached over plain SSH with no agent on the target.
 *
 * Two deliberate properties of this surface:
 *
 *  - Collection never happens in a request. `refresh` enqueues CollectServerFacts
 *    and the list renders the last recorded snapshot. The only SSH a request
 *    performs is the explicit "test connection", which runs with tight interactive
 *    timeouts because a user is synchronously waiting on it.
 *  - The host key is pinned. `test` returns the fingerprint it saw, the user
 *    confirms it, and `store` requires that value — so trust-on-first-use is an
 *    explicit human step rather than something the server does silently.
 *
 * Credentials are encrypted + #[Hidden] on the model and additionally never
 * placed in a response by present(). Rows are owner-scoped via OwnsUserData.
 */
class ServerController extends Controller
{
    /** Keep the trend history bounded — a snapshot every 15 min adds up. */
    private const HISTORY_LIMIT = 50;

    /** How long a generated keypair waits in the cache for the user to finish setup. */
    private const KEYPAIR_TTL_MINUTES = 30;

    public function __construct(private readonly ServerProbe $probe) {}

    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        $servers = Server::query()->with('latestFact')->orderBy('name')->get();

        return response()->json([
            'servers' => $servers->map(fn (Server $s): array => $this->present($s))->all(),
        ]);
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        $history = ServerFact::query()
            ->where('server_id', $server->id)
            ->orderByDesc('collected_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (ServerFact $f): array => $this->trendPoint($f))->all();

        return response()->json([
            'server' => [
                ...$this->present($server->load('latestFact')),
                // Derived, not stored: it is what the removal instructions have to
                // name so the right authorized_keys line is deleted and any other
                // key in that account survives.
                'public_key' => $this->publicKey($server),
            ],
            'history' => $history,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $data = $this->validated($request);

        // Re-read the host key here and check it against the fingerprint the user
        // confirmed. The pin is enforced by handing OpenSSH a known_hosts entry,
        // which needs the whole key; deriving it now also re-verifies the
        // confirmation instead of trusting a value the client sent back.
        $hostKey = $this->resolveHostKey($data['columns'], $data['fingerprint']);
        if ($hostKey === null) {
            return response()->json(['error' => 'host_key_unconfirmed'], 422);
        }

        $server = new Server;
        $server->forceFill([
            'user_id' => $uid,
            ...$data['columns'],
            'credentials' => $data['credentials'],
            'host_fingerprint' => $data['fingerprint'],
            'host_key' => $hostKey,
        ])->save();

        // First snapshot in the background, so the create request returns at once.
        CollectServerFacts::dispatch($server->id);

        return response()->json(['server' => $this->present($server)], 201);
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);
        $data = $this->validated($request, $server);

        // Only re-scan when the pin or the address actually moved — an edit that
        // just renames the server must not depend on the host being reachable.
        $hostKey = (string) $server->host_key;
        $moved = $data['fingerprint'] !== (string) $server->host_fingerprint
            || $data['columns']['host'] !== $server->host
            || $data['columns']['port'] !== $server->port;
        if ($moved || $hostKey === '') {
            $resolved = $this->resolveHostKey($data['columns'], $data['fingerprint']);
            if ($resolved === null) {
                return response()->json(['error' => 'host_key_unconfirmed'], 422);
            }
            $hostKey = $resolved;
        }

        $server->forceFill([
            ...$data['columns'],
            'credentials' => $data['credentials'],
            'host_fingerprint' => $data['fingerprint'],
            'host_key' => $hostKey,
        ])->save();

        return response()->json(['server' => $this->present($server->load('latestFact'))]);
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);
        $server->delete();

        return response()->json(['ok' => true]);
    }

    /** Queue a fresh probe. 202: the answer arrives in a later index/show. */
    public function refresh(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);
        CollectServerFacts::dispatch($server->id);

        return response()->json(['queued' => true], 202);
    }

    /** Queue a probe for every enabled server of this user. */
    public function refreshAll(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $queued = 0;
        foreach (Server::query()->where('enabled', true)->pluck('id') as $id) {
            if (! is_numeric($id)) {
                continue;
            }
            CollectServerFacts::dispatch((int) $id);
            $queued++;
        }

        return response()->json(['queued' => $queued], 202);
    }

    /**
     * Probe connection parameters that are not saved yet (the create dialog).
     * Returns the host key fingerprint so the user can confirm it before it is
     * pinned — this is the only place a request opens an SSH session.
     */
    public function test(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:64'],
            'auth_type' => ['sometimes', Rule::in(['key'])],
            'private_key' => ['nullable', 'string', 'max:16384'],
            'passphrase' => ['nullable', 'string', 'max:1024'],
            'host_fingerprint' => ['nullable', 'string', 'max:128'],
            'keypair_token' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->probe->run(new ServerTarget(
            host: $request->string('host')->value(),
            port: $request->integer('port', 22),
            username: $request->string('username')->value(),
            privateKey: $request->string('private_key')->value() ?: $this->keypairPrivateKey($request),
            passphrase: $request->string('passphrase')->value(),
            // Empty means "learn it" — the response carries what we saw.
            fingerprint: $request->string('host_fingerprint')->value(),
        ), interactive: true);

        return response()->json([
            'ok' => $result->ok,
            'error' => $result->error,
            'fingerprint' => $result->fingerprint,
            'facts' => $result->ok ? $result->facts : null,
            'duration_ms' => $result->durationMs,
        ], $result->ok ? 200 : 422);
    }

    /** Re-test a stored server with its stored credentials (also re-reads the host key). */
    public function testStored(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        $credentials = $server->credentials ?? [];
        $result = $this->probe->run(new ServerTarget(
            host: $server->host,
            port: $server->port,
            username: $server->username,
            privateKey: is_string($credentials['private_key'] ?? null) ? $credentials['private_key'] : '',
            passphrase: is_string($credentials['passphrase'] ?? null) ? $credentials['passphrase'] : '',
            fingerprint: (string) $server->host_fingerprint,
            hostKey: (string) $server->host_key,
        ), interactive: true);

        return response()->json([
            'ok' => $result->ok,
            'error' => $result->error,
            'fingerprint' => $result->fingerprint,
            'duration_ms' => $result->durationMs,
        ], $result->ok ? 200 : 422);
    }

    /**
     * Generate a fresh Ed25519 keypair for a server the user is about to add.
     *
     * Only the PUBLIC key is returned — the user pastes that into the target's
     * authorized_keys. The private half waits in the cache under an opaque token
     * and is picked up by /servers/test and /servers, so a key this host
     * generated never travels to the browser at all.
     *
     * Ed25519 because it is short enough to paste in one line and is accepted by
     * every OpenSSH an operator is realistically running.
     */
    public function keypair(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $key = EC::createKey('Ed25519');
        $token = Str::random(40);

        // phpseclib types neither getPublicKey() nor toString() tightly; narrow
        // rather than assume, so a library change surfaces here instead of
        // silently storing an unusable key.
        $public = $key->getPublicKey();
        $privatePem = $this->openSsh($key);
        $publicLine = $public instanceof AsymmetricKey ? $this->openSsh($public) : null;
        if ($privatePem === null || $publicLine === null) {
            return response()->json(['error' => 'keygen_failed'], 500);
        }

        Cache::put($this->keypairKey($uid, $token), $privatePem, now()->addMinutes(self::KEYPAIR_TTL_MINUTES));

        return response()->json([
            'token' => $token,
            'public_key' => $publicLine,
            'expires_in_minutes' => self::KEYPAIR_TTL_MINUTES,
        ]);
    }

    /**
     * Read the target's host key and accept it only if it matches the fingerprint
     * the user confirmed. Returns null when the host is unreachable or answers
     * with a different key — either way the server is not saved, because a pin
     * nobody verified is not a pin.
     *
     * @param  array<string, mixed>  $columns
     */
    private function resolveHostKey(array $columns, string $fingerprint): ?string
    {
        $result = $this->probe->run(new ServerTarget(
            host: is_string($columns['host'] ?? null) ? $columns['host'] : '',
            port: is_int($columns['port'] ?? null) ? $columns['port'] : 22,
            username: is_string($columns['username'] ?? null) ? $columns['username'] : '',
            fingerprint: $fingerprint,
        ), interactive: true);

        // A missing credential is fine here — the scan happens before auth, so a
        // matching fingerprint plus a key is all this step needs.
        return $result->fingerprint === $fingerprint ? $result->hostKey : null;
    }

    /**
     * The public half of the stored key, in the one-line form authorized_keys
     * uses. Derived on demand rather than stored: it is a pure function of the
     * private key, and a second copy could only ever drift.
     */
    private function publicKey(Server $server): ?string
    {
        $credentials = $server->credentials ?? [];
        $private = is_string($credentials['private_key'] ?? null) ? $credentials['private_key'] : '';
        if (trim($private) === '') {
            return null;
        }
        try {
            $passphrase = is_string($credentials['passphrase'] ?? null) ? $credentials['passphrase'] : '';
            $public = PublicKeyLoader::loadPrivateKey($private, $passphrase)->getPublicKey();

            return $public instanceof AsymmetricKey ? $this->openSsh($public) : null;
        } catch (Throwable) {
            // A key we cannot parse simply yields no removal hint; the rest of
            // the detail view must still render.
            return null;
        }
    }

    /** phpseclib's toString() is documented as array|string; OpenSSH gives a string. */
    private function openSsh(AsymmetricKey $key): ?string
    {
        $out = $key->toString('OpenSSH');

        return is_string($out) ? $out : null;
    }

    /**
     * The private key a request wants to use: one the user pasted, or the one we
     * generated for them a moment ago. Scoped to the caller, so a token cannot be
     * redeemed by anyone else.
     */
    private function keypairPrivateKey(Request $request): string
    {
        $token = $request->string('keypair_token')->value();
        if ($token === '') {
            return '';
        }
        $cached = Cache::get($this->keypairKey((int) $this->requireUser($request)->id, $token));

        return is_string($cached) ? $cached : '';
    }

    private function keypairKey(int $userId, string $token): string
    {
        return "servers.keypair.{$userId}.{$token}";
    }

    /**
     * The probe script an operator installs on the target when restricting the
     * key with a forced command. Served from the same constant the collector
     * runs, so the two can never drift apart.
     */
    public function probeScript(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json(['script' => ServerProbe::PROBE]);
    }

    // ---- internals ----

    /**
     * Validate and split the payload into columns, credentials and the host-key pin.
     *
     * On update a blank secret preserves the stored one, so the client never has
     * to round-trip a credential it is not allowed to read back.
     *
     * @return array{columns: array<string, mixed>, credentials: array<string, string>, fingerprint: string}
     */
    private function validated(Request $request, ?Server $server = null): array
    {
        $creating = $server === null;

        $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'host' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            // Monitoring needs no privilege, and a key that logs in as root hands
            // over the whole machine if it is ever stolen. The UI says so where
            // the name is entered; it is not refused, because the operator may
            // have a host where root is the only account that exists.
            'username' => [$creating ? 'required' : 'sometimes', 'string', 'max:64'],
            // Key only. OpenSSH takes no password without a terminal, and adding
            // sshpass to drive a pty would be a worse answer than generating a key,
            // which this app does for the user anyway.
            'auth_type' => ['sometimes', Rule::in(['key'])],
            'private_key' => ['nullable', 'string', 'max:16384'],
            'passphrase' => ['nullable', 'string', 'max:1024'],
            // Required on create: the pin comes from the test step the user just
            // confirmed. Accepting a server without one would make the very first
            // credential-carrying connection interceptable.
            'host_fingerprint' => [$creating ? 'required' : 'sometimes', 'string', 'regex:/^SHA256:[A-Za-z0-9+\/]{43}$/'],
            'group' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['sometimes', 'boolean'],
            'restricted_key' => ['sometimes', 'boolean'],
            'account_created' => ['sometimes', 'boolean'],
            'keypair_token' => ['nullable', 'string', 'max:64'],
        ]);

        $old = $server?->credentials ?? [];
        // A generated key is redeemed from the cache, so the browser never had to
        // hold it; a pasted one wins over it, and a blank field keeps the stored
        // secret rather than wiping it.
        $generated = $this->keypairPrivateKey($request);
        $keep = static function (string $key) use ($request, $old, $generated): string {
            $new = $request->string($key)->value();
            if ($new === '' && $key === 'private_key') {
                $new = $generated;
            }

            return $new !== '' ? $new : (is_string($old[$key] ?? null) ? $old[$key] : '');
        };

        $credentials = ['private_key' => $keep('private_key'), 'passphrase' => $keep('passphrase')];

        return [
            'columns' => [
                'name' => $request->has('name') ? $request->string('name')->value() : ($server?->name ?? ''),
                'host' => $request->has('host') ? $request->string('host')->value() : ($server?->host ?? ''),
                'port' => $request->integer('port', $server?->port ?? 22),
                'username' => $request->has('username') ? $request->string('username')->value() : ($server?->username ?? ''),
                'auth_type' => 'key',
                'group' => $request->has('group') ? ($request->string('group')->value() ?: null) : $server?->group,
                'note' => $request->has('note') ? ($request->string('note')->value() ?: null) : $server?->note,
                'enabled' => $request->boolean('enabled', $server?->enabled ?? true),
                'restricted_key' => $request->boolean('restricted_key', $server?->restricted_key ?? false),
                // Recorded once, at setup: it decides which removal path the
                // detail page may offer without the reader having to guess.
                'account_created' => $request->has('account_created')
                    ? $request->boolean('account_created')
                    : $server?->account_created,
            ],
            'credentials' => $credentials,
            'fingerprint' => $request->has('host_fingerprint')
                ? $request->string('host_fingerprint')->value()
                : (string) ($server?->host_fingerprint ?? ''),
        ];
    }

    /**
     * One point of the trend series — only what a chart draws, not the whole
     * snapshot for each of the fifty rows.
     *
     * @return array<string, mixed>
     */
    private function trendPoint(ServerFact $fact): array
    {
        $facts = $fact->facts ?? [];
        $mem = is_array($facts['mem'] ?? null) ? $facts['mem'] : [];

        return [
            'ok' => $fact->ok,
            'error' => $fact->error,
            'collected_at' => $fact->collected_at->toIso8601String(),
            'duration_ms' => $fact->duration_ms,
            'load' => is_array($facts['load'] ?? null) ? array_values($facts['load']) : [],
            'mem_used_pct' => is_numeric($mem['used_pct'] ?? null) ? (float) $mem['used_pct'] : null,
            'disk_max_pct' => is_numeric($facts['disk_max_pct'] ?? null) ? (float) $facts['disk_max_pct'] : null,
        ];
    }

    /**
     * The client-facing shape. Credentials are absent by construction — this
     * whitelist, on top of the model's #[Hidden] cast, is what the guard test
     * for secret exposure backs up.
     *
     * @return array<string, mixed>
     */
    private function present(Server $server): array
    {
        $fact = $server->latestFact;

        return [
            'id' => $server->id,
            'name' => $server->name,
            'host' => $server->host,
            'port' => $server->port,
            'username' => $server->username,
            'auth_type' => $server->auth_type,
            'group' => $server->group,
            'note' => $server->note,
            'enabled' => $server->enabled,
            'restricted_key' => $server->restricted_key,
            'account_created' => $server->account_created,
            'host_fingerprint' => $server->host_fingerprint,
            'status' => $fact === null ? null : [
                'ok' => $fact->ok,
                'error' => $fact->error,
                'collected_at' => $fact->collected_at->toIso8601String(),
                'duration_ms' => $fact->duration_ms,
            ],
            'facts' => $fact?->ok === true ? $fact->facts : null,
        ];
    }
}
