<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicShare;
use App\Support\BlobRegistry;
use App\Support\BlobStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stateless public-share consumption API for native clients.
 *
 * Security model
 * ──────────────
 * The share KEY (fragment) never reaches this server — the client holds it and
 * uses it to decrypt the sealed manifest / blobs it receives. This controller
 * therefore only guards:
 *   1. Expiry                → 410
 *   2. Password gate         → stateless signed grant (HMAC, 1 h TTL, see below)
 *   3. allow_download flag   → enforced on /blob
 *   4. blob_refs allowlist   → only blobs declared in the share are streamable
 *   5. user_id ledger check  → owner-scoped blob lookup, denyAsNotFound style
 *
 * Stateless grant mechanism
 * ─────────────────────────
 * The web flow stores an unlock flag in the session. We cannot use a session on
 * the API (stateless, Bearer-less requests). Instead, after a correct password we
 * issue a Laravel *signed URL*-style HMAC grant:
 *
 *   grant = base64url( json({ share_id, expires }) ) . "." . HMAC-SHA256(payload, APP_KEY)
 *
 * The client sends it as the `X-Share-Grant` header (or `grant` query param for
 * blob/manifest endpoints). We verify HMAC + expiry + share_id on every request.
 * TTL is 1 hour. The grant leaks nothing (share_id is not secret; the MAC binds
 * it to APP_KEY so it cannot be forged). No database state is required.
 *
 * Routes (public, no auth middleware, inside prefix api/v1/s):
 *   GET  /api/v1/s/{token}/meta         – share metadata (no secrets)
 *   POST /api/v1/s/{token}/unlock       – password check → grant
 *   GET  /api/v1/s/{token}/manifest     – sealed manifest ciphertext
 *   GET  /api/v1/s/{token}/blob/{ref}   – stream sealed blob bytes
 */
class PublicShareController extends Controller
{
    private const GRANT_TTL_SECONDS = 3600; // 1 hour

    // ─────────────────────────────────────────────────────────────── meta ───

    public function meta(string $token): JsonResponse
    {
        $share = $this->resolve($token);

        if ($share === null) {
            return response()->json(['found' => false], 404);
        }

        if ($share->isExpired()) {
            return response()->json(['found' => true, 'expired' => true], 410);
        }

        return response()->json([
            'found' => true,
            'expired' => false,
            'kind' => $share->kind,
            'needs_password' => $share->needsPassword(),
            'allow_download' => $share->allow_download,
        ]);
    }

    // ────────────────────────────────────────────────────────────── unlock ───

    public function unlock(Request $request, string $token): JsonResponse
    {
        $share = $this->resolve($token);

        if ($share === null || $share->isExpired()) {
            return response()->json(['ok' => false], 404);
        }

        $request->validate(['password' => ['required', 'string', 'max:200']]);

        if (! $share->needsPassword()
            || ! Hash::check($request->string('password')->value(), (string) $share->password_hash)
        ) {
            return response()->json(['ok' => false], 422);
        }

        $grant = $this->issueGrant($share);

        return response()->json(['ok' => true, 'grant' => $grant]);
    }

    // ─────────────────────────────────────────────────────────── manifest ───

    public function manifest(Request $request, string $token): JsonResponse
    {
        $share = $this->resolve($token);

        if ($share === null || $share->isExpired()) {
            abort(404);
        }

        $this->requireGrant($request, $share);

        // Count view once per client per manifest fetch — best-effort, not
        // session-deduplicated on the API (acceptable; native clients call once).
        $share->forceFill(['views' => $share->views + 1, 'last_viewed_at' => now()])->saveQuietly();

        return response()->json([
            'sealed' => $share->sealed_manifest,
            'allow_download' => $share->allow_download,
        ]);
    }

    // ───────────────────────────────────────────────────────────────── blob ───

