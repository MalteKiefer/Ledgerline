<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CryptoRecipient;
use App\Models\KeyServer;
use App\Models\MailPgpKey;
use App\Rules\SafeUrl;
use App\Support\Crypto\FileCipher;
use App\Support\Crypto\HkpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * A user's configured HKP public-keyservers (App\Models\KeyServer) — CRUD,
 * search, import a search result as a saved recipient (App\Models\
 * CryptoRecipient), refresh an already-saved one, publish an own PGP key, and
 * check whether an own key is already published. All outbound requests run
 * through App\Support\Crypto\HkpClient (SSRF-guarded via OutboundUrl); S/MIME
 * has no keyserver equivalent, so publish/check-presence/search are PGP-only.
 */
class KeyServerController extends Controller
{
    // search()/checkPresence() visit every enabled keyserver sequentially and
    // synchronously (HkpClient::TIMEOUT's doc) — this bounds the worst case
    // regardless of how many a single account has configured.
    private const MAX_SERVERS_PER_REQUEST = 5;

    public function __construct(private readonly HkpClient $hkp, private readonly FileCipher $cipher) {}

    public function index(Request $request): JsonResponse
    {
        $servers = KeyServer::query()->ownedBy($this->requireUser($request)->id)
            ->orderBy('name')->get(['id', 'name', 'url', 'enabled']);

        return response()->json(['servers' => $servers]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($fail = $this->guard($request, [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500', new SafeUrl],
            'enabled' => ['nullable', 'boolean'],
        ])) {
            return $fail;
        }

        $server = new KeyServer; // AssignsOwner stamps user_id on save.
        $server->forceFill([
            'name' => $request->string('name')->value(),
            'url' => rtrim($request->string('url')->value(), '/'),
            'enabled' => $request->boolean('enabled', true),
        ])->save();

        return response()->json(['server' => $server->only(['id', 'name', 'url', 'enabled'])], 201);
    }

    public function update(Request $request, KeyServer $keyServer): JsonResponse
    {
        if ($fail = $this->guard($request, [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'url' => ['sometimes', 'required', 'url', 'max:500', new SafeUrl],
            'enabled' => ['sometimes', 'boolean'],
        ])) {
            return $fail;
        }

        $patch = [];
        if ($request->has('name')) {
            $patch['name'] = $request->string('name')->value();
        }
        if ($request->has('url')) {
            $patch['url'] = rtrim($request->string('url')->value(), '/');
        }
        if ($request->has('enabled')) {
            $patch['enabled'] = $request->boolean('enabled');
        }
        $keyServer->forceFill($patch)->save();

        return response()->json(['server' => $keyServer->only(['id', 'name', 'url', 'enabled'])]);
    }

    public function destroy(KeyServer $keyServer): JsonResponse
    {
        $keyServer->delete(); // owner-scoped by the global scope + route binding

        return response()->json(['ok' => true]);
    }

    /**
     * Search one server (server_id) or every enabled one (omitted) for $query
     * (an email, name fragment, or 0x-prefixed key id). Never fails the whole
     * request when one server errors — that server's slot just comes back
     * empty, so a single unreachable/misconfigured server doesn't block the
     * others.
     */
    public function search(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        if ($fail = $this->guard($request, [
            'query' => ['required', 'string', 'max:300'],
            'server_id' => ['nullable', 'integer'],
        ])) {
            return $fail;
        }

        // Capped: each server is visited sequentially and synchronously (see
        // HkpClient::TIMEOUT's doc) — a hard ceiling bounds worst-case blocking
        // time regardless of how many keyservers the account has configured.
        $servers = KeyServer::query()->ownedBy($uid)->where('enabled', true)
            ->when($request->filled('server_id'), fn ($q) => $q->whereKey($request->integer('server_id')))
            ->orderBy('id')->limit(self::MAX_SERVERS_PER_REQUEST)
            ->get(['id', 'name', 'url']);
        if ($servers->isEmpty()) {
            return response()->json(['results' => []]);
        }

        $query = $request->string('query')->value();
        $results = [];
        foreach ($servers as $server) {
            foreach ($this->hkp->search($server->url, $query) as $candidate) {
                $results[] = ['server_id' => $server->id, 'server_name' => $server->name] + $candidate;
            }
        }

        return response()->json(['results' => $results]);
    }

    /** Fetch a chosen search-result key from $keyServer and save it as a recipient. */
    public function import(Request $request, KeyServer $keyServer): JsonResponse
    {
        if ($fail = $this->guard($request, [
            'key_id' => ['required', 'string', 'max:64'],
            'label' => ['required', 'string', 'max:200'],
        ])) {
            return $fail;
        }

        $armored = $this->hkp->fetch($keyServer->url, $request->string('key_id')->value());
        if ($armored === null) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $fingerprint = $this->cipher->pgpFingerprint($armored);
        if ($fingerprint === null) {
            return response()->json(['error' => 'invalid_key'], 422);
        }

        $recipient = new CryptoRecipient; // AssignsOwner stamps user_id on save.
        $recipient->forceFill([
            'key_server_id' => $keyServer->id,
            'type' => 'pgp',
            'label' => $request->string('label')->value(),
            'fingerprint' => $fingerprint,
            'key_id' => $request->string('key_id')->value(),
            'public_key' => $armored,
            'refreshed_at' => now(),
        ])->save();

        return response()->json(['recipient' => $this->presentRecipient($recipient)], 201);
    }

    /**
     * Re-fetch a saved recipient's key from the server it was originally
     * imported from, by its full fingerprint — updates public_key +
     * refreshed_at (e.g. to pick up a revocation, a new subkey, or a new uid).
     * Only meaningful for keyserver-imported recipients; a manually pasted one
     * (key_server_id null) has no server to refresh from.
     */
    public function refreshRecipient(Request $request, CryptoRecipient $recipient): JsonResponse
    {
        $this->requireUser($request);
        if ($recipient->type !== 'pgp' || $recipient->key_server_id === null || $recipient->fingerprint === null) {
            return response()->json(['error' => 'no_origin_server'], 422);
        }
        $server = KeyServer::query()->ownedBy((int) $recipient->user_id)->find($recipient->key_server_id);
        if ($server === null) {
            return response()->json(['error' => 'no_origin_server'], 422);
        }

        $armored = $this->hkp->fetch($server->url, $recipient->fingerprint);
        if ($armored === null) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $fingerprint = $this->cipher->pgpFingerprint($armored);
        if ($fingerprint === null || strcasecmp($fingerprint, (string) $recipient->fingerprint) !== 0) {
            // The server returned something, but it isn't (still) the same key
            // we asked for by fingerprint — refuse rather than silently
            // swapping the recipient's key material for an unrelated one.
            return response()->json(['error' => 'fingerprint_mismatch'], 422);
        }

        $recipient->forceFill(['public_key' => $armored, 'refreshed_at' => now()])->save();

        return response()->json(['recipient' => $this->presentRecipient($recipient)]);
    }

    /** Publish an own PGP key's public part to a configured server. */
    public function publish(Request $request, MailPgpKey $key): JsonResponse
    {
        $this->authorizeOwnKey($request, $key);
        if ($fail = $this->guard($request, ['server_id' => ['required', 'integer']])) {
            return $fail;
        }
        if ($key->type !== 'pgp' || $key->public_key === null) {
            return response()->json(['error' => 'not_pgp'], 422);
        }
        $server = KeyServer::query()->ownedBy((int) $key->user_id)->find($request->integer('server_id'));
        if ($server === null) {
            return response()->json(['error' => 'unknown_server'], 404);
        }

        $ok = $this->hkp->upload($server->url, $key->public_key);

        return response()->json(['ok' => $ok], $ok ? 200 : 502);
    }

    /**
     * Whether $key is already published, checked against every enabled server
     * on the account (not just one) — the account may have several
     * keyservers configured, and "is it out there" is naturally a
     * per-server question.
     *
     * @return JsonResponse {results: list<{server_id:int, server_name:string, present:bool}>}
     */
    public function checkPresence(Request $request, MailPgpKey $key): JsonResponse
    {
        $this->authorizeOwnKey($request, $key);
        if ($key->type !== 'pgp' || $key->key_fingerprint === null) {
            return response()->json(['error' => 'not_pgp'], 422);
        }

        $servers = KeyServer::query()->ownedBy((int) $key->user_id)->where('enabled', true)
            ->orderBy('id')->limit(self::MAX_SERVERS_PER_REQUEST)->get(['id', 'name', 'url']);
        $results = $servers->map(fn (KeyServer $s): array => [
            'server_id' => $s->id,
            'server_name' => $s->name,
            'present' => $this->hkp->isPresent($s->url, (string) $key->key_fingerprint),
        ])->all();

        return response()->json(['results' => $results]);
    }

    private function authorizeOwnKey(Request $request, MailPgpKey $key): void
    {
        abort_if((int) $key->user_id !== (int) $this->requireUser($request)->id, 404);
    }

    /**
     * Mirrors CryptoController::keyring()'s recipient shape so a freshly
     * imported/refreshed recipient can be merged straight into the client's
     * already-loaded list without dropping any field it started with.
     *
     * @return array{id:int, type:string, label:string, fingerprint:?string, key_id:?string, public_key:?string, cert_pem:?string, key_server_id:?int, refreshed_at:?string}
     */
    private function presentRecipient(CryptoRecipient $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type,
            'label' => $r->label,
            'fingerprint' => $r->fingerprint,
            'key_id' => $r->key_id,
            'public_key' => $r->public_key,
            'cert_pem' => $r->cert_pem,
            'key_server_id' => $r->key_server_id,
            'refreshed_at' => $r->refreshed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, array<int, mixed>>  $rules
     */
    private function guard(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'validation',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return null;
    }
}
