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

    // Methods that our TOTP feature (or a security key) can actually cover.
    private const APP_METHODS = ['totp', 'u2f', 'hardware', 'fido2', 'webauthn'];

    public function index(): JsonResponse
    {
        $domains = Cache::remember('tfa_directory_domains', now()->addDay(), function (): array {
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

        return response()->json(['domains' => $domains], 200, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Flatten the v4 dataset to a unique, lower-cased list of domains whose
     * entry advertises an app/hardware 2FA method.
     *
     * @param  array<int, mixed>  $data
     * @return array<int, string>
     */
    private function parse(array $data): array
    {
        $domains = [];
        foreach ($data as $entry) {
            // v4 shape: ["Name", { domain, additional-domains, tfa, ... }].
            $meta = is_array($entry) ? ($entry[1] ?? null) : null;
            if (! is_array($meta)) {
                continue;
            }
            $tfa = array_map('strtolower', array_filter((array) ($meta['tfa'] ?? []), 'is_string'));
            if (empty(array_intersect($tfa, self::APP_METHODS))) {
                continue;
            }
            foreach (array_merge([$meta['domain'] ?? null], (array) ($meta['additional-domains'] ?? [])) as $d) {
                if (is_string($d) && $d !== '') {
                    $domains[strtolower($d)] = true;
                }
            }
        }

        return array_keys($domains);
    }
}
