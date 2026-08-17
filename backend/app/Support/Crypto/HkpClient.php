<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use App\Support\Mail\PgpDecryptor;
use App\Support\OutboundUrl;
use Throwable;

/**
 * A minimal HKP (HTTP Keyserver Protocol) client for a user-configured
 * keyserver (see App\Models\KeyServer) — search, fetch-by-id, publish, and a
 * cryptographically-verified presence check. Every request goes through
 * OutboundUrl (SSRF-guarded: link-local/metadata refused, IP pinned against
 * DNS-rebinding) — the server URL is user-supplied, the same posture as
 * Paperless/NTFY/webhook targets elsewhere in this app. Best-effort
 * throughout: a keyserver being unreachable, slow, or returning garbage never
 * throws out to the caller, it just yields an empty/false result.
 */
final class HkpClient
{
    private const TIMEOUT = 10;

    // A malicious or misbehaving keyserver could otherwise stream an
    // unbounded response; this is a plain safety cap, not a real-world size
    // (a full keyserver index response is at most a few hundred KB).
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_RESULTS = 25;

    private const USER_AGENT = 'Ledgerline (self-hosted personal cloud)';

    public function __construct(private readonly FileCipher $cipher) {}

    /**
     * HKP index search (op=index) — candidate keys matching $query (an email,
     * name fragment, or 0x-prefixed id), WITHOUT fetching full key material.
     * Empty on no results / any failure.
     *
     * @return list<array{
     *   key_id: string, fingerprint: ?string, algorithm: ?string, bits: ?int,
     *   created_at: ?int, expires_at: ?int, revoked: bool,
     *   uids: list<array{name: ?string, email: ?string, comment: ?string}>,
     * }>
     */
    public function search(string $serverUrl, string $query): array
    {
        $body = $this->get(rtrim($serverUrl, '/').'/pks/lookup', [
            'op' => 'index', 'options' => 'mr', 'fingerprint' => 'on', 'search' => $query,
        ]);

        return $body === null ? [] : self::parseIndex($body);
    }

    /**
     * Fetch one key's full armored public-key block by key id or fingerprint
     * (a leading "0x" is added if missing). Null on failure/not-found.
     */
    public function fetch(string $serverUrl, string $keyIdOrFingerprint): ?string
    {
        $search = str_starts_with($keyIdOrFingerprint, '0x') ? $keyIdOrFingerprint : '0x'.$keyIdOrFingerprint;
        $body = $this->get(rtrim($serverUrl, '/').'/pks/lookup', [
            'op' => 'get', 'options' => 'mr', 'search' => $search,
        ]);
        if ($body === null || ! str_contains($body, 'BEGIN PGP PUBLIC KEY BLOCK')) {
            return null;
        }

        return $body;
    }

    /**
     * Publish an armored public key via the classic HKP submission endpoint
     * (/pks/add). Not every keyserver supports this synchronously — notably
     * keys.openpgp.org requires an out-of-band email-verification step this
     * does not implement — a false return here means "try a different server
     * or that server's own web upload form", not necessarily an app bug.
     */
    public function upload(string $serverUrl, string $armoredPublicKey): bool
    {
        $url = rtrim($serverUrl, '/').'/pks/add';
        if (! OutboundUrl::safe($url)) {
            return false;
        }
        try {
            $res = OutboundUrl::client($url, self::TIMEOUT)
                ->asForm()
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->post($url, ['keytext' => $armoredPublicKey]);

            return $res->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether a key with EXACTLY this fingerprint is present on the server.
     * Fetches by the full fingerprint (collision-resistant lookup) and then
     * cryptographically re-derives the RETURNED key's own fingerprint via gpg
     * — never trusts a server's label alone, only what our own gpg computes
     * from the bytes it sent back.
     */
    public function isPresent(string $serverUrl, string $fingerprint): bool
    {
        $armored = $this->fetch($serverUrl, $fingerprint);
        if ($armored === null) {
            return false;
        }
        $actual = $this->cipher->pgpFingerprint($armored);

        return $actual !== null && strcasecmp($actual, $fingerprint) === 0;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function get(string $url, array $params): ?string
    {
        if (! OutboundUrl::safe($url)) {
            return null;
        }
        try {
            $res = OutboundUrl::client($url, self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url, $params);
            if ($res->status() === 404) {
                return null; // "no results" / "not found" on this server — not an error
            }
            if (! $res->successful()) {
                return null;
            }
            $body = (string) $res->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            return $body;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse an HKP `op=index&options=mr` response. Field layout (colon-split,
     * 0-indexed here, per the HKP machine-readable index format): a `pub`
     * record is key_id/algorithm/bits/creation/expiration/flags at indices
     * 1..5 (flags may contain "r"=revoked), followed by zero or more `uid`
     * records whose index 1 is the url-encoded user id string.
     *
     * @return list<array{
     *   key_id: string, fingerprint: ?string, algorithm: ?string, bits: ?int,
     *   created_at: ?int, expires_at: ?int, revoked: bool,
     *   uids: list<array{name: ?string, email: ?string, comment: ?string}>,
     * }>
     */
    private static function parseIndex(string $body): array
    {
        /** @var list<array{key_id: string, fingerprint: ?string, algorithm: ?string, bits: ?int, created_at: ?int, expires_at: ?int, revoked: bool, uids: list<array{name: ?string, email: ?string, comment: ?string}>}> $out */
        $out = [];
        $current = null;

        foreach (explode("\n", $body) as $rawLine) {
            $line = rtrim($rawLine, "\r");
            if ($line === '') {
                continue;
            }
            $f = explode(':', $line);
            $type = $f[0] ?? '';

            if ($type === 'pub') {
                if ($current !== null) {
                    $out[] = $current;
                    if (count($out) >= self::MAX_RESULTS) {
                        return $out;
                    }
                }
                $keyId = $f[1] ?? '';
                if ($keyId === '') {
                    $current = null;

                    continue;
                }
                $algoRaw = $f[2] ?? '';
                $flags = $f[6] ?? '';
                $bitsRaw = $f[3] ?? '';
                $createdRaw = $f[4] ?? '';
                $expiresRaw = $f[5] ?? '';
                $current = [
                    'key_id' => $keyId,
                    'fingerprint' => strlen($keyId) === 40 ? strtoupper($keyId) : null,
                    'algorithm' => PgpDecryptor::algorithmName($algoRaw !== '' ? $algoRaw : null),
                    'bits' => ctype_digit($bitsRaw) ? (int) $bitsRaw : null,
                    'created_at' => ctype_digit($createdRaw) ? (int) $createdRaw : null,
                    'expires_at' => ctype_digit($expiresRaw) ? (int) $expiresRaw : null,
                    'revoked' => str_contains($flags, 'r'),
                    'uids' => [],
                ];
            } elseif ($type === 'uid' && $current !== null) {
                $raw = urldecode($f[1] ?? '');
                if ($raw !== '') {
                    $current['uids'][] = PgpDecryptor::parseUserId($raw);
                }
            }
        }
        if ($current !== null) {
            $out[] = $current;
        }

        return array_slice($out, 0, self::MAX_RESULTS);
    }
}
