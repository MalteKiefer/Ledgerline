<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FileShare;

/**
 * Stateless "unlock" grant for a public file-share, used by tokenless API clients
 * that have no session cookie. The web `PublicFileShareController` remembers a
 * successful password unlock in the session; a bearer/anonymous API client cannot,
 * so `unlock` also returns one of these signed grants which the client then carries
 * on `manifest`/`raw` (via the `X-Share-Grant` header or a `grant` query parameter).
 *
 * Shape: `base64url(payload).hmac_sha256(payload, APP_KEY)`. The payload carries the
 * share id + a short expiry; the HMAC is additionally bound to the share id AND its
 * (secret) token, so a grant minted for one share is useless for any other share and
 * cannot be forged without APP_KEY. Verified in constant time.
 */
final class ShareGrant
{
    /** Grant lifetime in seconds (short — the client re-unlocks after it lapses). */
    private const TTL = 3600;

    public static function issue(FileShare $share): string
    {
        $payload = (string) json_encode([
            's' => $share->id,
            'exp' => time() + self::TTL,
        ]);
        $body = self::b64UrlEncode($payload);

        return $body.'.'.self::sign($body, $share);
    }

    /** Constant-time validation that a grant authorises unlocking THIS share. */
    public static function valid(?string $grant, FileShare $share): bool
    {
        if (! is_string($grant) || ! str_contains($grant, '.')) {
            return false;
        }

        [$body, $sig] = explode('.', $grant, 2);
        if (! hash_equals(self::sign($body, $share), $sig)) {
            return false;
        }

        $decoded = json_decode(self::b64UrlDecode($body), true);
        if (! is_array($decoded)) {
            return false;
        }

        $sid = $decoded['s'] ?? null;
        $exp = $decoded['exp'] ?? null;

        return is_int($sid) && $sid === $share->id && is_int($exp) && $exp >= time();
    }

    /** HMAC bound to the share id + token (so the signature is share-specific). */
    private static function sign(string $body, FileShare $share): string
    {
        return hash_hmac('sha256', $body.'|'.$share->id.'|'.$share->token, self::key());
    }

    private static function key(): string
    {
        $key = config('app.key');

        return is_string($key) && $key !== '' ? $key : 'ledgerline-share-grant';
    }

    private static function b64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $encoded): string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }
}
