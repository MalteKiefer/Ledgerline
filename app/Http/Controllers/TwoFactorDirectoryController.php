<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Serves the list of domains known to support app-based two-factor auth, from
 * the public 2fa.directory dataset (https://2fa.directory/api). The client uses
 * it to hint, 1Password-style, when a stored login could add a one-time code.
 *
 * The dataset is public and identical for everyone, so fetching it server-side
 * and caching it leaks nothing about the user's vault (zero-knowledge intact) —
 * the matching of a domain against the user's own entries happens only in the
 * browser. The outbound fetch goes through the SSRF guard.
 */
class TwoFactorDirectoryController extends Controller
{
    private const SOURCE = 'https://api.2fa.directory/v4/all.json';

    // App/hardware 2FA methods (v4 vocabulary) — the actionable ones our TOTP
    // feature or a security key covers. SMS / e-mail / phone calls are excluded.
    private const APP_METHODS = ['totp', 'u2f', 'custom-software', 'custom-hardware'];

    public function index(): JsonResponse
    {
        $entries = Cache::remember('tfa_directory_entries_v3', now()->addDay(), function (): array {
            try {
                $res = OutboundUrl::client(self::SOURCE, 12)
                    ->withHeaders(['User-Agent' => 'Ledgerline', 'Accept' => 'application/json'])
                    ->get(self::SOURCE);
                if (! $res->ok()) {
                    return [];
                }

                return $this->parse($res->json() ?? []);
            } catch (Throwable) {
                return [];
            }
        });

        // { domain: documentationUrl } — the client matches its own login
        // domains against the keys and links the doc URL for setup instructions.
        return response()->json(['entries' => $entries], 200, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Flatten the v4 dataset to a unique, lower-cased list of domains whose
     * entry advertises an app/hardware 2FA method. v4 is a flat object keyed by
     * domain: { "example.com": { "methods": ["totp","sms"], ... }, ... }.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function parse(array $data): array
    {
        $map = [];
        foreach ($data as $domain => $meta) {
            if (! is_string($domain) || $domain === '' || ! is_array($meta)) {
                continue;
            }
            $methods = array_map('strtolower', array_filter((array) ($meta['methods'] ?? []), 'is_string'));
            if (empty(array_intersect($methods, self::APP_METHODS))) {
                continue;
            }
            $doc = is_string($meta['documentation'] ?? null) ? $meta['documentation'] : '';
            $d = strtolower($domain);
            // The dataset keys specific subdomains (accounts.google.com), but users
            // often store the bare domain — index both so either matches.
            $map[$d] = $doc;
            $reg = $this->registrable($d);
            if (! isset($map[$reg]) || $map[$reg] === '') {
                $map[$reg] = $doc;
            }
        }

        return $map;
    }

    /** Best-effort registrable domain (no PSL): last 2 labels, or 3 for a ccSLD. */
    private function registrable(string $d): string
    {
        $p = explode('.', $d);
        $n = count($p);
        if ($n <= 2) {
            return $d;
        }
        $sld = ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu', 'gob', 'go'];
        $take = (strlen($p[$n - 1]) === 2 && in_array($p[$n - 2], $sld, true)) ? 3 : 2;

        return implode('.', array_slice($p, -$take));
    }
}