    public function blob(Request $request, string $token, string $ref): StreamedResponse
    {
        $share = $this->resolve($token);

        if ($share === null || $share->isExpired()) {
            abort(404);
        }

        $this->requireGrant($request, $share);

        if (! $share->allow_download) {
            abort(403, 'Download not allowed for this share.');
        }

        if (! Str::isUuid($ref)) {
            abort(404);
        }

        if (! in_array($ref, $share->blob_refs ?? [], true)) {
            abort(404);
        }

        [$prefix, $ledgerClass] = $this->blobSource($share);

        if (! $ledgerClass::where('blob', $ref)->where('user_id', $share->user_id)->exists()) {
            abort(404);
        }

        $disk = BlobStore::disk();
        $path = $prefix.'/'.$ref;

        if (! $disk->exists($path)) {
            abort(404);
        }

        return BlobStore::immutableResponse(
            $disk->response($path, 'file', ['Content-Type' => 'application/octet-stream'], 'attachment'),
            $ref,
        );
    }

    // ─────────────────────────────────────────────────────────────── grant ───

    /**
     * Issue a stateless HMAC grant for password-protected shares.
     *
     * Format: base64url(json_payload) . "." . base64url(hmac)
     * Payload: {"share_id":<int>,"expires":<unix_timestamp>}
     * HMAC key: APP_KEY (32-byte secret, never sent to client)
     */
    private function issueGrant(PublicShare $share): string
    {
        $encoded = json_encode([
            'share_id' => $share->getKey(),
            'expires' => time() + self::GRANT_TTL_SECONDS,
        ]);

        $b64Payload = rtrim(strtr(base64_encode($encoded !== false ? $encoded : ''), '+/', '-_'), '=');
        $mac = $this->grantMac($b64Payload);

        return $b64Payload.'.'.$mac;
    }

    /**
     * Verify the grant from the request.
     * Reads X-Share-Grant header or ?grant= query param.
     * For password-free shares the grant is not needed and this is a no-op.
     */
    private function requireGrant(Request $request, PublicShare $share): void
    {
        if (! $share->needsPassword()) {
            return;
        }

        $raw = $request->header('X-Share-Grant') ?? $request->string('grant')->value();

        if ($raw === '' || $raw === null) {
            abort(403, 'Grant required.');
        }

        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) {
            abort(403, 'Invalid grant.');
        }

        [$b64Payload, $mac] = $parts;

        // Constant-time MAC verification
        if (! hash_equals($this->grantMac($b64Payload), $mac)) {
            abort(403, 'Invalid grant signature.');
        }

        $json = base64_decode(strtr($b64Payload, '-_', '+/'), true);
        $payload = $json !== false ? json_decode($json, true) : null;

        if (! is_array($payload)
            || ! isset($payload['share_id'], $payload['expires'])
        ) {
            abort(403, 'Grant expired or malformed.');
        }

        $shareId = $payload['share_id'];
        $expires = $payload['expires'];

        if (! is_int($expires) || $expires < time()) {
            abort(403, 'Grant expired or malformed.');
        }

        $rawKey = $share->getKey();
        if (! is_int($shareId) || ! is_int($rawKey) || $shareId !== $rawKey) {
            abort(403, 'Grant share mismatch.');
        }
    }

    /**
     * Compute the HMAC for a grant payload string.
     * Uses APP_KEY (the Laravel application key, already 32 bytes after base64).
     */
    private function grantMac(string $b64Payload): string
    {
        $appKey = config('app.key');
        $appKeyStr = is_string($appKey) ? $appKey : '';
        // Strip the "base64:" prefix that Laravel prepends to generated keys.
        if (str_starts_with($appKeyStr, 'base64:')) {
            $raw = base64_decode(substr($appKeyStr, 7), true);
            $key = $raw !== false ? $raw : $appKeyStr;
        } else {
            $key = $appKeyStr;
        }

        $mac = hash_hmac('sha256', 'share-grant|'.$b64Payload, $key, true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    // ──────────────────────────────────────────────────────────── helpers ───

    private function resolve(string $token): ?PublicShare
    {
        if (! preg_match('/^[A-Za-z0-9]{1,32}$/', $token)) {
            return null;
        }

        return PublicShare::where('token', $token)->first();
    }

    /** @return array{string, class-string<Model>} */
    private function blobSource(PublicShare $share): array
    {
        $module = $share->kind === 'gallery_album' ? 'gallery' : 'files';

        return [BlobRegistry::prefix($module), BlobRegistry::model($module)];
    }
}
